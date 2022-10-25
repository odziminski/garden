@extends('layouts.app')


@section('content')

    @auth
        <div class="container mx-auto mt-4">
            <div class="row">
                @if (count($plants) != 0)
                    @foreach ($plants as $plant)
                        <div class="col-md-3">
                            <div class="card" style="width: 14rem;">
                                <a href="{{ URL::to('plants/' . $plant->id) }}">
                                    <img src="{{str_ireplace( 'https://', 'http://', $plant->avatar )}}"
                                         class="card-img-top"/> </a>
                                <div class="card-body" id="card-body">
                                    <h5 class="card-title">{{$plant->name}}</h5>
                                    <hr/>
                                    @if($plant->needs->need_watering)
                                        <button class="btn " id="watering"> Watered</button>
                                    @else
                                        <h6 title="Next watering should be at: {{$nextWatering}}">Doesn't need watering
                                            yet
                                            ℹ️</h6>
                                    @endif
                                    @if($plant->needs->need_fertilizing)
                                        <button class="btn " id="fertilizing"> Fertilized</button>
                                    @else

                                        <h6 title="Next fertilizing should be at: {{$nextFertilizing}}">Doesn't need
                                            fertilizing yet ℹ️</h6>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
            </div>
        </div>
        <script src="//ajax.googleapis.com/ajax/libs/jquery/1.9.1/jquery.min.js"></script>

        <script type="text/javascript" charset="utf-8">
            $(document).on('click', '#fertilizing', function (event) {
                event.preventDefault();
                getMessage("{{ route('updateFertilizing',['id' => $plant->id]) }}", 'fertilized', '#fertilizing', '.nextFertilizing');

            });
            $(document).on('click', '#watering', function (event) {
                event.preventDefault();
                getMessage("{{ route('updateWatering',['id' => $plant->id]) }}", 'watered', '#watering', '.nextWatering');

            });
            let getMessage = function (route, word, buttonClass, spanClass) {
                $.ajax({
                    type: 'GET',
                    url: route,
                    success: function (data) {
                        $(spanClass).text(data);
                        $(buttonClass).fadeOut();

                        $('.alert').show()

                    }
                });
            }
        </script>
    @else
        <div class="no_plants_added_yet">
            <h1>No plants added yet 😔</h1>
            <a href="{{route('add-plant')}}"><h2>add your first plant</h2></a>
        </div>
    @endif

    @endauth

    @guest
        <a href="{{ route('login') }}">Login</a>
        <a href="{{ route('register') }}">Register</a>
    @endguest

@endsection
