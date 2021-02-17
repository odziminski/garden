@extends('layouts.app')


@section('content')

@if (!Auth::guest())

<div class="container">
  <div class="card">

    <img src= {{$plant->avatar}} class="single-image pt-3 mx-auto img-fluid"/>

      <div class="card-content text-center p-5">
          <h4 class="card-title">{{$plant->name}}</h4>       
          <div class="text-left">
              <p> Last time watered: {{$plant->watered_at}} </p> 
              <p> Last time fertilized: {{$plant->fertilized_at}} </p>
              <p> Next watering will be at: {{$nextWatering}} </p>
              <p> Next fertilizing will be at: {{$nextFertilizing}} </p>
          </div>
           @if ($plant->need_watering)
            <a href="{{ route('updateWatering',['id' => $plant->id]) }}" type="button" class="btn-success btn-lg">I have watered the plant</a>
              @if ($lateForWatering > 1)
              
                <p class="text-danger d-block"> You're {{$lateForWatering}} days late for watering! </p>
              @endif
          @else
          <button class="btn btn-success btn-lg" disabled>No need to water yet</button> <br />
            @endif
            @if ($plant->need_fertilizing)
            <a href="{{ route('updateFertilizing',['id' => $plant->id]) }}" type="button" class="btn-success btn-lg">I have fertilized the plant</a><br />
            @if ($lateForFertilizing > 1)
            
            <p class="text-danger d-block"> You're {{$lateForFertilizing}} days late for fertilizing! </p>
          @endif
          @else
          <button class="btn btn-success btn-lg" disabled>No need to fertilize yet</button> <br />
            @endif

          
  </div>
</div>

                               
     


 @endif
  @endsection