@extends('layouts.app')

@section('content')
    <div class="add_plant_text_center">
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
                <h5>Name of your plant</h5>
                <input type="text" name="name" id="name" value="{{ old('name') }}"> <br/>
                <h5>What's the species of your plant?</h5>
                <input type="text" name="species" value="{{ old('species') }}"> <br/>
                <h5>Watering frequency required?</h5>
                <div class="text-left">
                    <input type="radio" name="watering_frequency" value="15"> Very low  <br/>
                    <input type="radio" name="watering_frequency" value="10"> Low <br/>
                    <input type="radio" name="watering_frequency" value="6"> Moderate <br/>
                    <input type="radio" name="watering_frequency" value="4"> High <br/>
                    <input type="radio" name="watering_frequency" value="2"> Very high <br/>
                </div>

                <h5>Fertilizing frequency required?</h5>
                <div class="text-left">

                    <input type="radio" name="fertilizing_frequency" value="15"> Very low <br/>
                    <input type="radio" name="fertilizing_frequency" value="10"> Low <br/>
                    <input type="radio" name="fertilizing_frequency" value="6"> Moderate <br/>
                    <input type="radio" name="fertilizing_frequency" value="4"> High <br/>
                    <input type="radio" name="fertilizing_frequency" value="2"> Very high <br/><br/>

                </div>
                <h5>Picture of your plant </h5> <br/>
                <input type="file" name="avatar" accept="image/png, image/jpeg"> <br/> <br/>
                <input type="submit" value="Add" class="btn btn-success btn-lg"> <br/>

            </form>
        </div>
    </div>
@endsection
