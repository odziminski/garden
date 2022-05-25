@extends('layouts.app')


@section('content')

    @auth
        @if (count($plants) == 0)
            <div class="no_plants_added_yet">
                <h1>No plants added yet 😔</h1>
                <a href="{{route('add-plant')}}"><h2>add your first plant</h2></a>
            </div>
        @else
            <div class="parent">
                @foreach ($plants as $plant)

                    <div class="card">
                        <a href="{{ URL::to('plants/' . $plant->id) }}">
                            <img src="{{str_ireplace( 'https://', 'http://', $plant->avatar )}}" class="img-card"/> </a>
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
        @endif

    @endauth

    @guest
        <a href="{{ route('login') }}">Login</a>
        <a href="{{ route('register') }}">Register</a>
    @endguest

@endsection
