@extends('layouts.app')

@section('content')
    <div class="add_plant_text_center">
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
            <h4>Name of your plant</h4>
            <input type="text" class="form-control" name="name" id="name" placeholder="{{ $plant->name }}"> <br/>
            <h4>What's the species of your plant?</h4>
            <input type="text" class="form-control" name="species" placeholder="{{ $plant->species }}"> <br/>
            <h4>Watering frequency required?</h4>
            <div class="text-left">
                <input type="radio" name="watering_frequency"
                       value="15" {{ ($plant->watering_frequency==15)? "checked" : "" }} > Very low <br/>
                <input type="radio" name="watering_frequency"
                       value="10" {{ ($plant->watering_frequency==10)? "checked" : "" }}> Low <br/>
                <input type="radio" name="watering_frequency"
                       value="6" {{ ($plant->watering_frequency==6)? "checked" : "" }}> Moderate <br/>
                <input type="radio" name="watering_frequency"
                       value="4" {{ ($plant->watering_frequency==4)? "checked" : "" }}> High <br/>
                <input type="radio" name="watering_frequency"
                       value="2" {{ ($plant->watering_frequency==2)? "checked" : "" }}> Very high <br/> <br/>
            </div>

            <h4>Fertilizing frequency required?</h4>

                <input type="radio" name="fertilizing_frequency"
                       value="15" {{ ($plant->fertilizing_frequency==15)? "checked" : "" }}> Very low <br/>
                <input type="radio" name="fertilizing_frequency"
                       value="10" {{ ($plant->fertilizing_frequency==10)? "checked" : "" }}> Low <br/>
                <input type="radio" name="fertilizing_frequency"
                       value="6" {{ ($plant->fertilizing_frequency==6)? "checked" : "" }}> Moderate <br/>
                <input type="radio" name="fertilizing_frequency"
                       value="4" {{ ($plant->fertilizing_frequency==4)? "checked" : "" }}> High <br/>
                <input type="radio" name="fertilizing_frequency"
                       value="2" {{ ($plant->fertilizing_frequency==2)? "checked" : "" }}> Very high <br/>


            <h4>Picture of your plant </h4>
            <img src="{{str_ireplace( 'https://', 'http://', $plant->avatar )}}"/> <br />
            <input type="file" name="avatar" accept="image/png, image/jpeg"> <br/> <br/>
            <input type="submit" value="Add"> <br/>

        </form>
    </div>
    </div>
@endsection
