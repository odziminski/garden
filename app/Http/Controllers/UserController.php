<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

class UserController extends Controller
{
    public function displayUserData(Request $request): View
    {
        return view('users', ['user' => $request->user()]);
    }

    public function editUserProfile(UpdateProfileRequest $request): RedirectResponse
    {
        $user = $request->user();
        $email = $request->validated('email');
        $password = $request->validated('password');
        $emailChanged = $user->email !== $email;

        DB::transaction(function () use ($user, $email, $password, $emailChanged) {
            if ($emailChanged || $password !== null) {
                Password::broker()->deleteToken($user);
                $user->setRememberToken(Str::random(60));
            }

            if ($emailChanged) {
                $user->email = $email;
                $user->email_verified_at = null;
            }

            if ($password !== null) {
                $user->password = Hash::make($password);
            }

            $user->save();
        });

        $request->session()->regenerate();

        return redirect()->route('users')->with('message', 'Profile updated.');
    }
}
