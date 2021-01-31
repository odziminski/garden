@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-body text-center">
                    <form method="POST" enctype="multipart/form-data" action="{{ route('add-plant-query') }}">
                    @csrf
                        <h5> Dates will be assigned from today</h5><br>
                        @if ($errors->any())
                        <div class="text-danger">
                            <ul>
                                @foreach ($errors->all() as $error)
                                    {{ $error }}
                                @endforeach
                            </ul>
                        </div>
                    @endif
                        <label for="name"> Name of your plant </label>
                        <input type="text" class="form-control" name="name" id="name" value="{{ old('name') }}">
                       <!-- <label for="name"> Where's your plant located? </label>
                        <input type="text" class="form-control" id="location"> -->
                        <label for="watering_frequency" >Watering frequency required?</label> 
                        <select class="form-control" id="watering_frequency" name="watering_frequency">
                             
                                <option value = "15"> Very low - for plants that require very small care, watering once per 15 days</option> 
                                <option value = "10"> Low - for plants that require small care, watering once per 10 days </option> 
                                <option value = "6" selected="selected"> Moderate - regular schedule that will work for most plants, watering once per 6 days</option> 
                                <option value = "4"> High - for plants that require intensive care, watering once per 4 days </option> 
                                <option value = "2"> Very high - for the most demanding plants, watering once per 2 days </option> 
                            
                        </select>  
                        <label for="fertilizing_frequency" >Fertilizing frequency required?</label> 
                        <select class="form-control" id="fertilizing_frequency" name="fertilizing_frequency">
                             
                                <option value = "15"> Very low </option> 
                                <option value = "10"> Low </option> 
                                <option value = "6" selected="selected"> Moderate </option> 
                                <option value = "4"> High </option> 
                                <option value = "2"> Very high </option>  
                            
                        </select>  
                        <label>Picture of your plant </label> <br>
                        <input type="file" name="avatar" accept="image/png, image/jpeg">                             
                        <br> <input type="submit" value="Add" class="btn btn-success btn-lg"></button> <br>

                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
