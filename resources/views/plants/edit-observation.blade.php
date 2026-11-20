@extends('layouts.app')

@section('title', 'Correct observation')

@section('content')
    @php
        $field = fn ($name, $default = '') => is_string(old($name, $default)) ? old($name, $default) : '';
        $repotting = \App\Enums\PlantObservationCategory::Repotting->value;
    @endphp
    <div class="container py-5">
        <header class="bloom-page-heading">
            <a href="{{ route('plants', $plant) }}#observations">{{ $plant->name }}</a>
            <h1>Correct observation</h1>
        </header>
        <form class="bloom-panel" method="POST" action="{{ route('plants.observations.update', [$plant, $entry]) }}">
            @csrf
            @method('PATCH')
            @if ($errors->plantObservation->any())
                <ul class="alert alert-danger list-unstyled" role="alert">
                    @foreach ($errors->plantObservation->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            @endif
            <div class="row g-3 mb-3">
                <div class="col-sm-6">
                    <label class="form-label" for="entry-category">Category</label>
                    <select class="form-select" id="entry-category" name="category" required>
                        @foreach (\App\Enums\PlantObservationCategory::cases() as $category)
                            <option value="{{ $category->value }}" @selected($field('category', $entry->category->value) === $category->value)>{{ $category->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-6">
                    <label class="form-label" for="entry-date">Observation date</label>
                    <input class="form-control" id="entry-date" type="date" name="observed_on"
                           min="1000-01-01" max="{{ now($plant->careTimezone())->toDateString() }}"
                           value="{{ $field('observed_on', $entry->observed_on) }}" required>
                </div>
                <div class="col-12">
                    <label class="form-label" for="entry-notes">Notes</label>
                    <textarea class="form-control" id="entry-notes" name="notes" rows="3" maxlength="1000" required>{{ $field('notes', $entry->notes) }}</textarea>
                </div>
                <div class="col-12">
                    <details @if ($field('category', $entry->category->value) === $repotting || $errors->plantObservation->hasAny(['container_size', 'substrate'])) open @endif>
                        <summary>Repotting details</summary>
                        <div class="row g-3 mt-1">
                            <div class="col-sm-6">
                                <label class="form-label" for="entry-container">Container size</label>
                                <input class="form-control" id="entry-container" name="container_size" maxlength="100"
                                       value="{{ $field('container_size', $entry->container_size) }}">
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label" for="entry-substrate">Substrate</label>
                                <input class="form-control" id="entry-substrate" name="substrate" maxlength="500"
                                       value="{{ $field('substrate', $entry->substrate) }}">
                            </div>
                        </div>
                    </details>
                </div>
            </div>
            <button class="btn btn-primary" type="submit">Save observation</button>
        </form>
    </div>
@endsection
