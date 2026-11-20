@if (session('plant_check_status'))
    <p class="alert alert-success" role="status">{{ session('plant_check_status') }}</p>
@endif
@if (session('plant_check_notice'))
    <p class="alert alert-info" role="status">{{ session('plant_check_notice') }}</p>
@endif
@foreach ([$errors->plantCheck, $errors->plantCheckSchedule] as $checkErrors)
    @foreach ($checkErrors->all() as $message)
        <p class="alert alert-danger" role="alert">{{ $message }}</p>
    @endforeach
@endforeach
@if ($checkUndo = session('plant_check_undo'))
    <form method="POST" action="{{ route('plant-checks.undo', $checkUndo) }}" class="mb-3">
        @csrf
        @method('DELETE')
        <button class="btn btn-outline-secondary btn-sm" type="submit">Undo soil check</button>
    </form>
@endif
@if ($skipUndo = session('plant_check_skip_undo'))
    <form method="POST" action="{{ route('plant-checks.undo-skip', $skipUndo[0]) }}" class="mb-3">
        @csrf
        @method('DELETE')
        <input type="hidden" name="schedule_version" value="{{ $skipUndo[1] }}">
        <button class="btn btn-outline-secondary btn-sm" type="submit">Undo skip</button>
    </form>
@endif
