@extends('layouts.app')


@section('content')

@if (!Auth::guest())

<div class="container">
  <div class="card">
    <div class="">
      <div class="img-card">
          <img src="{{ asset('images/'. $plant->avatar) }}" />
      </div>
      <div class="card-content text-center">
          <h4 class="card-title text-center">
              {{$plant->name}}
          </h4>       
          <div class="text-successs">
              <p> Last watered: {{$plant->watered_at}} </p> 
              <p> Last fertilized:{{$plant->fertilized_at}} </p>
              
              <p> Next watering will be at: {{$nextWatering}} </p>
              <p> Next fertilizing will be at: {{$nextFertilizing}} </p>
           
            <a href="{{ route('update',['column' => 'watered_at','id' => $plant->id]) }}" type="button" class="btn-success btn-lg">I just watered this plant</a>
            <a href="{{ route('update',['column' => 'fertilized_at','id' => $plant->id]) }}" type="button" class="btn-success btn-lg">I just fertilized this plant</a>

          </div>
         
      </div>

      </a>
  </div>
</div>
</a>
                               
     


 @endif
  @endsection