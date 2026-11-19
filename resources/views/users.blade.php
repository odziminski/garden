@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="card mx-auto" style="max-width: 32rem;">
            <div class="card-body">
                <h1 class="h4">Your profile</h1>
                <p>Joined {{ $user->created_at?->toFormattedDateString() }}</p>

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

                    <button class="btn" type="submit">Save profile</button>
                </form>
            </div>
        </div>
    </div>
@endsection
