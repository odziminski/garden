@extends('layouts.app')

@section('content')
    <div class="add_plant_text_center">
        <form method="POST" enctype="multipart/form-data" action="{{ route('add-plant-query') }}" class="form-group">
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
            <input type="number" class="form-control" name="watering_frequency" value="{{ old('watering_frequency') }}">

            </div>

            <h5>Fertilizing frequency required?</h5>
            <div class="text-left">

            <input type="number" class="form-control" name="fertilizing_frequency" value="{{ old('fertilizing_frequency') }}">


            </div>
            <h5>Picture of your plant </h5> <br/>
            <input type="file" name="avatar" accept="image/png, image/jpeg"> <br/> <br/>
            <input type="submit" value="Add" class="btn btn-success btn-lg"> <br/>

        </form>
    </div>
    </div>
@endsection
