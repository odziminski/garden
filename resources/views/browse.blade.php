@extends('layouts.app')


@section('content')

@if (!Auth::guest())

    <div class="parent">
    @foreach ($plants as $plant)
            <div class="card">
                <a  href="{{ URL::to('plants/' . $plant->id) }}">
                    <img src= "{{str_ireplace( 'https://', 'http://', $plant->avatar )}}"  class="img-card" /> </a>
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
        @endforeach
    </div>
</div>


@else
<a href="{{ route('login') }}">Login</a>
<a href="{{ route('register') }}">Register</a>
@endif

@endsection
