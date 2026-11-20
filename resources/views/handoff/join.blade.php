@extends('layouts.app')

@section('title', 'Accept a vacation handoff')

@section('content')
    <div class="container py-5" style="max-width: 42rem;">
        <section class="bloom-panel">
            <h1 class="h4">Accept a vacation handoff</h1>
            <p><strong class="text-break">{{ $handoff->owner->email }}</strong> invited you to help care for their plants.</p>
            <p>{{ $handoff->dateRange() }} <small class="text-muted">({{ $handoff->timezone }})</small></p>
            <p>You can see the selected plants and their instructions, check past care, and record the care you provide during these dates.</p>
            <form method="POST" action="{{ route('handoff.invitation.accept', $token) }}">
                @csrf
                <button type="submit" class="btn btn-primary">Accept handoff</button>
                <a class="btn btn-link" href="{{ route('handoff.index') }}">Back to vacation handoff</a>
            </form>
        </section>
    </div>
@endsection
