@extends('layouts.app')

@section('title', 'Confirm your password')

@section('content')
    <x-auth-panel heading="Confirm your password" description="Enter your password to continue.">
        <form method="POST" action="{{ route('password.confirm') }}">
            @csrf
            <div class="mb-4">
                <label class="form-label" for="password">Password</label>
                <input id="password" type="password" class="form-control @error('password') is-invalid @enderror"
                       name="password" required autocomplete="current-password"
                       @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
                @error('password')
                    <div class="invalid-feedback" id="password-error">{{ $message }}</div>
                @enderror
            </div>
            <button class="btn btn-primary w-100" type="submit">Confirm password</button>
        </form>

        @if (Route::has('password.request'))
            <p class="bloom-auth-links mb-0"><a href="{{ route('password.request') }}">Forgot password?</a></p>
        @endif
    </x-auth-panel>
@endsection
