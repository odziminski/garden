@extends('layouts.app')

@section('title', 'Monthly report')

@section('content')
    <div class="container">
        <header class="bloom-page-heading">
            <h1>Monthly report</h1>
            <p class="text-muted mb-0">Review care and journal photos for a plant month by month.</p>
        </header>

        @if ($errors->any())
            <div class="alert alert-danger" role="alert">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($plant === null)
            <section class="bloom-panel bloom-empty">
                <h2 class="h4">Your garden is empty</h2>
                <a class="btn btn-primary" href="{{ route('add-plant') }}">Add your first plant</a>
            </section>
        @else
            <form class="bloom-panel mb-4" method="GET" action="{{ route('monthly-report.index') }}" aria-label="Report filters">
                <div class="row g-3 align-items-end">
                    <div class="col-12 col-sm">
                        <label class="form-label" for="report-plant">Plant</label>
                        <select class="form-select" id="report-plant" name="plant">
                            @foreach ($plants as $option)
                                <option value="{{ $option->id }}" @selected($option->id === $plant->id)>{{ $option->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-sm">
                        <label class="form-label" for="report-month">Month</label>
                        <input class="form-control" id="report-month" type="month" name="month"
                               value="{{ $month->format('Y-m') }}" placeholder="YYYY-MM" required>
                    </div>
                    <div class="col-12 col-sm-auto">
                        <button class="btn btn-primary" type="submit">View report</button>
                    </div>
                </div>
            </form>

            <header class="mb-3">
                <h2 class="h3 text-break mb-1"><a href="{{ route('plants', $plant) }}">{{ $plant->name }}</a></h2>
                <p class="text-muted mb-0"><time datetime="{{ $month->format('Y-m') }}">{{ $month->format('F Y') }}</time></p>
            </header>

            <div class="row g-4 align-items-start">
                <div class="col-12 col-lg-5">
                    <section class="bloom-panel" aria-labelledby="watering-heading">
                        <h3 id="watering-heading" class="h4">Days between waterings</h3>
                        @if ($report['intervals'] === [])
                            <p class="mb-0 text-muted">{{ $report['hasWatering'] ? 'No interval can be calculated from one watering date.' : 'No watering dates in this month.' }}</p>
                        @else
                            <p class="text-muted small">Watering logs on the same day count once. The first interval can start in an earlier month.</p>
                            <ul class="list-unstyled d-grid gap-3 mb-0" role="list">
                                @foreach ($report['intervals'] as $interval)
                                    <li>
                                        <div class="d-flex flex-wrap align-items-baseline justify-content-between column-gap-3">
                                            <span class="small">
                                                <time datetime="{{ $interval['from'] }}">{{ \Carbon\CarbonImmutable::parse($interval['from'])->format('M j, Y') }}</time>
                                                to
                                                <time datetime="{{ $interval['to'] }}">{{ \Carbon\CarbonImmutable::parse($interval['to'])->format('M j, Y') }}</time>
                                            </span>
                                            <strong>{{ $interval['days'] }} {{ Str::plural('day', $interval['days']) }}</strong>
                                        </div>
                                        <div class="progress mt-1" style="height: .5rem" aria-hidden="true">
                                            <div class="progress-bar" style="width: {{ $interval['days'] / $report['maxInterval'] * 100 }}%"></div>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </section>
                </div>

                <div class="col-12 col-lg-7">
                    <section class="bloom-panel" aria-labelledby="timeline-heading">
                        <h3 id="timeline-heading" class="h4">Care and photo timeline</h3>
                        @if ($report['timeline']->isEmpty())
                            <p class="mb-0 text-muted">No care logs or photos for {{ $month->format('F Y') }}.</p>
                        @else
                            <ol class="list-unstyled mb-0" role="list">
                                @foreach ($report['timeline'] as $entry)
                                    <li @class(['border-start border-2 ps-3', 'pt-2' => $loop->first, 'pb-3' => ! $loop->last])>
                                        <div class="d-flex flex-wrap align-items-baseline column-gap-3">
                                            <h4 class="h6 mb-0">{{ $entry['label'] }}</h4>
                                            <time class="small text-muted" datetime="{{ $entry['date']->toDateString() }}">{{ $entry['date']->format('M j, Y') }}</time>
                                            @if ($entry['legacy'])
                                                <span class="small text-muted">Imported history</span>
                                            @endif
                                        </div>
                                        @if ($entry['photoId'] !== null)
                                            <div class="row mt-2">
                                                <div class="col-12 col-sm-8">
                                                    <div class="ratio ratio-4x3">
                                                        <img class="rounded object-fit-contain" loading="lazy"
                                                             src="{{ route('plants.photo-journal.show', [$plant, $entry['photoId']]) }}"
                                                             alt="{{ $plant->name }} journal photo from {{ $entry['date']->format('M j, Y') }}">
                                                    </div>
                                                </div>
                                            </div>
                                        @endif
                                        @if ($entry['note'] !== null && $entry['note'] !== '')
                                            <p class="mt-1 mb-0 text-break">{{ $entry['note'] }}</p>
                                        @endif
                                    </li>
                                @endforeach
                            </ol>
                        @endif
                        @if ($report['timeline']->hasPages())
                            <nav class="d-flex gap-2 mt-3" aria-label="Timeline pages">
                                @if ($report['timeline']->previousPageUrl())
                                    <a class="btn btn-outline-secondary btn-sm" href="{{ $report['timeline']->previousPageUrl() }}">Earlier entries</a>
                                @endif
                                @if ($report['timeline']->nextPageUrl())
                                    <a class="btn btn-outline-secondary btn-sm" href="{{ $report['timeline']->nextPageUrl() }}">Later entries</a>
                                @endif
                            </nav>
                        @endif
                    </section>
                </div>
            </div>
        @endif
    </div>
@endsection
