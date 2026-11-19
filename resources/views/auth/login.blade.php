@extends('layouts.app')

@section('title', 'Log in')

@section('content')
    <x-auth-panel heading="Welcome back" description="Log in to tend to your plants.">
        <form method="POST" action="{{ route('login') }}">
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
                       name="password" required autocomplete="current-password"
                       @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
                @error('password')
                    <div class="invalid-feedback" id="password-error">{{ $message }}</div>
                @enderror
            </div>
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
                <div class="form-check mb-0">
                    <input class="form-check-input" type="checkbox" name="remember" id="remember" @checked(old('remember'))>
                    <label class="form-check-label" for="remember">Remember me</label>
                </div>
                <a href="{{ route('password.request') }}">Forgot password?</a>
            </div>
            <button class="btn btn-primary w-100" type="submit">Log in</button>
        </form>

        <p class="bloom-auth-links mb-0">New to Bloomify? <a href="{{ route('register') }}">Create an account</a></p>
    </x-auth-panel>
@endsection
