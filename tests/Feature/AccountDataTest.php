<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AccountDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_sends_verification_and_the_signed_link_verifies_only_the_expected_address(): void
    {
        Notification::fake();
        $this->post('/register', [
            'email' => 'new@example.test',
            'password' => 'a-strong-password',
            'password_confirmation' => 'a-strong-password',
        ])->assertRedirect();

        $user = User::where('email', 'new@example.test')->sole();
        $this->assertFalse($user->hasVerifiedEmail());
        Notification::assertSentTo($user, VerifyEmail::class);

        $wrongLink = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $user->id, 'hash' => sha1('old@example.test'),
        ]);
        $this->get($wrongLink)->assertForbidden();
        $link = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $user->id, 'hash' => sha1($user->email),
        ]);
        $this->get($link)->assertRedirect();
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_export_contains_only_owned_data_and_no_credentials(): void
    {
        $owner = User::factory()->create();
        $owner->plants()->create(['name' => 'My fern']);
        User::factory()->create()->plants()->create(['name' => 'Private cactus']);

        $this->actingAs($owner)->get(route('account.export'))
            ->assertOk()
            ->assertDownload('bloomify-export.json')
            ->assertJsonPath('plants.0.name', 'My fern')
            ->assertDontSee('Private cactus')
            ->assertDontSee('password')
            ->assertDontSee('remember_token');
    }

    public function test_deletion_requires_the_password_and_removes_owned_plants(): void
    {
        $owner = User::factory()->create();
        $plant = $owner->plants()->create(['name' => 'My fern']);
        $other = User::factory()->create();

        $this->actingAs($owner)->deleteJson(route('account.destroy'), ['current_password' => 'wrong'])
            ->assertUnprocessable()->assertJsonValidationErrors('current_password');
        $this->assertModelExists($plant);

        $this->delete(route('account.destroy'), ['current_password' => 'password'])->assertRedirect('/');
        $this->assertModelMissing($owner);
        $this->assertModelMissing($plant);
        $this->assertModelExists($other);
        $this->assertGuest();
    }
}
