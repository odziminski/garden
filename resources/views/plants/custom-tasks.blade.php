@php
    $gardenToday = $caretakingTime->toDateString();
    $taskFormErrors = $errors->customTask;
    $submittedName = $taskFormErrors->any() ? old('name') : null;
    $submittedDate = $taskFormErrors->any() ? old('due_on') : null;
    $submittedInterval = $taskFormErrors->any() ? old('repeat_days') : null;
@endphp

<section class="bloom-panel" id="custom-tasks" aria-labelledby="custom-tasks-title">
    <h2 class="h4" id="custom-tasks-title">Custom care tasks</h2>

    @if (session('custom_task_status'))
        <p class="alert alert-success" role="status">{{ session('custom_task_status') }}</p>
    @endif
    @if (session('custom_task_notice'))
        <p class="alert alert-info" role="status">{{ session('custom_task_notice') }}</p>
    @endif
    @include('plants.custom-task-undo')

    @error('scheduled_for')
        <p class="alert alert-danger" role="alert">{{ $message }}</p>
    @enderror

    @if ($canManage)
        <form class="mb-4" method="POST" action="{{ route('plant-tasks.store', $plant) }}">
            @csrf
            <div class="row g-3">
                <div class="col-lg-6">
                    <label class="form-label" for="plant-task-name">Task name</label>
                    <input class="form-control @error('name', 'customTask') is-invalid @enderror" id="plant-task-name"
                           type="text" name="name" maxlength="120" required
                           value="{{ is_string($submittedName) ? $submittedName : '' }}"
                           aria-describedby="plant-task-name-help @error('name', 'customTask') plant-task-name-error @enderror">
                    <p class="form-text mb-0" id="plant-task-name-help">Try repotting, rotating the pot, wiping leaves, or checking for pests.</p>
                    @error('name', 'customTask')
                        <p class="invalid-feedback" id="plant-task-name-error">{{ $message }}</p>
                    @enderror
                </div>
                <div class="col-sm-6 col-lg-3">
                    <label class="form-label" for="plant-task-due">First due date</label>
                    <input class="form-control @error('due_on', 'customTask') is-invalid @enderror" id="plant-task-due"
                           type="date" name="due_on" min="1000-01-01" max="9999-12-31" required
                           value="{{ is_string($submittedDate) ? $submittedDate : $gardenToday }}"
                           @error('due_on', 'customTask') aria-describedby="plant-task-date-error" @enderror>
                    @error('due_on', 'customTask')
                        <p class="invalid-feedback" id="plant-task-date-error">{{ $message }}</p>
                    @enderror
                </div>
                <div class="col-sm-6 col-lg-3">
                    <label class="form-label" for="plant-task-repeat">Repeat every (days)</label>
                    <input class="form-control @error('repeat_days', 'customTask') is-invalid @enderror" id="plant-task-repeat"
                           type="number" name="repeat_days" min="1" step="1"
                           value="{{ is_scalar($submittedInterval) ? $submittedInterval : '' }}"
                           aria-describedby="plant-task-repeat-help @error('repeat_days', 'customTask') plant-task-repeat-error @enderror">
                    <p class="form-text mb-0" id="plant-task-repeat-help">Leave blank for a one-time task.</p>
                    @error('repeat_days', 'customTask')
                        <p class="invalid-feedback" id="plant-task-repeat-error">{{ $message }}</p>
                    @enderror
                </div>
            </div>
            <button class="btn btn-primary mt-3" type="submit">Add task</button>
        </form>
    @endif

    @if ($customTasks->isEmpty())
        <p class="text-muted">No active tasks.</p>
    @else
        <ul class="list-group list-group-flush mb-3">
            @foreach ($customTasks as $task)
                <li id="custom-task-{{ $task->id }}" class="list-group-item px-0 d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div>
                        <strong class="text-break">{{ $task->name }}</strong>
                        <p class="small text-muted mb-0">
                            {{ $task->effectiveDueOn() < $gardenToday ? 'Overdue since' : 'Due' }}
                            <time datetime="{{ $task->effectiveDueOn() }}">{{ \Carbon\CarbonImmutable::parse($task->effectiveDueOn())->format('M j, Y') }}</time>
                            @if ($task->repeat_days !== null)
                                &middot; Every {{ $task->repeat_days }} {{ Str::plural('day', $task->repeat_days) }}
                            @endif
                        </p>
                        @if ($task->assignee_id && !$canManage)
                            <p class="small text-muted mb-0">{{ (int) $task->assignee_id === (int) auth()->id() ? 'Assigned to you' : 'Assigned' }}</p>
                        @endif
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        @if ($task->effectiveDueOn() <= $gardenToday)
                            <form method="POST" action="{{ route('plant-tasks.complete', [$plant, $task]) }}">
                                @csrf
                                <input type="hidden" name="scheduled_for" value="{{ $task->due_on }}">
                                <button class="btn btn-primary btn-sm" type="submit" aria-label="Complete {{ $task->name }}">Complete</button>
                            </form>
                        @endif
                        @if ($canManage)
                            <a class="btn btn-outline-secondary btn-sm" href="{{ route('plant-tasks.edit', [$plant, $task]) }}">Edit task</a>
                            @include('plants.task-assignment')
                            <form method="POST" action="{{ route('plant-tasks.archive', [$plant, $task]) }}">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-outline-secondary btn-sm" type="submit" aria-label="Archive {{ $task->name }}">Archive</button>
                            </form>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    @endif

    <h3 class="h6 mt-3">Task completion history</h3>
    @if ($customTaskCompletions->isEmpty())
        <p class="text-muted mb-0">{{ $customTaskCompletions->onFirstPage() ? 'No tasks completed yet.' : 'No completions on this page.' }}</p>
    @else
        <ol class="list-group list-group-flush">
            @foreach ($customTaskCompletions as $completion)
                <li class="list-group-item px-0">
                    <div class="d-flex flex-wrap justify-content-between gap-2">
                        <strong class="text-break">{{ $completion->plantTask->name }}</strong>
                        <time class="text-muted" datetime="{{ $completion->completed_on }}">
                            {{ \Carbon\CarbonImmutable::parse($completion->completed_on)->format('M j, Y') }}
                        </time>
                    </div>
                    @if ($completion->completedBy)
                        <p class="small text-muted text-break mb-0">Completed by {{ $completion->completedBy->email }}</p>
                    @endif
                </li>
            @endforeach
        </ol>
    @endif
    @if ($customTaskCompletions->hasPages())
        <nav class="d-flex gap-2 mt-3" aria-label="Task completion pages">
            @if ($customTaskCompletions->previousPageUrl())
                <a class="btn btn-outline-secondary btn-sm" href="{{ $customTaskCompletions->previousPageUrl() }}">Newer completions</a>
            @endif
            @if ($customTaskCompletions->nextPageUrl())
                <a class="btn btn-outline-secondary btn-sm" href="{{ $customTaskCompletions->nextPageUrl() }}">Older completions</a>
            @endif
        </nav>
    @endif
</section>
