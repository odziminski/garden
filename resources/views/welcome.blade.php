@extends('layouts.app')


@section('content')

@if (!Auth::guest())

<div class="text-center">
</div>

<div class="container">
    <div class="row">
        
        

    </div>
</div>

<div class="text-center">
    <a href="{{ route('add-plant') }}" type = "button" class = "btn btn-success btn-lg"> Add a plant </a> <br>
</div>


@else
<a href="{{ route('login') }}">Login</a>
<a href="{{ route('register') }}">Register</a>
@endif

@endsection
