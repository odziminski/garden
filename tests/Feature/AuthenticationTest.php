<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_rejects_password_over_the_bcrypt_byte_limit(): void
    {
        $password = str_repeat("\u{00E9}", 37);

        $this->postJson(route('register'), [
            'email' => 'gardener@example.test',
            'password' => $password,
            'password_confirmation' => $password,
        ])->assertUnprocessable()->assertJsonValidationErrors('password');

        $this->assertDatabaseCount('users', 0);
    }

    public function test_password_reset_keeps_working_after_authentication_upgrade(): void
    {
        $user = User::factory()->create();
        $token = Password::broker()->createToken($user);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'a different password',
            'password_confirmation' => 'a different password',
        ])->assertRedirect(route('welcome'));

        $this->assertTrue(Hash::check('a different password', $user->fresh()->password));
        $this->assertFalse(Password::broker()->tokenExists($user, $token));
    }
}
