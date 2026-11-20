@extends('layouts.app')

@section('title', 'Edit care task')

@section('content')
    <div class="container py-5">
        <header class="bloom-page-heading">
            <a href="{{ route('plants', $plant) }}#custom-tasks">{{ $plant->name }}</a>
            <h1>Edit care task</h1>
        </header>
        <form class="bloom-panel" method="POST" action="{{ route('plant-tasks.update', [$plant, $task]) }}">
            @csrf
            @method('PATCH')
            @if ($errors->customTask->any())
                <ul class="alert alert-danger list-unstyled" role="alert">
                    @foreach ($errors->customTask->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            @endif
            <div class="mb-3">
                <label class="form-label" for="task-name">Task name</label>
                <input class="form-control" id="task-name" name="name" type="text" maxlength="120" required
                       value="{{ is_string(old('name')) ? old('name') : $task->name }}">
            </div>
            <div class="mb-3">
                <label class="form-label" for="task-date">Due date</label>
                <input class="form-control" id="task-date" name="due_on" type="date" min="1000-01-01" max="9999-12-31" required
                       value="{{ is_string(old('due_on')) ? old('due_on') : $task->due_on }}">
            </div>
            <div class="mb-3">
                <label class="form-label" for="task-interval">Repeat every (days)</label>
                @php($interval = old('repeat_days', $task->repeat_days))
                <input class="form-control" id="task-interval" name="repeat_days" type="number" min="1" step="1"
                       value="{{ is_scalar($interval) ? $interval : '' }}" aria-describedby="repeat-help">
                <p class="form-text" id="repeat-help">Leave blank for a one-time task.</p>
            </div>
            <button class="btn btn-primary" type="submit">Save care task</button>
        </form>
    </div>
@endsection
