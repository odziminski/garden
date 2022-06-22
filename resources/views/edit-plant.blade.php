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
            <input type="text" class="form-control" name="name" id="name" value="{{ $plant->name }}"> <br/>
            <h4>What's the species of your plant?</h4>
            <input type="text" class="form-control" name="species" value="{{ $plant->species }}"> <br/>
            <h4>Watering frequency required?</h4>
            <div class="text-left">
            <input type="number" class="form-control" name="watering_frequency" value="{{ $plant->needs->watering_frequency }}">

            </div>

            <h4>Fertilizing frequency required?</h4>
            <input type="number" class="form-control" name="fertilizing_frequency" value="{{ $plant->needs->fertilizing_frequency }}">

            <h4>Picture of your plant </h4>
            <img src="{{str_ireplace( 'https://', 'http://', $plant->avatar )}}"/> <br />
            <input type="file" name="avatar" accept="image/png, image/jpeg"> <br/> <br/>
            <input type="submit" value="Add"> <br/>

        </form>
    </div>
    </div>
@endsection
