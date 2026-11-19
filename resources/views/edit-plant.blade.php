@extends('layouts.app')

@section('content')
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <h1 class="h4">Edit {{ $plant->name }}</h1>
                <form method="POST" enctype="multipart/form-data" action="{{ route('editPlant', $plant) }}">
                    @csrf
                    @method('PATCH')
                    @include('plants.form-fields')
                    <button class="btn" type="submit">Save plant</button>
                </form>
            </div>
        </div>
    </div>
@endsection
