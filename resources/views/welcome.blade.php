@extends('layouts.app')


@section('content')

@if (!Auth::guest())
<div class="text-center">

        Number of your plants: {{$plants->count()}}</p>
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
                    <p> Last watered at: {{$plant->watered_at}}
                </div>
                <div class="card-read-more">
                    <p class="btn btn-link btn-block">
                        See More
                    </a>
                </div>
            </div>
        </div>
    </a>
        @endforeach

    </div>
</div>

<div class="text-center">
    <a href="{{ route('logout') }}">Logout</a>
</div>


@else
<a href="{{ route('login') }}">Login</a>
<a href="{{ route('register') }}">Register</a>
@endif

@endsection
