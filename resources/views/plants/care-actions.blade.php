@php
    $caretakingTime ??= now($plant->careTimezone());
@endphp
<div class="d-grid gap-3">
    @foreach (\App\Enums\CareType::cases() as $careType)
        @php
            $dueDate = \App\Services\PlantCareSchedule::nextDate($plant, $careType, $caretakingTime);
        @endphp
        @if ($dueDate)
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                <div>
                    <strong>{{ $careType->reminderLabel() }}</strong>
                    @if ($dueDate->lte($caretakingTime))
                        <span class="badge rounded-pill text-bg-warning">
                            {{ $dueDate->isSameDay($caretakingTime) ? 'Due today' : 'Overdue' }}
                        </span>
                    @endif
                    <div class="small text-muted">
                        Due <time datetime="{{ $dueDate->toDateString() }}">{{ $dueDate->format('M j, Y') }}</time>
                    </div>
                </div>
                @if ($dueDate->lte($caretakingTime))
                    <form method="POST" action="{{ route($careType->actionRoute(), $plant) }}">
                        @csrf
                        <button class="btn btn-primary btn-sm" type="submit"
                                aria-label="Mark {{ $plant->name }} as {{ strtolower($careType->actionLabel()) }}">{{ $careType->actionLabel() }}</button>
                    </form>
                @endif
            </div>
        @endif
    @endforeach
</div>
