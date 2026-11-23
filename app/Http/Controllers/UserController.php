<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProfileRequest;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class UserController extends Controller
{
    public function displayUserData(Request $request): View
    {
        return view('users', ['user' => $request->user()]);
    }

    public function editUserProfile(UpdateProfileRequest $request): RedirectResponse
    {
        $currentUser = $request->user();
        $data = $request->validated();

        try {
            [$user, $emailChanged] = DB::transaction(function () use ($currentUser, $data): array {
                $query = User::query()->whereKey($currentUser->getKey());
                if (DB::getDriverName() === 'sqlite') {
                    (clone $query)->toBase()->update(['id' => DB::raw('id')]);
                }
                $user = $query->lockForUpdate()->firstOrFail();

                if (! Hash::check($data['current_password'], $user->password)) {
                    throw ValidationException::withMessages(['current_password' => __('The password is incorrect.')]);
                }

                $emailChanged = $user->email !== $data['email'];
                $password = $data['password'] ?? null;
                if ($emailChanged || $password !== null) {
                    Password::broker()->deleteToken($user);
                    $user->setRememberToken(Str::random(60));
                }

                if ($emailChanged) {
                    $user->email = $data['email'];
                    $user->email_verified_at = null;
                }

                if ($password !== null) {
                    $user->password = Hash::make($password);
                }

                $user->save();

                return [$user, $emailChanged];
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['email' => __('The email has already been taken.')]);
        }

        $currentUser->setRawAttributes($user->getAttributes(), true);
        $request->session()->regenerate();

        if ($emailChanged) {
            try {
                $user->sendEmailVerificationNotification();
            } catch (Throwable $exception) {
                report($exception);

                return redirect()->route('users')->with('message', 'Profile updated. Use the verification page to resend the email.');
            }
        }

        return redirect()->route('users')->with('message', 'Profile updated.');
    }
}
