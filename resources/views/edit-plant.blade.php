@extends('layouts.app')

@section('title', 'Edit '.$plant->name)

@section('content')
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <header class="bloom-page-heading">
                    <a href="{{ route('plants', $plant) }}">Back to {{ $plant->name }}</a>
                    <h1>Edit {{ $plant->name }}</h1>
                </header>
                <form class="bloom-panel" method="POST" enctype="multipart/form-data" action="{{ route('editPlant', $plant) }}">
                    @csrf
                    @method('PATCH')
                    @include('plants.form-fields')
                    <button class="btn btn-primary" type="submit">Save plant</button>
                </form>
            </div>
        </div>
    </div>
@endsection
