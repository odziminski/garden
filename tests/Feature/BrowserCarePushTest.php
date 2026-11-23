<?php

namespace Tests\Feature;

use App\Models\PushSubscription;
use App\Models\User;
use App\Services\BrowserCarePush;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrowserCarePushTest extends TestCase
{
    use RefreshDatabase;

    public function test_push_signs_the_request_and_removes_expired_devices(): void
    {
        // The P-256 generator point corresponds to the public test scalar 1.
        $point = hex2bin('046b17d1f2e12c4247f8bce6e563a440f277037d812deb33a0f4a13945d898c2964fe342e2fe1a7f9b8ee7eb4a7c0f9e162bce33576b315ececbb6406837bf51f5');
        $encode = fn (string $bytes) => rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
        config([
            'push.public_key' => $encode($point),
            'push.private_key' => $encode(str_repeat(chr(0), 31).chr(1)),
            'push.subject' => 'mailto:admin@example.test',
        ]);
        $user = User::factory()->create(['push_reminders_enabled' => true]);
        $endpoint = 'https://fcm.googleapis.com/fcm/send/expired';
        $device = PushSubscription::query()->create([
            'user_id' => $user->id,
            'endpoint_hash' => hash('sha256', $endpoint),
            'endpoint' => $endpoint,
            'public_key' => $encode($point),
            'auth_token' => $encode(str_repeat('x', 16)),
        ]);
        $history = [];
        $handler = HandlerStack::create(new MockHandler([new Response(410)]));
        $handler->push(Middleware::history($history));

        (new BrowserCarePush(new Client(['handler' => $handler])))->send($user, new Collection);

        $this->assertModelMissing($device);
        $this->assertFalse($user->fresh()->push_reminders_enabled);
        $this->assertCount(1, $history);
        $this->assertStringStartsWith('vapid t=', $history[0]['request']->getHeaderLine('Authorization'));
        $this->assertSame($endpoint, (string) $history[0]['request']->getUri());
    }
}
