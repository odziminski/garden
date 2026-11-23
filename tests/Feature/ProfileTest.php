<?php

namespace Tests\Feature;

use App\Http\Requests\UpdateProfileRequest;
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

    public function test_profile_update_requires_the_password_to_still_match_when_the_record_is_written(): void
    {
        $user = User::factory()->create();
        $this->app->afterResolving(UpdateProfileRequest::class, function () use ($user): void {
            User::query()->whereKey($user->id)->update(['password' => Hash::make('updated concurrently')]);
        });

        $this->actingAs($user)->putJson(route('editUserProfile'), [
            'email' => 'new-address@example.test',
            'current_password' => 'password',
            'password' => 'stale request password',
            'password_confirmation' => 'stale request password',
        ])->assertUnprocessable()->assertJsonValidationErrors('current_password');

        $this->assertSame($user->email, $user->fresh()->email);
        $this->assertTrue(Hash::check('updated concurrently', $user->fresh()->password));
    }
}
