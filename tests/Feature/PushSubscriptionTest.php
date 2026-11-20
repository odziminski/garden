<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PushSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_push_requires_verified_email_and_rejects_non_provider_endpoints(): void
    {
        config(['push.public_key' => 'public', 'push.private_key' => 'private', 'push.subject' => 'mailto:admin@example.test']);
        $url = '/account/push';
        $verified = User::factory()->create();
        $unverified = User::factory()->create(['email_verified_at' => null]);

        $this->putJson($url, $this->subscription())->assertUnauthorized();
        $this->actingAs($unverified)->putJson($url, $this->subscription())->assertForbidden();
        $this->actingAs($verified)->putJson($url, $this->subscription('https://internal.example.test/push'))
            ->assertUnprocessable()->assertJsonValidationErrors('endpoint');
    }

    public function test_devices_can_unsubscribe_and_cannot_edit_another_users_subscription(): void
    {
        config(['push.public_key' => 'public', 'push.private_key' => 'private', 'push.subject' => 'mailto:admin@example.test']);
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $data = $this->subscription();

        $this->actingAs($owner)->putJson('/account/push', $data)->assertNoContent();
        $this->assertTrue($owner->fresh()->push_reminders_enabled);
        $this->actingAs($other)->putJson('/account/push', $data)->assertConflict();
        $this->deleteJson('/account/push', ['endpoint' => $data['endpoint']])->assertNoContent();
        $this->assertDatabaseCount('push_subscriptions', 1);
        $this->actingAs($owner)->deleteJson('/account/push', ['endpoint' => $data['endpoint']])->assertNoContent();
        $this->assertFalse($owner->fresh()->push_reminders_enabled);
    }

    private function subscription(string $endpoint = 'https://fcm.googleapis.com/fcm/send/test'): array
    {
        return [
            'endpoint' => $endpoint,
            'keys' => [
                'p256dh' => rtrim(strtr(base64_encode(chr(4).str_repeat('x', 64)), '+/', '-_'), '='),
                'auth' => rtrim(strtr(base64_encode(str_repeat('x', 16)), '+/', '-_'), '='),
            ],
        ];
    }
}
