@if (\App\Services\BrowserCarePush::configured() && $user->hasVerifiedEmail())
    <section class="card bloom-panel" data-browser-push
             data-url="{{ route('account.push.store') }}"
             data-worker="{{ asset('care-notifications.js') }}"
             data-public-key="{{ config('push.public_key') }}">
        <div class="card-body">
            <h2 class="h4">Browser reminders</h2>
            <p>Use your daily reminder time for this device. On iPhone and iPad, first add Bloomify to your Home Screen.</p>
            <div class="d-flex gap-2 flex-wrap">
                <button type="button" class="btn btn-outline-primary" data-push-enable hidden>Enable on this device</button>
                <button type="button" class="btn btn-outline-secondary" data-push-disable hidden>Disable on this device</button>
            </div>
            <p class="mb-0 mt-2" role="status" data-push-status></p>
        </div>
    </section>
@endif
