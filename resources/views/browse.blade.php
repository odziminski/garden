@extends('layouts.app')

@section('content')
    <div class="container mx-auto mt-4">
        @if (session('message'))
            <div class="alert alert-success" role="status">{{ session('message') }}</div>
        @endif

        <div class="row">
            @forelse ($plants as $plant)
                <div class="col-md-3">
                    <div class="card" style="width: 14rem;">
                        <a href="{{ route('plants', $plant) }}">
                            <img src="{{ $plant->avatarUrl() }}" class="card-img-top" alt="{{ $plant->name }}">
                        </a>
                        <div class="card-body">
                            <h5 class="card-title">{{ $plant->name }}</h5>
                            <hr>
                            @include('plants.care-actions')
                        </div>
                    </div>
                </div>
            @empty
                <div class="no_plants_added_yet">
                    <h1>No plants added yet 😔</h1>
                    <a href="{{ route('add-plant') }}">Add your first plant</a>
                </div>
            @endforelse
        </div>
    </div>
@endsection
