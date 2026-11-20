    @if ($lastCheck = $plant->latestPlantCheck)
        <p class="small text-muted mt-2 mb-1">
            Last check {{ \Carbon\CarbonImmutable::parse($lastCheck->checked_on)->format('M j') }}
            · {{ $lastCheck->outcome->value === 'watered' ? 'Watered' : 'Still moist' }}
            @if ($lastCheck->checkedBy)
                · {{ $lastCheck->checkedBy->email }}
            @endif
        </p>
    @endif
    @if ($plant->care_instructions)
        <p class="small text-break mt-2 mb-2">{{ $plant->care_instructions }}</p>
    @endif
    <form method="POST" action="{{ route('plant-checks.store', $plant) }}" class="mt-2">
        @csrf
        <input type="hidden" name="schedule_version" value="{{ $plant->checkSchedule?->version ?? 0 }}">
        <div class="d-flex flex-wrap gap-2">
            <button class="btn btn-primary btn-sm" type="submit" name="outcome" value="watered"
                    aria-label="Watered {{ $plant->name }}">Watered</button>
            <button class="btn btn-outline-primary btn-sm" type="submit" name="outcome" value="still_moist"
                    aria-label="Still moist: {{ $plant->name }}">Still moist</button>
            @if ($plant->checkSchedule?->isScheduled())
                <button class="btn btn-outline-secondary btn-sm" type="submit" name="outcome" value="skipped"
                        aria-label="Skip today: {{ $plant->name }}">Skip today</button>
            @endif
        </div>
        @if ($plant->checkSchedule?->isScheduled())
            <details class="small mt-2">
                <summary>Choose the next check date</summary>
                <label class="form-label mt-2" for="next-check-{{ $plant->id }}">Check again on</label>
                <input class="form-control form-control-sm w-auto" id="next-check-{{ $plant->id }}" name="next_check_on"
                       type="date" min="{{ now($plant->careTimezone())->addDay()->toDateString() }}" max="9999-12-31">
            </details>
        @endif
    </form>
