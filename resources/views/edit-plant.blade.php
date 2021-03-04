@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-body text-center">
                    <form method="POST" enctype="multipart/form-data" action="{{ route('editPlant',['id' => $plant->id]) }}">
                        @method('PATCH')
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
                        <h4>What's the species of your plant?</h4> <br />
                        <input type="text" class="form-control" name="species" value="{{ $plant->species }}"> <br />
                        <h4>Watering frequency required?</h4> <br />
                        <div class="text-left">
                            <input type="radio" name="watering_frequency" value = "15" {{ ($plant->watering_frequency==15)? "checked" : "" }} > Very low <br />
                            <input type="radio" name="watering_frequency" value = "10" {{ ($plant->watering_frequency==10)? "checked" : "" }}> Low <br />
                            <input type="radio" name="watering_frequency"  value = "6" {{ ($plant->watering_frequency==6)? "checked" : "" }}> Moderate  <br />
                            <input type="radio" name="watering_frequency" value = "4" {{ ($plant->watering_frequency==4)? "checked" : "" }}> High  <br />
                            <input type="radio" name="watering_frequency" value = "2" {{ ($plant->watering_frequency==2)? "checked" : "" }}> Very high  <br /> <br />
                        </div>
                          
                        <h4>Fertilizing frequency required?</h4> <br />
                        <div class="text-left">

                            <input type="radio" name="fertilizing_frequency" value = "15" {{ ($plant->fertilizing_frequency==15)? "checked" : "" }}> Very low  <br />
                            <input type="radio" name="fertilizing_frequency" value = "10" {{ ($plant->fertilizing_frequency==10)? "checked" : "" }}> Low  <br />
                            <input type="radio" name="fertilizing_frequency"  value = "6" {{ ($plant->fertilizing_frequency==6)? "checked" : "" }}> Moderate  <br />
                            <input type="radio" name="fertilizing_frequency" value = "4" {{ ($plant->fertilizing_frequency==4)? "checked" : "" }}> High   <br />
                            <input type="radio" name="fertilizing_frequency" value = "2" {{ ($plant->fertilizing_frequency==2)? "checked" : "" }}> Very high  <br /><br />

                        </div>
                        <h4>Picture of your plant </h4> <br />
                        <img src={{$plant->avatar}} class="img-thumbnail img-fluid single-image"/>

                        <input type="file" name="avatar" accept="image/png, image/jpeg">    <br /> <br />                          
                         <input type="submit" value="Add" class="btn btn-success btn-lg"></button> <br />

                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
