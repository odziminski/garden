@extends('layouts.app')

@section('title', 'Handoff invitation ready')

@section('content')
    <div class="container py-5" style="max-width: 42rem;">
        <section class="bloom-panel">
            <h1 class="h4">Handoff invitation ready</h1>
            <p>Share this link privately with <strong class="text-break">{{ $email }}</strong>. Only the Bloomify account with that verified email can accept it.</p>
            <label class="form-label" for="handoff-link">Invitation link</label>
            <input class="form-control mb-3" id="handoff-link" type="url" readonly value="{{ $inviteUrl }}">
            <a class="btn btn-outline-secondary" href="{{ route('handoff.index') }}">Back to vacation handoff</a>
        </section>
    </div>
@endsection
