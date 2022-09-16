@extends('layouts.app')


@section('content')

    @auth
        <div class="container mx-auto mt-4">
            <div class="row">
                @foreach ($plants as $plant)
                    <div class="col-md-3">
                        <div class="card" style="width: 14rem;">
                            <a href="{{ URL::to('plants/' . $plant->id) }}">
                                <img src="{{str_ireplace( 'https://', 'http://', $plant->avatar )}}"
                                     class="card-img-top"/> </a>
                            <div class="card-body">
                                <h5 class="card-title">{{$plant->name}}</h5>
                                <hr/>
                                {{--                                @if (isset($plant->plantData->plant_name)) <h6 class="card-subtitle mb-2 text-muted">--}}
                                {{--                                        {{$plant->plantData->plant_name}}--}}
                                {{--                               </h6>--}}
                                {{--                                @endif--}}
                                {{--                                <hr />--}}

                                {{--                                <p class="card-text">--}}
                                {{--                                    @if ($plant->watered_at === $plant->fertilized_at)--}}
                                {{--                                        Last time you took care of it: {{$plant->watered_at}} </p>--}}
                                {{--                                    @else--}}
                                {{--                                    Last time watered: {{$plant->watered_at}} <br/>--}}
                                {{--                                    Last time fertilized: {{$plant->fertilized_at}}</p>--}}
                                {{--                                @endif--}}
                                <a href="#" class="btn ">Watered</a>
                                <a href="#" class="btn "> Fertilized</a>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

    @endauth

    @guest
        <a href="{{ route('login') }}">Login</a>
        <a href="{{ route('register') }}">Register</a>
    @endguest

@endsection
