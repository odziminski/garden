@extends('layouts.app')

@section('title', 'Create an account')

@section('content')
    <x-auth-panel heading="Start your garden" description="Create your Bloomify account.">
        <form method="POST" action="{{ route('register') }}">
            @csrf
            <div class="mb-3">
                <label class="form-label" for="email">Email address</label>
                <input id="email" type="email" class="form-control @error('email') is-invalid @enderror"
                       name="email" value="{{ old('email') }}" required autocomplete="email"
                       @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
                @error('email')
                    <div class="invalid-feedback" id="email-error">{{ $message }}</div>
                @enderror
            </div>
            <div class="mb-3">
                <label class="form-label" for="password">Password</label>
                <input id="password" type="password" class="form-control @error('password') is-invalid @enderror"
                       name="password" required autocomplete="new-password" minlength="8"
                       aria-describedby="password-help @error('password') password-error @enderror"
                       @error('password') aria-invalid="true" @enderror>
                <div class="form-text" id="password-help">Use at least 8 characters.</div>
                @error('password')
                    <div class="invalid-feedback" id="password-error">{{ $message }}</div>
                @enderror
            </div>
            <div class="mb-4">
                <label class="form-label" for="password-confirm">Confirm password</label>
                <input id="password-confirm" type="password" class="form-control" name="password_confirmation"
                       required autocomplete="new-password">
            </div>
            <button class="btn btn-primary w-100" type="submit">Create an account</button>
        </form>

        <p class="bloom-auth-links mb-0">Already registered? <a href="{{ route('login') }}">Log in</a></p>
    </x-auth-panel>
@endsection
