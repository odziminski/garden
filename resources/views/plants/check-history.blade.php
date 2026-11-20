<section class="bloom-panel" id="plant-checks" aria-labelledby="soil-checks-title">
    <h2 class="h4" id="soil-checks-title">Soil checks</h2>
    @include('plants.check-feedback')

    @if ($plant->checkSchedule?->enabled && $plant->checkSchedule->next_check_on)
        <p>Next check <time datetime="{{ $plant->checkSchedule->next_check_on }}">{{ \Carbon\CarbonImmutable::parse($plant->checkSchedule->next_check_on)->format('M j, Y') }}</time></p>
    @endif
    @include('plants.check-actions')

    @if ($canManage)
        @php($checkSchedule = $plant->checkSchedule)
        @php($legacyDays = $plant->needs?->watering_frequency ?? 0)
        <details class="my-3" @if ($errors->plantCheckSchedule->any()) open @endif>
            <summary class="fw-semibold">Soil check settings</summary>
            <form method="POST" action="{{ route('plant-checks.schedule', $plant) }}" class="mt-2">
                @csrf
                @method('PATCH')
                <div class="form-check mb-3">
                    <input type="hidden" name="enabled" value="0">
                    <input class="form-check-input" id="check-enabled" name="enabled" type="checkbox" value="1"
                           @checked(old('enabled', $checkSchedule?->enabled))>
                    <label class="form-check-label" for="check-enabled">Enable soil check reminders</label>
                </div>
                <div class="row g-3 mb-2">
                    <div class="col-sm-6">
                        <label class="form-label" for="check-date-setting">Next check date</label>
                        <input class="form-control" type="date" id="check-date-setting" name="next_check_on"
                               value="{{ is_string(old('next_check_on', $checkSchedule?->next_check_on)) ? old('next_check_on', $checkSchedule?->next_check_on) : '' }}">
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label" for="check-interval-setting">Repeat every (days)</label>
                        <input class="form-control" type="number" id="check-interval-setting" name="interval_days" min="1" max="3650"
                               value="{{ is_scalar(old('interval_days', $checkSchedule?->interval_days)) ? old('interval_days', $checkSchedule?->interval_days) : '' }}">
                    </div>
                </div>
                @if ($legacyDays > 0)
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" id="use-legacy-interval" name="use_legacy_interval"
                               value="1" @checked(old('use_legacy_interval'))>
                        <label class="form-check-label" for="use-legacy-interval">
                            Use the previous {{ $legacyDays }}-day interval for soil checks
                        </label>
                    </div>
                @endif
                <p class="form-text">Leave the interval blank for a one-time check. Enabling checks replaces the old watering schedule.</p>
                <button class="btn btn-outline-primary" type="submit">Save soil check settings</button>
            </form>
        </details>
    @endif

    <h3 class="h6 mt-3">Recent checks</h3>
    @forelse ($plantChecks as $check)
        <div class="d-flex flex-wrap gap-2 justify-content-between border-top py-2">
            <div>
                <strong>{{ $check->outcome->value === 'watered' ? 'Watered' : 'Still moist' }}</strong>
                <time class="text-muted small" datetime="{{ $check->checked_on }}">{{ \Carbon\CarbonImmutable::parse($check->checked_on)->format('M j, Y') }}</time>
                @if ($check->checkedBy)
                    <p class="small text-muted text-break mb-0">Checked by {{ $check->checkedBy->email }}</p>
                @endif
                @if ($check->note)
                    <p class="mb-0 text-break">{{ $check->note }}</p>
                @endif
            </div>
            @if ($canManage || (int) $check->checked_by_user_id === (int) auth()->id())
                @if ($plant->checkSchedule?->version === $check->schedule_version + 1)
                    <form method="POST" action="{{ route('plant-checks.undo', [$plant, $check]) }}">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-outline-secondary btn-sm" type="submit">Undo check</button>
                    </form>
                @endif
            @endif
        </div>
    @empty
        <p class="text-muted mb-0">No soil checks recorded on this page.</p>
    @endforelse
    @if ($plantChecks->hasPages())
        <nav class="d-flex gap-2 mt-2" aria-label="Soil check pages">
            @if ($plantChecks->previousPageUrl())
                <a class="btn btn-outline-secondary btn-sm" href="{{ $plantChecks->previousPageUrl() }}">Newer checks</a>
            @endif
            @if ($plantChecks->nextPageUrl())
                <a class="btn btn-outline-secondary btn-sm" href="{{ $plantChecks->nextPageUrl() }}">Older checks</a>
            @endif
        </nav>
    @endif
</section>
