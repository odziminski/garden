<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use App\Models\User;
use App\Services\BrowserCarePush;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class PushSubscriptionController extends Controller
{
    public function store(Request $request): Response
    {
        abort_unless(BrowserCarePush::configured(), 503, 'Browser reminders are unavailable.');
        $data = $request->validate([
            'endpoint' => ['bail', 'required', 'string', 'url:https', 'max:2048', function ($attribute, $value, $fail) {
                if (! PushSubscription::supportsEndpoint($value)) {
                    $fail('This browser notification provider is unsupported.');
                }
            }],
            'keys.p256dh' => ['required', 'string', 'regex:/\A[A-Za-z0-9_-]{87}\z/'],
            'keys.auth' => ['required', 'string', 'regex:/\A[A-Za-z0-9_-]{22}\z/'],
        ]);

        DB::transaction(function () use ($request, $data) {
            $user = User::query()->lockForUpdate()->findOrFail($request->user()->id);
            $subscriptions = PushSubscription::query()->where('user_id', $user->id);
            $hash = hash('sha256', $data['endpoint']);
            if (! (clone $subscriptions)->where('endpoint_hash', $hash)->exists()) {
                abort_if($subscriptions->count() >= 5, 422, 'Disable an existing device before adding another.');
            }

            $subscription = PushSubscription::query()->firstOrCreate(
                ['endpoint_hash' => $hash],
                [
                    'user_id' => $user->id, 'endpoint' => $data['endpoint'],
                    'public_key' => $data['keys']['p256dh'], 'auth_token' => $data['keys']['auth'],
                ],
            );
            abort_unless($subscription->user_id === $user->id, 409, 'This device is linked to another account.');

            $subscription->update(['public_key' => $data['keys']['p256dh'], 'auth_token' => $data['keys']['auth']]);
            $user->forceFill(['push_reminders_enabled' => true])->save();
        }, 3);

        return response()->noContent();
    }

    public function destroy(Request $request): Response
    {
        $endpoint = $request->validate(['endpoint' => ['required', 'string', 'max:2048']])['endpoint'];
        DB::transaction(function () use ($request, $endpoint) {
            $user = User::query()->lockForUpdate()->findOrFail($request->user()->id);
            $subscriptions = PushSubscription::query()->where('user_id', $user->id);
            (clone $subscriptions)->where('endpoint_hash', hash('sha256', $endpoint))->delete();
            $user->forceFill(['push_reminders_enabled' => $subscriptions->exists()])->save();
        }, 3);

        return response()->noContent();
    }
}
