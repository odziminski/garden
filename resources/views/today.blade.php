@extends('layouts.app')

@section('title', 'Today')

@section('content')
    <div class="container py-5">
        <header class="bloom-page-heading d-flex flex-wrap align-items-end justify-content-between gap-3">
            <div>
                <h1>Today</h1>
                <p class="text-muted mb-0"><time datetime="{{ $today->toDateString() }}">{{ $today->format('l, F j, Y') }}</time></p>
            </div>
            <a class="btn btn-outline-secondary" href="{{ route('browse') }}">Browse plants</a>
        </header>

        @if ($duePlants->isEmpty())
            <div class="bloom-panel bloom-empty">
                <h2 class="h4">You’re all caught up</h2>
                <p class="text-muted mb-0">No watering or fertilizing is due today.</p>
            </div>
        @else
            <ul class="list-unstyled d-grid gap-3">
                @foreach ($duePlants as $item)
                    <li class="bloom-panel">
                        <h2 class="h4 text-break"><a href="{{ route('plants', $item['plant']) }}">{{ $item['plant']->name }}</a></h2>
                        @if (!$item['plant']->isOwnedBy(auth()->user()) && $item['plant']->owner)
                            <p class="small text-muted text-break">Shared by {{ $item['plant']->owner->email }}</p>
                        @endif
                        <ul class="list-unstyled d-grid gap-2 mb-0">
                            @foreach ($item['actions'] as $action)
                                <li class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                                    <div>
                                        <strong>{{ $action['type']->label() }}</strong>
                                        <span class="badge rounded-pill text-bg-warning">
                                            {{ $action['dueToday'] ? 'Due today' : 'Overdue' }}
                                        </span>
                                        <div class="small text-muted">
                                            <time datetime="{{ $action['date']->toDateString() }}">{{ $action['date']->format('M j, Y') }}</time>
                                        </div>
                                    </div>
                                    <form method="POST" action="{{ route($action['type']->actionRoute(), $item['plant']) }}">
                                        @csrf
                                        <button class="btn btn-primary btn-sm" type="submit"
                                                aria-label="Mark {{ $item['plant']->name }} as {{ strtolower($action['type']->actionLabel()) }}">{{ $action['type']->actionLabel() }}</button>
                                    </form>
                                </li>
                            @endforeach
                        </ul>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
@endsection
