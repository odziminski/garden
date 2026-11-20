@extends('layouts.app')

@section('title', 'Join a garden')

@section('content')
    <div class="container py-4" style="max-width: 42rem;">
        <section class="card bloom-panel">
            <div class="card-body">
                <h1 class="h4">Join a shared garden</h1>
                <p><strong class="text-break">{{ $invitation->owner->email }}</strong> has invited you to view their plants and log care.</p>
                <p>The garden owner and other members can see your account email on the care entries you log.</p>
                <form method="POST" action="{{ route('garden-sharing.invitations.accept', $token) }}">
                    @csrf
                    <button class="btn btn-primary" type="submit">Accept invitation</button>
                    <a class="btn btn-link" href="{{ route('browse') }}">Back to your garden</a>
                </form>
            </div>
        </section>
    </div>
@endsection
