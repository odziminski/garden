<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_changes_require_current_password_and_unique_email(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $this->actingAs($user)->putJson(route('editUserProfile'), [
            'email' => $otherUser->email,
            'current_password' => 'incorrect password',
        ])->assertUnprocessable()->assertJsonValidationErrors(['email', 'current_password']);

        $this->assertSame($user->email, $user->fresh()->email);
    }

    public function test_user_can_keep_email_and_change_password(): void
    {
        $user = User::factory()->create();
        $resetToken = Password::broker()->createToken($user);

        $this->actingAs($user)->put(route('editUserProfile'), [
            'email' => $user->email,
            'current_password' => 'password',
            'password' => 'a new password',
            'password_confirmation' => 'a new password',
        ])->assertRedirect(route('users'));

        $this->assertTrue(Hash::check('a new password', $user->fresh()->password));
        $this->assertFalse(Password::broker()->tokenExists($user, $resetToken));
        $this->get(route('users'))->assertOk()->assertSee($user->email);
    }

    public function test_user_can_change_email_without_resetting_password(): void
    {
        $user = User::factory()->create();
        $previousHash = $user->password;

        $this->actingAs($user)->put(route('editUserProfile'), [
            'email' => 'gardener@example.net',
            'current_password' => 'password',
        ])->assertRedirect(route('users'));

        $this->assertSame('gardener@example.net', $user->fresh()->email);
        $this->assertSame($previousHash, $user->fresh()->password);
        $this->assertNull($user->fresh()->email_verified_at);
    }
}
