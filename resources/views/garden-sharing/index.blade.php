@extends('layouts.app')

@section('title', 'Garden sharing')

@section('content')
    <div class="container d-grid gap-4 py-4" style="max-width: 48rem;">
        <div>
            <h1>Garden sharing</h1>
            <p class="mb-0">Invite someone to view all your plants and log care. Only you can manage your plants, care schedules, and photo journals.</p>
        </div>

        @if (session('sharing_status'))
            <div class="alert alert-success mb-0" role="status">{{ session('sharing_status') }}</div>
        @endif

        <section class="card bloom-panel">
            <div class="card-body">
                <h2 class="h4">Invite someone</h2>
                <p id="invite-help">Enter the exact email address shown on their Bloomify profile. You’ll get a link to share with them.</p>
                <form method="POST" action="{{ route('garden-sharing.invitations.store') }}">
                    @csrf
                    <label class="form-label" for="invite-email">Email address</label>
                    <input id="invite-email" @class(['form-control', 'is-invalid' => $errors->has('email')])
                           type="email" name="email" value="{{ old('email') }}" maxlength="255" required
                           aria-describedby="invite-help{{ $errors->has('email') ? ' invite-email-error' : '' }}"
                           @error('email') aria-invalid="true" @enderror>
                    @error('email')
                        <div class="invalid-feedback" id="invite-email-error" role="alert">{{ $message }}</div>
                    @enderror
                    <button class="btn btn-primary mt-3" type="submit">Create invitation link</button>
                </form>
            </div>
        </section>

        <section class="card bloom-panel">
            <div class="card-body">
                <h2 class="h4">Pending invitations</h2>
                @if ($invitations->isEmpty())
                    <p class="mb-0">You have no pending invitations.</p>
                @else
                    <ul class="list-group list-group-flush">
                        @foreach ($invitations as $invitation)
                            <li class="list-group-item d-flex flex-wrap gap-2 justify-content-between align-items-center px-0">
                                <div class="text-break">
                                    {{ $invitation->email }}
                                    <small class="d-block text-body-secondary">Expires {{ $invitation->expires_at->toFormattedDateString() }}</small>
                                </div>
                                <form method="POST" action="{{ route('garden-sharing.invitations.destroy', $invitation) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-outline-danger btn-sm" type="submit"
                                            aria-label="Revoke invitation for {{ $invitation->email }}">Revoke</button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </section>

        <section class="card bloom-panel">
            <div class="card-body">
                <h2 class="h4">People with access</h2>
                @if ($members->isEmpty())
                    <p class="mb-0">Your garden is private.</p>
                @else
                    <ul class="list-group list-group-flush">
                        @foreach ($members as $membership)
                            <li class="list-group-item d-flex flex-wrap gap-2 justify-content-between align-items-center px-0">
                                <span class="text-break">{{ $membership->member->email }}</span>
                                <form method="POST" action="{{ route('garden-sharing.memberships.destroy', $membership) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-outline-danger btn-sm" type="submit"
                                            aria-label="Remove access for {{ $membership->member->email }}">Remove access</button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </section>

        <section class="card bloom-panel">
            <div class="card-body">
                <h2 class="h4">Gardens shared with you</h2>
                @if ($sharedGardens->isEmpty())
                    <p class="mb-0">You haven’t joined any shared gardens.</p>
                @else
                    <p><a href="{{ route('browse') }}">View shared plants</a></p>
                    <ul class="list-group list-group-flush">
                        @foreach ($sharedGardens as $membership)
                            <li class="list-group-item d-flex flex-wrap gap-2 justify-content-between align-items-center px-0">
                                <span class="text-break">{{ $membership->owner->email }}</span>
                                <form method="POST" action="{{ route('garden-sharing.memberships.destroy', $membership) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-outline-danger btn-sm" type="submit"
                                            aria-label="Leave the garden shared by {{ $membership->owner->email }}">Leave garden</button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </section>
    </div>
@endsection
