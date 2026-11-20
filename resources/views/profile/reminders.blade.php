<section class="card bloom-panel" aria-labelledby="reminder-heading">
    <div class="card-body">
        <h2 class="h4" id="reminder-heading">Daily care reminders</h2>
        <p>Choose when to receive one email with all plants that need care.</p>

        @if (session('reminder_message'))
            <p class="alert alert-success" role="status">{{ session('reminder_message') }}</p>
        @endif

        @if ($errors->reminders->any())
            <ul class="alert alert-danger" role="alert">
                @foreach ($errors->reminders->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        @endif

        @php($previousTime = old('reminder_time'))
        <form method="POST" action="{{ route('reminders.update') }}">
            @csrf
            @method('PUT')
            <div class="form-check form-switch mb-3">
                <input type="hidden" name="reminders_enabled" value="0">
                <input class="form-check-input" id="reminders-enabled" type="checkbox" name="reminders_enabled"
                       value="1" @checked(old('reminders_enabled', $user->reminders_enabled))>
                <label class="form-check-label" for="reminders-enabled">Send daily reminders by email</label>
            </div>

            <label class="form-label" for="reminder-time">Reminder time</label>
            <input class="form-control mb-3" id="reminder-time" name="reminder_time" type="time"
                   value="{{ is_string($previousTime) ? $previousTime : $user->reminder_time }}" required>

            <label class="form-label" for="reminder-timezone">Time zone</label>
            <select class="form-select mb-3" id="reminder-timezone" name="reminder_timezone" required>
                @foreach (DateTimeZone::listIdentifiers() as $timezone)
                    <option value="{{ $timezone }}" @selected(old('reminder_timezone', $user->reminder_timezone) === $timezone)>
                        {{ str_replace('_', ' ', $timezone) }}
                    </option>
                @endforeach
            </select>

            <button class="btn btn-primary" type="submit">Save reminders</button>
        </form>
    </div>
</section>
