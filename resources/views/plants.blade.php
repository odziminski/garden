@extends('layouts.app')


@section('content')

    @if (!Auth::guest())
        <div class="container">
            <div class="card-single-plant _alignCenter">
                <h4>{{$plant->name}}</h4>

                
                    <h6 class="font-italic"> {{$plant->species}} </h6>

                <img src="{{str_ireplace( 'https://', 'http://', $plant->avatar )}}" alt="{{$plant->name}}"/>
                  
                <div class="_alignLeft m15">
                    <div class="bold-text">Stats</div>
                    <p>Overall health state is
                        @if ($lateForWatering + $lateForFertilizing >= 6)
                            bad
                        @else
                            good
                        @endif
                    </p>

                    <p>Next watering will be at {{$nextWatering}}</p>
                    <p>Next fertilizing will be at {{$nextFertilizing}}</p>
                </div>

                <div class="buttons">
                    @if ($plant->needs->need_watering)
                        <div class="needs">
                            <button class="button-white">
                                <a href="{{ route('updateWatering',['id' => $plant->id]) }}"
                                   style="text-decoration: none; color:#333C1C">HYDRATE</a>
                            </button>
                        </div>
                    @else
                        <button class="display-none">Water
                            <a href="#"></a>
                        </button>
                    @endif
                    @if ($plant->needs->need_fertilizing)
                        <div class="needs">
                            <button class="button-white">
                                <a href="{{ route('updateFertilizing',['id' => $plant->id]) }}"
                                   style="text-decoration: none; color:#333C1C">FERTILIZE</a>
                            </button>
                        </div>
                    @else
                        <button class="display-none">Fertilize
                            <a href="#"></a>
                        </button> <br/>
                    @endif

                    <br/>
                    <a href="{{ route('displayEditPlant',['id' => $plant->id]) }}">
                        <button>Edit</button>
                    </a>

                    <button onclick="openmodal('myModal')">Delete</button>
                </div>
                <div id="myModal" class="modalbox-modal ">
                    <div class="modalbox-modal-content">
                        <span class="-close" id="modalbox-close">✖</span>
                        <p>Are you sure you want to delete <span class="font-weight-bold">{{$plant->name}}</span>?<br/>
                            <a href="{{ route('deletePlant',['id' => $plant->id]) }}" class="_box _pink">Delete</a></p>

                    </div>
                </div>
            </div>
        </div>


    @endif
@endsection
