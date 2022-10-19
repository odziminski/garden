<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/webcamjs/1.0.25/webcam.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/js/bootstrap.bundle.min.js"></script>


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
@extends('layouts.app')


@section('content')

    @if (!Auth::guest())
        {{--        <div class="container">--}}

        {{--            <div class="card-single-plant _alignCenter">--}}

        {{--                <h4>{{$plant->name}}</h4>--}}


        {{--                <h6 class="font-italic"> {{$plant->species}} </h6>--}}

        {{--                <img src="{{str_ireplace( 'https://', 'http://', $plant->avatar )}}" alt="{{$plant->name}}"/>--}}

        {{--                <div class="_alignLeft m15">--}}

        {{--                    <p>Next watering will be at <span class="nextWatering">{{$nextWatering}}</span></p>--}}
        {{--                    <p>Next fertilizing will be at <span class="nextFertilizing">{{$nextFertilizing}}</span></p>--}}
        {{--                </div>--}}

        {{--                <div class="alert alert-custom" hidden>--}}
        {{--                    <strong>Success!</strong>--}}
        {{--                </div>--}}
        {{--                <div class="buttons">--}}
        {{--                    @if ($plant->needs->need_watering)--}}
        {{--                        <div class="needs">--}}
        {{--                            <button class="button-white" id="watering" style="text-decoration: none; color:#333C1C">--}}
        {{--                                HYDRATE--}}
        {{--                            </button>--}}
        {{--                        </div>--}}
        {{--                    @else--}}
        {{--                        <button class="display-none">Water--}}
        {{--                            <a href="#"></a>--}}
        {{--                        </button>--}}
        {{--                    @endif--}}
        {{--                    @if ($plant->needs->need_fertilizing)--}}
        {{--                        <div class="needs">--}}
        {{--                            <button class="button-white" id="fertilizing" style="text-decoration: none; color:#333C1C">--}}
        {{--                                FERTILIZE--}}
        {{--                            </button>--}}
        {{--                        </div>--}}
        {{--                    @else--}}
        {{--                        <button class="display-none">Fertilize--}}
        {{--                            <a href="#"></a>--}}
        {{--                        </button> <br/>--}}
        {{--                    @endif--}}

        {{--                    <br/>--}}
        {{--                    <a href="{{ route('displayEditPlant',['id' => $plant->id]) }}">--}}
        {{--                        <button>Edit</button>--}}
        {{--                    </a>--}}

        {{--                    <button onclick="openmodal('myModal')">Delete</button>--}}
        {{--                </div>--}}
        {{--                <div id="myModal" class="modalbox-modal ">--}}
        {{--                    <div class="modalbox-modal-content">--}}
        {{--                        <span class="-close" id="modalbox-close">✖</span>--}}
        {{--                        <p>Are you sure you want to delete <span class="font-weight-bold">{{$plant->name}}</span>?<br/>--}}
        {{--                            <a href="{{ route('deletePlant',['id' => $plant->id]) }}" class="_box _pink">Delete</a>--}}
        {{--                        </p>--}}

        {{--                    </div>--}}
        {{--                </div>--}}
        {{--            </div>--}}
        {{--        </div>--}}
        <div class="container mt-5">
            <div class="row d-flex justify-content-center align-items-center">
                <div class="col-md-6">
                    <div class="card">
                        <a href="{{ URL::to('plants/' . $plant->id) }}">
                            <img src="{{str_ireplace( 'https://', 'http://', $plant->avatar )}}"
                                 class="card-img-top"/> </a>
                        <div class="card-body" id="card-body">
                            <h5 class="card-title">{{$plant->name}}</h5>


                            @if (isset($plant->plantData->plant_name))
                                <h6 class="card-subtitle mb-2 text-muted">

                                    {{$plant->plantData->plant_name}}, also called {{$plant->plantData->common_name}}


                                    <a href="javascript:void(0)" data-toggle="modal" data-target="#myModal"> <i
                                            class="fa fa-plus" aria-hidden="true"></i> </a>
                                    <!-- Modal -->
                                    <div class="modal fade" id="exampleModal" tabindex="-1"
                                         aria-labelledby="exampleModalLabel" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title" id="exampleModalLabel">Modal title</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"
                                                            aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    ...
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary"
                                                            data-bs-dismiss="modal">Close
                                                    </button>
                                                    <button type="button" class="btn btn-primary">Save changes</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    {{--                                    <?php--}}
                                    {{--                                    echo substr($plant->plantData->wikipedia_description, 0, strpos($plant->plantData->wikipedia_description, ' ', 200));--}}
                                    {{--                                    ?>--}}
                                    {{--                                    ... <a href="{{$plant->plantData->wikipedia_url}}" target="_blank">read more</a>--}}

                                </h6>
                            @endif
                            <hr/>

                            <p class="card-text">

                                <!-- Button trigger modal -->
                            @if ($plant->plantData)
                                <div>
                                    <h6>
                                        @if ($plant->plantData->wikipedia_description && $plant->plantData->wikipedia_url)

                                            <?php
                                            echo substr($plant->plantData->wikipedia_description, 0, strpos($plant->plantData->wikipedia_description, ' ', 100));
                                            ?>
                                            ... <a href="{{$plant->plantData->wikipedia_url}}" target="_blank">Read
                                                more</a>
                                            @endif
                                    </h6>
                                    <h5>Taxonomy</h5>
                                    <h6><em>{{$plant->plantData->taxonomy_kingdom}}
                                            ➤ {{$plant->plantData->taxonomy_phylum}}
                                            ➤ {{$plant->plantData->taxonomy_class}}
                                            ➤ {{$plant->plantData->taxonomy_order}}
                                            ➤ {{$plant->plantData->taxonomy_family}}
                                            ➤ {{$plant->plantData->taxonomy_genus}}
                                            ➤ {{$plant->plantData->plant_name}}</em></h6>


                                    {{--                            Next watering should be at : {{$nextWatering}} <br />--}}
                                    {{--                            Next fertilizing should be at : {{$nextFertilizing}}<br/>--}}
                                </div>
                                <a href="{{ route('displayEditPlant',['id' => $plant->id]) }}"><h6
                                        style="display: inline">Edit</h6></a>
                                <a href="#"><h6 style="display: inline">Delete</h6></a>
                            @else
                                <a href="{{ route('displayEditPlant',['id' => $plant->id]) }}"><h6
                                        style="display: inline">Edit</h6></a>
                                <a href="#"><h6 style="display: inline">Delete</h6></a>
                                <h6> Next watering should be at: {{$nextWatering}}</h6>
                                <h6> Next fertilizing should be at: {{$nextFertilizing}}</h6>
                            @endif

                        </div>
                        <div class="card-footer text-muted">
                            @if($plant->needs->need_watering)
                                <button class="btn " id="watering" style="width:49%"> Watered</button>
                            @else
                                <h6 style="width:40%; display:inline;"
                                    title="Next watering should be at: {{$nextWatering}}">Doesn't need
                                    watering yet
                                    ℹ️</h6>
                            @endif
                            @if($plant->needs->need_fertilizing)
                                <button class="btn " id="fertilizing" style="width:49%"> Fertilized</button>
                            @else

                                <h6 style="width:40%; display:inline;"
                                    title="Next fertilizing should be at: {{$nextFertilizing}}">
                                    Doesn't need fertilizing
                                    yet ℹ️</h6>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

        </div>

    @endif
@endsection
