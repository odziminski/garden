@extends('layouts.app')

@section('title', 'Garden invitation link')

@section('content')
    <div class="container py-4" style="max-width: 42rem;">
        <section class="card bloom-panel">
            <div class="card-body">
                <h1 class="h4">Your invitation link is ready</h1>
                <p>Copy the link and send it to <strong class="text-break">{{ $email }}</strong>. It expires in seven days and can be used once.</p>
                <label class="form-label" for="invite-link">Invitation link</label>
                <input id="invite-link" class="form-control mb-3" type="text" readonly value="{{ $inviteUrl }}"
                       aria-describedby="invite-link-help">
                <p id="invite-link-help">If you need a fresh link, create another invitation for the same email. This replaces the previous link.</p>
                <a class="btn btn-primary" href="{{ route('garden-sharing.index') }}">Back to garden sharing</a>
            </div>
        </section>
    </div>
@endsection
