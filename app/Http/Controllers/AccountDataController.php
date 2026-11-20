<?php

namespace App\Http\Controllers;

use App\Models\Plant;
use App\Models\PlantPhotoEntry;
use App\Models\User;
use App\Services\PlantPhoto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class AccountDataController extends Controller
{
    public function export(Request $request): JsonResponse
    {
        $user = $request->user();
        $plants = $user->plants()
            ->with(['needs', 'history', 'careLogs', 'careTasks.completions', 'observations', 'plantData', 'checkSchedule', 'plantChecks'])
            ->orderBy('id')->get()->map(function (Plant $plant): array {
                return [
                    ...$plant->makeHidden(['avatar', 'cover_public_id'])->toArray(),
                    'cover_url' => $plant->avatarUrl(),
                    'photo_journal' => PlantPhotoEntry::query()->forPlant($plant)->orderBy('id')->get(['id', 'taken_on', 'note'])
                        ->map(fn ($photo) => [
                            ...$photo->toArray(),
                            'url' => route('plants.photo-journal.show', [$plant, $photo]),
                        ]),
                ];
            });

        return response()->json([
            'version' => 1,
            'exported_at' => now()->toIso8601String(),
            'account' => $user->only(['email', 'email_verified_at', 'created_at', 'reminders_enabled', 'reminder_timezone', 'reminder_time', 'push_reminders_enabled']),
            'plants' => $plants,
        ], 200, [
            'Content-Disposition' => 'attachment; filename="bloomify-export.json"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function destroy(Request $request, PlantPhoto $photos): RedirectResponse
    {
        $password = $request->validate(['current_password' => ['required', 'string', 'current_password']])['current_password'];
        $userId = $request->user()->id;

        DB::transaction(function () use ($photos, $userId, $password) {
            $user = User::query()->lockForUpdate()->findOrFail($userId);
            if (! Hash::check($password, $user->password)) {
                throw ValidationException::withMessages(['current_password' => 'The password is incorrect.']);
            }

            foreach ($user->plants()->lockForUpdate()->lazyById() as $plant) {
                $photos->discardPlantMedia($plant);
            }
            Password::broker()->deleteToken($user);
            $user->delete();
        }, 3);

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('welcome');
    }
}
