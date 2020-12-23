@extends('layouts.app')


@section('content')

@if (!Auth::guest())
<div class="text-center">
<p> Plants total: {{$plants->count()}} </p>
</div>

<div class="container">
    <div class="row">
        
        @foreach ($plants as $plant)
        <div class="col-xs-12 col-sm-3">
            <div class="card">
                <a class="img-card" href="{{ URL::to('plants/' . $plant->id) }}">
                    <img src="{{ asset('images/'. $plant->avatar) }}" />
                </a>
                <div class="card-content">
                    <h4 class="card-title">
                        {{$plant->name}}
                    </h4>
                    @if ($plant->need_watering)
                    <div class="text-danger">
                        <p> i need watering!</p>
                    @else 
                    <div class="text-successs">
                        @endif 
                        Last watered: {{$plant->watered_at}} <br/>
                        Last fertilized:{{$plant->fertilized_at}} 
                    </div>
                   
                </div>

                </a>
            </div>
        </div>
        </a>
        @endforeach

    </div>
</div>

<div class="text-center">
    <a href="{{ route('add-plant') }}" type = "button" class = "btn btn-success btn-lg"> Add a plant </a> <br>
    <a href="{{ route('logout') }}" type = "button" class = "btn btn-danger btn-lg"> Logout </a>
</div>


@else
<a href="{{ route('login') }}">Login</a>
<a href="{{ route('register') }}">Register</a>
@endif

@endsection
