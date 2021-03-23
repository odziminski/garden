@extends('layouts.app')


@section('content')

@if (!Auth::guest())

  <div class="card-single-plant">

    <img src= "{{str_ireplace( 'https://', 'http://', $plant->avatar )}}" alt="{{$plant->name}}"/>

      <div class="">
          <h4 class="card-title">{{$plant->name}}</h4>
          <div class="text-right">
            @if ($trefleData ?? '')
              <h4 class="font-italic"> {{$trefleData['scientific_name']}} </h4>
              <p> Also called <span class="font-weight-bold">{{$trefleData['common_name']}}</span>.
                Is a species of the <span class="font-weight-bold"> {{$trefleData['family']}}</span> family.
              </p>
              <p> Synonyms:
                <span class="font-italic"> {{$trefleData['synonyms'][0]}} </span>
                or <span class="font-italic"> {{$trefleData['synonyms'][1]}} </span>
            @endif
            </p>
          </div>
      </div>
          <div class="text-center">
            <table class="table table-striped">
              <thead>
                <tr>
                  <th scope="col">Last time watered</th>
                  <th scope="col">Last time fertilized</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td>{{$plant->watered_at}}</td>
                  <td>{{$plant->fertilized_at}}</td>
                </tr>
                <tr>
                </tbody>
              </table>
              <table class="table table-striped">
                <thead>
                  <tr>
                    <th scope="col">Next watering </th>
                    <th scope="col">Next fertilizing </th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td>{{$nextWatering}}</td>
                    <td>{{$nextFertilizing}}</td>
                  </tr>
                  <tr>
                  </tbody>
                </table>
             <div class="text-center">
           @if ($plant->need_watering)
            <a href="{{ route('updateWatering',['id' => $plant->id]) }}" type="button" class="btn-success btn-lg">I have watered the plant</a>
              @if ($lateForWatering > 1)
                <span class="text-danger d-block"> You're {{$lateForWatering}} days late for watering! </span>
              @endif
          @else
          <button class="btn btn-success btn-lg" disabled>No need to water yet</button>
            @endif
            @if ($plant->need_fertilizing)
            <a href="{{ route('updateFertilizing',['id' => $plant->id]) }}" type="button" class="btn-success btn-lg">I have fertilized the plant</a>
            @if ($lateForFertilizing > 1)

            <p class="text-danger d-block"> You're {{$lateForFertilizing}} days late for fertilizing! </p>
          @endif
          @else
          <button class="btn btn-success btn-lg" disabled>No need to fertilize yet</button> <br />
            @endif
            <a href="{{ route('displayEditPlant',['id' => $plant->id]) }}">Edit the plant</a><br />

            <a href="#" data-toggle="modal" data-target="#exampleModalCenter">Delete the plant</a><br />
            <div class="modal fade" id="exampleModalCenter" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
              <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                  <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLongTitle">Deletion</h5>
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






 @endif
  @endsection
