<?php

namespace App\Services;

use App\Contracts\CarePushDelivery;
use App\Models\PushSubscription;
use App\Models\User;
use GuzzleHttp\Client;
use Illuminate\Database\Eloquent\Collection;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Psr\Http\Client\ClientInterface;
use RuntimeException;

class BrowserCarePush implements CarePushDelivery
{
    public function __construct(private readonly ?ClientInterface $client = null) {}

    public static function configured(): bool
    {
        return filled(config('push.public_key')) && filled(config('push.private_key')) && filled(config('push.subject'));
    }

    public function send(User $recipient, Collection $plants): void
    {
        if (! self::configured()) {
            throw new RuntimeException('Browser care reminders need valid Web Push keys.');
        }

        $sender = new WebPush(['VAPID' => [
            'subject' => config('push.subject'),
            'publicKey' => config('push.public_key'),
            'privateKey' => config('push.private_key'),
        ]], ['TTL' => 3600, 'topic' => 'bloomify-daily-care'], $this->client ?? new Client([
            'connect_timeout' => 3, 'timeout' => 6, 'allow_redirects' => false,
        ]));

        $failed = false;
        foreach (PushSubscription::query()->where('user_id', $recipient->id)->limit(5)->get() as $device) {
            if (! PushSubscription::supportsEndpoint($device->endpoint)) {
                $device->delete();

                continue;
            }

            $report = $sender->sendOneNotification(Subscription::create([
                'endpoint' => $device->endpoint,
                'publicKey' => $device->public_key,
                'authToken' => $device->auth_token,
                'contentEncoding' => 'aes128gcm',
            ]));
            if ($report->isSubscriptionExpired()) {
                $device->delete();
            } elseif (! $report->isSuccess()) {
                $failed = true;
            }
        }

        if ($failed) {
            throw new RuntimeException('A browser provider rejected a care reminder.');
        }
    }
}
