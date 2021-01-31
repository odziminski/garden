@extends('layouts.app')


@section('content')
    <div class="container">
@guest
<a href="{{ route('login') }}">Login</a>
<a href="{{ route('register') }}">Register</a>
@endguest

@endsection
