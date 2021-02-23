@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-body text-center">
                    <form method="POST" enctype="multipart/form-data" action="{{ route('add-plant-query') }}">
                    @csrf
                        @if ($errors->any())
                        <div class="text-danger">
                            <ul>
                                @foreach ($errors->all() as $error)
                                    {{ $error }}
                                @endforeach
                            </ul>
                        </div>
                    @endif
                        <h4>Name of your plant</h4> <br /> 
                        <input type="text" class="form-control" name="name" id="name" value="{{ $plant->name }}"> <br />
                        <h4>Watering frequency required?</h4> <br />
                        <div class="text-left">
                            <input type="radio" name="watering_frequency" value = "15" {{ ($plant->watering_frequency==15)? "checked" : "" }} > Very low - for plants that require very small care, watering once per 15 days <br />
                            <input type="radio" name="watering_frequency" value = "10" {{ ($plant->watering_frequency==10)? "checked" : "" }}> Low - for plants that require small care, watering once per 10 days <br />
                            <input type="radio" name="watering_frequency"  value = "6" {{ ($plant->watering_frequency==6)? "checked" : "" }}> Moderate - regular schedule that will work for most plants, watering once per 6 days <br />
                            <input type="radio" name="watering_frequency" value = "4" {{ ($plant->watering_frequency==4)? "checked" : "" }}> High - for plants that require intensive care, watering once per 4 days  <br />
                            <input type="radio" name="watering_frequency" value = "2" {{ ($plant->watering_frequency==2)? "checked" : "" }}> Very high - for the most demanding plants, watering once per 2 days <br /> <br />
                        </div>
                          
                        <h4>Fertilizing frequency required?</h4> <br />
                        <div class="text-left">

                            <input type="radio" name="fertilizing_frequency" value = "15" {{ ($plant->fertilizing_frequency==15)? "checked" : "" }}> Very low - for plants that require very small care, fertilizing once per 15 days <br />
                            <input type="radio" name="fertilizing_frequency" value = "10" {{ ($plant->fertilizing_frequency==10)? "checked" : "" }}> Low - for plants that require small care, fertilizing once per 10 days <br />
                            <input type="radio" name="fertilizing_frequency"  value = "6" {{ ($plant->fertilizing_frequency==6)? "checked" : "" }}> Moderate - regular schedule that will work for most plants, fertilizing once per 6 days <br />
                            <input type="radio" name="fertilizing_frequency" value = "4" {{ ($plant->fertilizing_frequency==4)? "checked" : "" }}> High - for plants that require intensive care, fertilizing once per 4 days  <br />
                            <input type="radio" name="fertilizing_frequency" value = "2" {{ ($plant->fertilizing_frequency==2)? "checked" : "" }}> Very high - for the most demanding plants, fertilizing once per 2 days <br /><br />

                        </div>
                        <h4>Picture of your plant </h4> <br />
                        <img src={{$plant->avatar}} class="img-thumbnail img-fluid"/>

                        <input type="file" name="avatar" accept="image/png, image/jpeg">    <br /> <br />                          
                         <input type="submit" value="Add" class="btn btn-success btn-lg"></button> <br />

                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
