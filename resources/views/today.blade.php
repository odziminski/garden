@extends('layouts.app')

@section('title', 'Today')

@section('content')
    <div class="container py-5">
        <header class="bloom-page-heading d-flex flex-wrap align-items-end justify-content-between gap-3">
            <div>
                <h1>Today</h1>
                <p class="text-muted mb-0"><time datetime="{{ $today->toDateString() }}">{{ $today->format('l, F j, Y') }}</time></p>
            </div>
            <a class="btn btn-outline-secondary" href="{{ route('browse') }}">Browse plants</a>
        </header>

        @foreach (['care_status' => 'success', 'care_notice' => 'info', 'custom_task_status' => 'success', 'custom_task_notice' => 'info'] as $notice => $variant)
            @if (session($notice))
                <p class="alert alert-{{ $variant }}" role="status">{{ session($notice) }}</p>
            @endif
        @endforeach
        @if ($undo = session('care_undo'))
            <form method="POST" action="{{ route('care-logs.undo', $undo) }}" class="mb-3">
                @csrf
                @method('DELETE')
                <button class="btn btn-outline-secondary btn-sm" type="submit">Undo care entry</button>
            </form>
        @endif
        @include('plants.custom-task-undo')

        @if ($duePlants->isEmpty())
            <div class="bloom-panel bloom-empty">
                @if ($isFirstPage)
                    <h2 class="h4">You’re all caught up</h2>
                    <p class="text-muted mb-0">No care tasks are due today.</p>
                @else
                    <h2 class="h4">No due tasks on this page</h2>
                @endif
            </div>
            @if ($isFirstPage && $nextCare)
                @php($nextTask = $nextCare['task'] ?? null)
                <section class="bloom-panel mt-3" aria-labelledby="upcoming-care">
                    <h2 class="h4" id="upcoming-care">Next scheduled care</h2>
                    <p class="text-break">
                        <a href="{{ route('plants', $nextCare['plant']) }}">{{ $nextCare['plant']->name }}</a>
                        · {{ $nextTask?->name ?? $nextCare['type']->label() }}
                        · <time datetime="{{ $nextCare['date']->toDateString() }}">{{ $nextCare['date']->format('M j, Y') }}</time>
                    </p>
                    <form method="POST" action="{{ $nextTask
                        ? route('plant-tasks.complete', [$nextCare['plant'], $nextTask])
                        : route('care-actions.early', [$nextCare['plant'], $nextCare['type']->value]) }}">
                        @csrf
                        <input type="hidden" name="scheduled_for" value="{{ $nextTask?->due_on ?? $nextCare['date']->toDateString() }}">
                        @if ($nextTask)
                            <input type="hidden" name="complete_early" value="1">
                        @endif
                        <button type="submit" class="btn btn-outline-secondary btn-sm">{{ $nextTask ? 'Complete early' : ($nextCare['type']->value === 'watering' ? 'Water early' : 'Fertilize early') }}</button>
                    </form>
                </section>
            @endif
        @else
            <ul class="list-unstyled d-grid gap-3">
                @foreach ($duePlants as $item)
                    <li class="bloom-panel">
                        <h2 class="h4 text-break"><a href="{{ route('plants', $item['plant']) }}">{{ $item['plant']->name }}</a></h2>
                        @if (!$item['plant']->isOwnedBy(auth()->user()) && $item['plant']->owner)
                            <p class="small text-muted text-break">Shared by {{ $item['plant']->owner->email }}</p>
                        @endif
                        <ul class="list-unstyled d-grid gap-2 mb-0">
                            @foreach ($item['actions'] as $action)
                                @php($customTask = $action['task'] ?? null)
                                <li class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                                    <div>
                                        <strong class="text-break">{{ $customTask?->name ?? $action['type']->label() }}</strong>
                                        <span class="badge rounded-pill text-bg-warning">
                                            {{ $action['dueToday'] ? 'Due today' : 'Overdue' }}
                                        </span>
                                        <div class="small text-muted">
                                            <time datetime="{{ $action['date']->toDateString() }}">{{ $action['date']->format('M j, Y') }}</time>
                                        </div>
                                    </div>
                                    <div class="d-flex flex-wrap gap-2">
                                        <form method="POST" action="{{ $customTask
                                        ? route('plant-tasks.complete', [$item['plant'], $customTask])
                                        : route($action['type']->actionRoute(), $item['plant']) }}">
                                        @csrf
                                        @if ($customTask)
                                            <input type="hidden" name="scheduled_for" value="{{ $customTask->due_on }}">
                                            <button class="btn btn-primary btn-sm" type="submit"
                                                    aria-label="Complete {{ $customTask->name }} for {{ $item['plant']->name }}">Complete</button>
                                        @else
                                            <button class="btn btn-primary btn-sm" type="submit"
                                                    aria-label="Mark {{ $item['plant']->name }} as {{ strtolower($action['type']->actionLabel()) }}">{{ $action['type']->actionLabel() }}</button>
                                        @endif
                                        </form>
                                        <form method="POST" action="{{ $customTask
                                            ? route('plant-tasks.snooze', [$item['plant'], $customTask])
                                            : route('care-actions.snooze', [$item['plant'], $action['type']->value]) }}">
                                            @csrf
                                            @if ($customTask)
                                                <input type="hidden" name="scheduled_for" value="{{ $customTask->due_on }}">
                                            @endif
                                            <button class="btn btn-outline-secondary btn-sm" type="submit">Snooze until tomorrow</button>
                                        </form>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                        @if ($item['hasMore'])
                            <a class="d-inline-block mt-2" href="{{ route('plants', $item['plant']) }}#custom-tasks">See all tasks for this plant</a>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
        @if (!$isFirstPage || $nextPage)
            <nav class="d-flex gap-2 mt-3" aria-label="Today pages">
                @if (!$isFirstPage)
                    <a class="btn btn-outline-secondary" href="{{ route('care.today') }}">Back to first page</a>
                @endif
                @if ($nextPage)
                    <a class="btn btn-outline-secondary" href="{{ route('care.today', ['after' => $nextPage]) }}">More due plants</a>
                @endif
            </nav>
        @endif
    </div>
@endsection
