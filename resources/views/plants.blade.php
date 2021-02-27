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
            <a href="{{ route('displayEditPlant',['id' => $plant->id]) }}">Edit the plant</a><br />

            <a href="#" data-toggle="modal" data-target="#exampleModal">Delete the plant</a><br />
            

            <div class="modal fade" id="exampleModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
              <div class="modal-dialog" role="document">
                <div class="modal-content">
                  <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                      <span aria-hidden="true">&times;</span>
                    </button>
                  </div>
                  <div class="modal-body">
                    Are you sure you wanna delete <strong> {{$plant->name}}? </strong>
                  </div>
                  <div class="modal-footer">
                    <button type="button" class="btn btn-success" data-dismiss="modal">No</button>
                    <form action="{{ route('deletePlant',['id' => $plant->id]) }}">
                      <button type="submit" class="btn btn-danger">Yes</button>
                    </form>
                  </div>
                </div>
              </div>
            </div>

  </div>
</div>

                               
     


 @endif
  @endsection