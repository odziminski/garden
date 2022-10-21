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

                                    @endif
                                    <hr/>

                                    <p class="card-text">
                                    @if ($plant->plantData)
                                        <div>
                                            <h6>
                                                @if ($plant->plantData->wikipedia_description && $plant->plantData->wikipedia_url)

                                                    <?php
                                                    echo substr($plant->plantData->wikipedia_description, 0, strpos($plant->plantData->wikipedia_description, ' ', 250));
                                                    ?>
                                                    ... <a href="{{$plant->plantData->wikipedia_url}}" target="_blank">Read
                                                        more</a>
                                                @endif
                                            </h6>
                                            <br/>
                                            <h5>Taxonomy</h5>
                                            <h6><em>{{$plant->plantData->taxonomy_kingdom}}
                                                    ➤ {{$plant->plantData->taxonomy_phylum}}
                                                    ➤ {{$plant->plantData->taxonomy_class}}
                                                    ➤ {{$plant->plantData->taxonomy_order}}
                                                    ➤ {{$plant->plantData->taxonomy_family}}
                                                    ➤ {{$plant->plantData->taxonomy_genus}}
                                                    ➤ {{$plant->plantData->plant_name}}</em></h6>
                                        </div>
                                        <br/>
                                    @endif
                                    <h6> Next watering should be at: {{$nextWatering}}</h6>
                                    <h6> Next fertilizing should be at: {{$nextFertilizing}}</h6>
                                    <a class="btn " href="{{ route('displayEditPlant',['id' => $plant->id]) }}"
                                       role="button">Edit</a>
                                    <a class="btn " data-bs-toggle="modal" href="#modal" role="button">Delete</a>

                                    <!-- First modal dialog -->
                                    <div class="modal fade" id="modal" aria-hidden="true" aria-labelledby="..."
                                         tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"
                                                            aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <p>Are you sure you want to delete {{$plant->name}}?</p>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary"
                                                            data-bs-dismiss="modal">Close
                                                    </button>
                                                    <a href="{{route('deletePlant',['id' => $plant->id])}}">
                                                        <button type="button" class="btn btn-danger">Delete</button>
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

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
