@if ($taskUndo = session('custom_task_undo'))
    <form method="POST" action="{{ route('plant-tasks.undo', $taskUndo) }}" class="mb-3">
        @csrf
        @method('DELETE')
        <button class="btn btn-outline-secondary btn-sm" type="submit">Undo task completion</button>
    </form>
@endif
