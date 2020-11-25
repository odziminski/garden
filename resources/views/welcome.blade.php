@extends('layouts.app')


@section('content')

@if (!Auth::guest())
<div class="text-center">
        Number of your plants: {{$plants->count()}}</p>
        @if (session('message'))
    <div class="alert alert-success" role="alert">
        {{ (session('message')) }}
      </div>
    @endif
</div>
<div class="container">
    <div class="row">
        @foreach ($plants as $plant)
        <div class="col-xs-12 col-sm-4">
            <div class="card">
                <a class="img-card" href="{{ URL::to('plants/' . $plant->id) }}">
                <img src="{{ asset('images/plant.png') }}" />
              </a>
                <div class="card-content">
                    <h4 class="card-title">
                            {{$plant->name}}
                    </h4>
                    <p> Last watered: {{$plant->watered_at}}</p>
                    <p> Last fertilized:{{$plant->fertilized_at}} </p>
                </div>
                
            </a>
            </div>
        </div>
    </a>
        @endforeach

    </div>
</div>

<div class="text-center">
    <a href="{{ route('add-plant') }}" type="button" class="btn btn-success btn-lg">Add a plant</a> <br>
    <a href="{{ route('logout') }}" type="button" class="btn btn-danger btn-lg">Logout</a>
</div>


@else
<a href="{{ route('login') }}">Login</a>
<a href="{{ route('register') }}">Register</a>
@endif

@endsection
