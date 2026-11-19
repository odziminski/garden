@extends('layouts.app')

@section('title', 'Reset your password')

@section('content')
    <x-auth-panel heading="Forgot your password?" description="Enter your email address and we’ll send you a link to reset your password.">
        @if (session('status'))
            <div class="alert alert-success" role="status">{{ session('status') }}</div>
        @endif

        <form method="POST" action="{{ route('password.email') }}">
            @csrf
            <div class="mb-4">
                <label class="form-label" for="email">Email address</label>
                <input id="email" type="email" class="form-control @error('email') is-invalid @enderror"
                       name="email" value="{{ old('email') }}" required autocomplete="email"
                       @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
                @error('email')
                    <div class="invalid-feedback" id="email-error">{{ $message }}</div>
                @enderror
            </div>
            <button class="btn btn-primary w-100" type="submit">Send reset link</button>
        </form>

        <p class="bloom-auth-links mb-0"><a href="{{ route('login') }}">Back to login</a></p>
    </x-auth-panel>
@endsection
