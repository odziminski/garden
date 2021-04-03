@extends('layouts.app')


@section('content')

@if (!Auth::guest())
    <div class="card-single-plant _alignCenter">
    <img src= "{{str_ireplace( 'https://', 'http://', $plant->avatar )}}" alt="{{$plant->name}}"/>
          <h4>{{$plant->name}}</h4>
            @if ($trefleData ?? '')
            <div class="_alignRight">
              <h4 class="font-italic"> {{$trefleData['scientific_name']}} </h4>
              <p> Also called <span class="font-weight-bold">{{$trefleData['common_name']}}</span>.
                Is a species of the <span class="font-weight-bold"> {{$trefleData['family']}}</span> family.
              </p>
              <p> Synonyms:
                <span class="font-italic"> {{$trefleData['synonyms'][0]}} </span>
                or <span class="font-italic"> {{$trefleData['synonyms'][1]}} </span>
              </p>
            </div>
            @endif
            <table class="_width75">
              <thead>
                <tr>
                  <th>Last time watered</th>
                  <th>Last time fertilized</th>
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
              <table class="_width75">
                <thead>
                  <tr>
                    <th>Next watering </th>
                    <th>Next fertilizing </th>
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
           @if ($plant->need_watering)
            <div class="needs">
            <a href="{{ route('updateWatering',['id' => $plant->id]) }}" class="button">I have watered the plant</a>
              @if ($lateForWatering > 1)
                <span class="caption"> You're {{$lateForWatering}} days late for watering! </span>
            </div>
              @endif
          @else

          <button class="_disabled">No need to water yet</button>
            @endif
            @if ($plant->need_fertilizing)
            <div class="needs">
            <a href="{{ route('updateFertilizing',['id' => $plant->id]) }}" class="button">I have fertilized the plant</a>
            @if ($lateForFertilizing > 1)
                <span class="caption"> You're {{$lateForFertilizing}} days late for fertilizing! </span> <br />
            </div>
          @endif
          @else
          <button class="_disabled">No need to fertilize yet</button> <br />
            @endif
            <br />
            <a href="{{ route('displayEditPlant',['id' => $plant->id]) }}" >Edit the plant</a><br />

        <a href="#" onclick="openmodal('myModal')">Delete plant</a>
        <div id="myModal" class="modalbox-modal ">
            <div class="modalbox-modal-content">
                <span class="-close" id="modalbox-close">✖</span>
                Are you sure you wanna delete <span class="font-weight-bold">{{$plant->name}}</span>?
                <a href="{{ route('deletePlant',['id' => $plant->id]) }}">Delete</a>
            </div>
        </div>
    </div>
    </div>


 @endif
  @endsection
