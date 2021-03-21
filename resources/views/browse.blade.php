@extends('layouts.app')


@section('content')

@if (!Auth::guest())

<div class="container">
    <div class="row">
        @foreach ($plants as $plant)
            <div class="col m1-5 ">
            <div class="card">
                <a class="img-card" href="{{ URL::to('plants/' . $plant->id) }}">
                    <img src= "{{str_ireplace( 'https://', 'http://', $plant->avatar )}}"  /> </a>
                        @if ($plant->need_watering || $plant->need_fertilizing)
                         <span class="red-dot-notification">!</span>
                         @endif
                        <div class="card-content">
                        <h4 class="card-title">{{$plant->name}}</h4>
                        <div class="card-text">
                         Last watered: {{$plant->watered_at}} <br/>
                         Last fertilized: {{$plant->fertilized_at}}
                    </div>
                </div>
            </div>
        </div>
        @endforeach
    </div>
</div>


@else
<a href="{{ route('login') }}">Login</a>
<a href="{{ route('register') }}">Register</a>
@endif

@endsection
