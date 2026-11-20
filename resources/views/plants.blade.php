@extends('layouts.app')

@section('title', $plant->name)

@section('content')
    <div class="container py-5">
        <header class="bloom-page-heading">
            <a class="d-inline-block mb-2" href="{{ route('browse') }}">Your garden</a>
            <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
                <h1 class="mb-0">{{ $plant->name }}</h1>
                @if ($canManage)
                    <div class="d-flex flex-wrap gap-2">
                        <a class="btn btn-outline-secondary" href="{{ route('monthly-report.index', ['plant' => $plant->id]) }}">Monthly report</a>
                        <a class="btn btn-outline-secondary" href="{{ route('displayEditPlant', $plant) }}">Edit plant</a>
                        <button class="btn btn-outline-danger" type="button" data-bs-toggle="modal"
                                data-bs-target="#delete-plant-modal">Delete</button>
                    </div>
                @elseif ($plant->owner)
                    <p class="text-muted text-break mb-0">Shared by {{ $plant->owner->email }}</p>
                @endif
            </div>
        </header>

        <div class="row g-4">
            <aside class="col-lg-4">
                <div class="card bloom-plant-card mb-4">
                    <img src="{{ $plant->avatarUrl() }}" class="card-img-top" alt="{{ $plant->name }}">
                    @if ($plant->species_name || $plant->room || $plant->care_instructions)
                        <div class="card-body">
                            @if ($plant->species_name)
                                <p class="fw-semibold mb-1">{{ $plant->species_name }}</p>
                            @endif
                            @if ($plant->room)
                                <p class="text-muted mb-1">{{ $plant->room }}</p>
                            @endif
                            @if ($plant->care_instructions)
                                <p class="mb-0 text-break">{{ $plant->care_instructions }}</p>
                            @endif
                        </div>
                    @endif
                    @if ($identificationStatus === 'pending')
                        <p class="card-body mb-0 text-muted">Plant identification is pending.</p>
                    @elseif ($identificationStatus === 'unavailable')
                        <p class="card-body mb-0 text-muted">Plant identification is unavailable.</p>
                    @elseif ($identificationStatus === 'no_match')
                        <p class="card-body mb-0 text-muted">No plant match was found for this cover photo.</p>
                    @endif
                    @if ($plant->plantData)
                        <div class="card-body">
                            @if (in_array($identificationStatus, ['pending', 'unavailable', 'no_match'], true))
                                <p class="small text-muted mb-1">Previous identification</p>
                            @endif
                            <h2 class="h5">
                                {{ $plant->plantData->plant_name }}
                                @if ($plant->plantData->common_name)
                                    ({{ $plant->plantData->common_name }})
                                @endif
                            </h2>
                            @if ($plant->plantData->wikipedia_description)
                                <p>{{ $plant->plantData->wikipedia_description }}</p>
                            @endif
                            @if (parse_url($plant->plantData->wikipedia_url ?? '', PHP_URL_SCHEME) === 'https')
                                <a href="{{ $plant->plantData->wikipedia_url }}" target="_blank"
                                   rel="noopener noreferrer">Read more</a>
                            @endif
                            <h3 class="h6 mt-3">Taxonomy</h3>
                            <p class="mb-0">
                                {{ implode(' ➤ ', array_filter($plant->plantData->only([
                                    'taxonomy_kingdom', 'taxonomy_phylum', 'taxonomy_class', 'taxonomy_order',
                                    'taxonomy_family', 'taxonomy_genus', 'plant_name',
                                ]))) }}
                            </p>
                        </div>
                    @endif
                </div>

                @if ($nextWatering || $nextFertilizing)
                    <section class="bloom-panel" aria-labelledby="plant-care-schedule">
                        <h2 class="h4" id="plant-care-schedule">Care schedule</h2>
                        @include('plants.care-actions')
                    </section>
                @endif
            </aside>

            <div class="col-lg-8">
                <div class="d-grid gap-4">
                    @include('plants.check-history')
                    @include('plants.custom-tasks')
                    @if ($canObserve)
                        @include('plants.observations')
                    @endif
                    @include('plants.care-history')
                    @if ($canManage)
                        @include('plants.photo-journal')
                    @endif
                </div>
            </div>
        </div>
    </div>

    @if ($canManage)
        <div class="modal fade" id="delete-plant-modal" tabindex="-1" aria-labelledby="delete-plant-title"
             aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="delete-plant-title">Delete {{ $plant->name }}?</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <form method="POST" action="{{ route('deletePlant', $plant) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger">Delete</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection
