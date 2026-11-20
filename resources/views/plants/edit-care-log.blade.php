@extends('layouts.app')

@section('title', 'Correct care entry')

@section('content')
    @php
        $oldDate = old('care_date', $entry->performed_on);
        $oldNote = old('note', $entry->note);
    @endphp
    <div class="container py-5">
        <header class="bloom-page-heading">
            <a href="{{ route('plants', $plant) }}#care-history">{{ $plant->name }}</a>
            <h1>Correct care entry</h1>
        </header>
        <form class="bloom-panel" method="POST" action="{{ route('care-logs.update', [$plant, $entry]) }}">
            @csrf
            @method('PATCH')
            <p class="text-muted">The original entry remains visible in the history. The replacement updates the care schedule.</p>
            @if ($errors->careCorrection->any())
                <ul class="alert alert-danger list-unstyled" role="alert">
                    @foreach ($errors->careCorrection->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            @endif
            <div class="row g-3 mb-3">
                <div class="col-sm-6">
                    <label class="form-label" for="entry-type">Care type</label>
                    <select class="form-select" id="entry-type" name="type" required>
                        @foreach (\App\Enums\CareType::cases() as $type)
                            <option value="{{ $type->value }}" @selected(old('type', $entry->type->value) === $type->value)>{{ $type->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-6">
                    <label class="form-label" for="entry-date">Date</label>
                    <input class="form-control" id="entry-date" type="date" name="care_date" min="1970-01-02"
                           max="{{ now($plant->careTimezone())->toDateString() }}"
                           value="{{ is_string($oldDate) ? $oldDate : $entry->performed_on }}" required>
                </div>
                <div class="col-12">
                    <label class="form-label" for="entry-note">Note (optional)</label>
                    <textarea class="form-control" id="entry-note" name="note" rows="3" maxlength="1000">{{ is_string($oldNote) ? $oldNote : '' }}</textarea>
                </div>
                @include('plants.feeding-fields', ['fieldPrefix' => 'edit-care', 'feedingEntry' => $entry])
            </div>
            <button class="btn btn-primary" type="submit">Save correction</button>
        </form>
    </div>
@endsection
