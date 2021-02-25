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
                    <img src={{$plant->avatar}}  />
                </a>
                <div class="card-content">
                    <h4 class="card-title">{{$plant->name}}</h4>
                    @if ($plant->need_watering || $plant->need_fertilizing )
                        <div class="text-danger">  
                    @else 
                        <div>
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


@else
<a href="{{ route('login') }}">Login</a>
<a href="{{ route('register') }}">Register</a>
@endif

@endsection
