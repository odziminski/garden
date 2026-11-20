@extends('layouts.app')

@section('title', 'Your garden')

@section('content')
    <div class="container py-5">
        <header class="bloom-page-heading d-flex flex-wrap align-items-end justify-content-between gap-3">
            <h1 class="mb-0">Your garden</h1>
            <div class="d-flex flex-wrap gap-2">
                <a class="btn btn-outline-secondary" href="{{ route('monthly-report.index') }}">Monthly report</a>
                <a class="btn btn-outline-secondary" href="{{ route('care.today') }}">Today’s care</a>
            </div>
        </header>

        @if (session('message'))
            <div class="alert alert-success" role="status">{{ session('message') }}</div>
        @endif

        @if ($plants->isEmpty())
            <div class="bloom-panel bloom-empty">
                @if ($plants->onFirstPage())
                    <h2 class="h4">Your garden is empty</h2>
                    <a class="btn btn-primary" href="{{ route('add-plant') }}">Add your first plant</a>
                @else
                    <h2 class="h4">No plants on this page</h2>
                    <a class="btn btn-outline-secondary" href="{{ $plants->url(1) }}">Back to first page</a>
                @endif
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
        @if ($plants->isNotEmpty() && $plants->hasPages())
            <nav class="d-flex gap-2 mt-4" aria-label="Garden pages">
                @if ($plants->previousPageUrl())
                    <a class="btn btn-outline-secondary" href="{{ $plants->previousPageUrl() }}">Previous plants</a>
                @endif
                @if ($plants->nextPageUrl())
                    <a class="btn btn-outline-secondary" href="{{ $plants->nextPageUrl() }}">More plants</a>
                @endif
            </nav>
        @endif
    </div>
@endsection
