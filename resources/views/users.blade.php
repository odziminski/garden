@extends('layouts.app')

@section('content')
    <div class="container d-grid gap-4 py-4" style="max-width: 36rem;">
        <div class="card bloom-panel">
            <div class="card-body">
                <h1 class="h4">Your profile</h1>
                <p>Joined {{ $user->created_at?->toFormattedDateString() }}</p>
                @unless ($user->hasVerifiedEmail())
                    <p><a href="{{ route('verification.notice') }}">Verify your email address</a> to receive reminders and share gardens.</p>
                @endunless

                @if (session('message'))
                    <div class="alert alert-success" role="status">{{ session('message') }}</div>
                @endif

                @if ($errors->any())
                    <ul class="alert alert-danger" role="alert">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                @endif

                <form method="POST" action="{{ route('editUserProfile') }}">
                    @csrf
                    @method('PUT')

                    <label class="form-label" for="profile-email">Email</label>
                    <input id="profile-email" class="form-control mb-3" type="email" name="email"
                           value="{{ old('email', $user->email) }}" autocomplete="email" required>

                    <label class="form-label" for="current-password">Current password</label>
                    <input id="current-password" class="form-control mb-3" type="password" name="current_password"
                           autocomplete="current-password" required>

                    <label class="form-label" for="new-password">New password (optional)</label>
                    <input id="new-password" class="form-control mb-3" type="password" name="password"
                           autocomplete="new-password" minlength="8">

                    <label class="form-label" for="password-confirmation">Confirm new password</label>
                    <input id="password-confirmation" class="form-control mb-3" type="password"
                           name="password_confirmation" autocomplete="new-password">

                    <button class="btn btn-primary" type="submit">Save profile</button>
                </form>
            </div>
        </div>

        @include('profile.reminders')
        @include('profile.push')
        <section class="card bloom-panel">
            <div class="card-body">
                <h2 class="h4">Your data</h2>
                <p>Download your plant and care records as JSON. Photo links require an active account.</p>
                <a class="btn btn-outline-secondary mb-3" href="{{ route('account.export') }}">Download data</a>
                <details>
                    <summary class="text-danger">Delete account</summary>
                    <p class="mt-3">Permanently delete your account, owned gardens, care history, and uploaded photos.</p>
                    <form method="POST" action="{{ route('account.destroy') }}">
                        @csrf
                        @method('DELETE')
                        <label for="delete-password" class="form-label">Current password</label>
                        <input id="delete-password" name="current_password" type="password" autocomplete="current-password" class="form-control mb-3" required>
                        <button type="submit" class="btn btn-outline-danger">Permanently delete account</button>
                    </form>
                </details>
            </div>
        </section>
    </div>
@endsection
