@extends('layouts.app')

@section('title', 'Your garden')

@section('content')
    <div class="container py-5">
        <header class="bloom-page-heading d-flex flex-wrap align-items-end justify-content-between gap-3">
            <h1 class="mb-0">Your garden</h1>
            <a class="btn btn-outline-secondary" href="{{ route('care.today') }}">Today’s care</a>
        </header>

        @if (session('message'))
            <div class="alert alert-success" role="status">{{ session('message') }}</div>
        @endif

        @if ($plants->isEmpty())
            <div class="bloom-panel bloom-empty">
                <h2 class="h4">Your garden is empty</h2>
                <a class="btn btn-primary" href="{{ route('add-plant') }}">Add your first plant</a>
            </div>
        @else
            <div class="bloom-card-grid">
                @foreach ($plants as $plant)
                    <article class="card bloom-plant-card">
                        <a href="{{ route('plants', $plant) }}" aria-label="View {{ $plant->name }}">
                            <img src="{{ $plant->avatarUrl() }}" class="card-img-top" alt="{{ $plant->name }}">
                        </a>
                        <div class="card-body">
                            <h2 class="h5 card-title">
                                <a href="{{ route('plants', $plant) }}">{{ $plant->name }}</a>
                            </h2>
                            @if (!$plant->isOwnedBy(auth()->user()) && $plant->owner)
                                <p class="small text-muted text-break">Shared by {{ $plant->owner->email }}</p>
                            @endif
                            @include('plants.care-actions')
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </div>
@endsection
