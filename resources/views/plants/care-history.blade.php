@php
    $submittedCareDate = old('care_date');
    $submittedCareNote = $errors->care->any() ? old('note') : null;
@endphp

<section class="bloom-panel" id="care-history" aria-labelledby="care-history-title">
    <h2 class="h4" id="care-history-title">Care history</h2>

    @if (session('care_status'))
        <p class="alert alert-success" role="status">{{ session('care_status') }}</p>
    @endif
    @if ($errors->care->any())
        <ul class="alert alert-danger list-unstyled" role="alert">
            @foreach ($errors->care->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    <form class="mb-4" method="POST" action="{{ route('care-logs.store', $plant) }}">
        @csrf
        <div class="row g-3">
            <div class="col-sm-6">
                <label class="form-label" for="care-type">Care type</label>
                <select class="form-select" id="care-type" name="type" required>
                    @foreach (\App\Enums\CareType::cases() as $careType)
                        <option value="{{ $careType->value }}" @selected(old('type') === $careType->value)>{{ $careType->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-sm-6">
                <label class="form-label" for="care-date">Date</label>
                <input class="form-control" id="care-date" name="care_date" type="date" min="1970-01-02"
                       max="{{ $caretakingTime->toDateString() }}"
                       value="{{ is_string($submittedCareDate) ? $submittedCareDate : $caretakingTime->toDateString() }}" required>
            </div>
            <div class="col-12">
                <label class="form-label" for="care-note">Note (optional)</label>
                <textarea class="form-control" id="care-note" name="note" rows="2" maxlength="1000">{{ is_string($submittedCareNote) ? $submittedCareNote : '' }}</textarea>
            </div>
            @include('plants.feeding-fields', ['fieldPrefix' => 'care', 'feedingEntry' => null])
        </div>
        <button class="btn btn-primary mt-3" type="submit">Log care</button>
    </form>

    @if ($careLogs->isEmpty())
        <p class="text-muted mb-0">{{ $careLogs->onFirstPage() ? 'No care recorded yet.' : 'No entries on this page.' }}</p>
    @else
        <ol class="list-group list-group-flush">
            @foreach ($careLogs as $entry)
                <li class="list-group-item px-0">
                    <div class="d-flex flex-wrap justify-content-between gap-2">
                        <strong>{{ $entry->type->label() }}</strong>
                        <time class="text-muted" datetime="{{ $entry->performed_on }}">{{ \Carbon\Carbon::parse($entry->performed_on)->format('M j, Y') }}</time>
                    </div>
                    @if ($entry->performedBy)
                        <p class="small text-muted text-break mb-0">Recorded by {{ $entry->performedBy->email }}</p>
                    @endif
                    @if ($entry->note !== null)
                        <p class="text-break mb-0">{{ $entry->note }}</p>
                    @endif
                    @if ($entry->fertilizer || $entry->dose)
                        <p class="text-break mb-0">{{ implode(' · ', array_filter([$entry->fertilizer, $entry->dose])) }}</p>
                    @endif
                    @if ($entry->is_legacy)
                        <small class="text-muted">Imported from previous care records</small>
                    @endif
                    @if ($entry->voided_at)
                        <p class="small text-muted mb-0">Undone. Excluded from the care schedule.</p>
                    @else
                        @if ($entry->corrects_entry_id)
                            <p class="small text-muted mb-0">Corrected care entry</p>
                        @endif
                        @if ($entry->plantCheck && $entry->plantCheck->voided_at === null)
                            <a class="small" href="#plant-checks">See linked soil check</a>
                        @elseif ($entry->canBeCorrectedBy(auth()->user(), $plant))
                            <div class="d-flex flex-wrap gap-2 mt-1">
                                <a class="btn btn-outline-secondary btn-sm" href="{{ route('care-logs.edit', [$plant, $entry]) }}">Correct entry</a>
                                <form method="POST" action="{{ route('care-logs.undo', [$plant, $entry]) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-outline-secondary btn-sm" type="submit">Undo entry</button>
                                </form>
                            </div>
                        @endif
                    @endif
                </li>
            @endforeach
        </ol>
    @endif
    @if ($careLogs->hasPages())
        <nav class="d-flex gap-2 mt-3" aria-label="Care history pages">
            @if ($careLogs->isEmpty())
                <a class="btn btn-outline-secondary btn-sm" href="{{ $careLogs->url(1) }}">Newest entries</a>
            @elseif ($careLogs->previousPageUrl())
                <a class="btn btn-outline-secondary btn-sm" href="{{ $careLogs->previousPageUrl() }}">Newer entries</a>
            @endif
            @if ($careLogs->nextPageUrl())
                <a class="btn btn-outline-secondary btn-sm" href="{{ $careLogs->nextPageUrl() }}">Older entries</a>
            @endif
        </nav>
    @endif
</section>
