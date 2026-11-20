<form method="POST" action="{{ route('plant-tasks.assignment', [$plant, $task]) }}" class="d-flex align-items-end gap-2 flex-wrap">
    @csrf
    @method('PUT')
    <div>
        <label for="task-assignee-{{ $task->id }}" class="form-label small mb-1">Assigned to</label>
        <select class="form-select form-select-sm" name="assignee_id" id="task-assignee-{{ $task->id }}">
            <option value="">Anyone with access</option>
            <option value="{{ auth()->id() }}" @selected($task->assignee_id === auth()->id())>Me</option>
            @foreach ($assignableMembers as $member)
                <option value="{{ $member->id }}" @selected($task->assignee_id === $member->id)>{{ $member->email }}</option>
            @endforeach
        </select>
    </div>
    <button type="submit" class="btn btn-sm btn-outline-secondary" aria-label="Save assignment for {{ $task->name }}">Assign</button>
</form>
