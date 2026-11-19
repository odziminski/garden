@extends('layouts.app')

@section('content')
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <img src="{{ $plant->avatarUrl() }}" class="card-img-top" alt="{{ $plant->name }}">
                    <div class="card-body">
                        <h5 class="card-title">{{ $plant->name }}</h5>
                        @if ($plant->plantData)
                            <h6 class="card-subtitle text-muted">
                                {{ $plant->plantData->plant_name }}
                                @if ($plant->plantData->common_name)
                                    ({{ $plant->plantData->common_name }})
                                @endif
                            </h6>
                            @if ($plant->plantData->wikipedia_description)
                                <p>{{ $plant->plantData->wikipedia_description }}</p>
                            @endif
                            @if (parse_url($plant->plantData->wikipedia_url, PHP_URL_SCHEME) === 'https')
                                <a href="{{ $plant->plantData->wikipedia_url }}" target="_blank"
                                   rel="noopener noreferrer">Read more</a>
                            @endif
                            <h6 class="mt-3">Taxonomy</h6>
                            <p>
                                {{ implode(' ➤ ', array_filter($plant->plantData->only([
                                    'taxonomy_kingdom', 'taxonomy_phylum', 'taxonomy_class', 'taxonomy_order',
                                    'taxonomy_family', 'taxonomy_genus', 'plant_name',
                                ]))) }}
                            </p>
                        @endif

                        <h6 class="mt-3">Watering</h6>
                        <div class="progress" role="progressbar" aria-label="Time until watering"
                             aria-valuenow="{{ $wateringPercentage }}" aria-valuemin="0" aria-valuemax="100">
                            <div @class(['progress-bar', 'bg-danger' => $wateringPercentage === 100])
                                 style="width: {{ $wateringPercentage }}%"></div>
                        </div>
                        <h6 class="mt-3">Fertilizing</h6>
                        <div class="progress" role="progressbar" aria-label="Time until fertilizing"
                             aria-valuenow="{{ $fertilizingPercentage }}" aria-valuemin="0" aria-valuemax="100">
                            <div @class(['progress-bar', 'bg-danger' => $fertilizingPercentage === 100])
                                 style="width: {{ $fertilizingPercentage }}%"></div>
                        </div>

                        <div class="mt-3">
                            <a class="btn" href="{{ route('displayEditPlant', $plant) }}">Edit</a>
                            <button class="btn" type="button" data-bs-toggle="modal"
                                    data-bs-target="#delete-plant-modal">Delete</button>
                        </div>
                    </div>

                    <div class="card-footer text-muted">
                        @include('plants.care-actions')
                    </div>
                </div>
            </div>
        </div>
    </div>

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
@endsection
