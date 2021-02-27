@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-body text-center">
                    <form method="POST" enctype="multipart/form-data" action="{{ route('add-plant-query') }}">
                    @csrf
                        <!--<h6> Dates will be assigned from today </h6><br /> -->
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
                        <input type="text" class="form-control" name="name" id="name" value="{{ old('name') }}"> <br />
                        <h4>What's the species of your plant?</h4> <br />
                        <input type="text" class="form-control" name="species" value="{{ old('species') }}"> <br />
                        <h4>Watering frequency required?</h4> <br />
                        <div class="text-left">
                            <input type="radio" name="watering_frequency" value = "15"> Very low - for plants that require very small care, watering once per 15 days <br />
                            <input type="radio" name="watering_frequency" value = "10"> Low - for plants that require small care, watering once per 10 days <br />
                            <input type="radio" name="watering_frequency"  value = "6"> Moderate - regular schedule that will work for most plants, watering once per 6 days <br />
                            <input type="radio" name="watering_frequency" value = "4"> High - for plants that require intensive care, watering once per 4 days  <br />
                            <input type="radio" name="watering_frequency" value = "2"> Very high - for the most demanding plants, watering once per 2 days <br /> <br />
                        </div>
                          
                        <h4>Fertilizing frequency required?</h4> <br />
                        <div class="text-left">

                            <input type="radio" name="fertilizing_frequency" value = "15"> Very low - for plants that require very small care, fertilizing once per 15 days <br />
                            <input type="radio" name="fertilizing_frequency" value = "10"> Low - for plants that require small care, fertilizing once per 10 days <br />
                            <input type="radio" name="fertilizing_frequency"  value = "6"> Moderate - regular schedule that will work for most plants, fertilizing once per 6 days <br />
                            <input type="radio" name="fertilizing_frequency" value = "4"> High - for plants that require intensive care, fertilizing once per 4 days  <br />
                            <input type="radio" name="fertilizing_frequency" value = "2"> Very high - for the most demanding plants, fertilizing once per 2 days <br /><br />

                        </div>
                        <h4>Picture of your plant </h4> <br />
                        <input type="file" name="avatar" accept="image/png, image/jpeg">    <br /> <br />                          
                         <input type="submit" value="Add" class="btn btn-success btn-lg"></button> <br />

                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
