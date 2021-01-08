@extends('layouts.app')


@section('content')

@if (!Auth::guest())

<div class="container">
  <div class="card">

    <img class="rounded pt-3 mx-auto" src= {{$plant->avatar}}/>

      <div class="card-content text-center p-5">
          <h4 class="card-title text-center">
              {{$plant->name}}
          </h4>       
          <div class="text-left">

              <p> Last watered: {{$plant->watered_at}} </p> 
              <p> Last fertilized:{{$plant->fertilized_at}} </p>
      
              <p> Next watering will be at: {{$nextWatering}} </p>
              <p> Next fertilizing will be at: {{$nextFertilizing}} </p>
          </div>
           @if ($plant->need_watering)
            <a href="{{ route('update',['column' => 'watered_at','id' => $plant->id]) }}" type="button" class="btn-success btn-lg">Watered the plant</a>
          @else
          <button class="btn btn-success btn-lg"  disabled>No need to water yet</button>
            @endif
            @if ($plant->need_watering)
            <a href="{{ route('update',['column' => 'fertilized_at','id' => $plant->id]) }}" type="button" class="btn-success btn-lg">Fertilized the plant</a>
          @else
          <button class="btn btn-success btn-lg"  disabled>No need to fertilize yet</button>
            @endif

          </div>
         
      </div>

      </a>
  </div>
</div>
</a>
                               
     


 @endif
  @endsection