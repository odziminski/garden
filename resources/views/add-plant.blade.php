@extends('layouts.app')

@section('content')
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <h1 class="h4">Add a plant</h1>
                <form method="POST" enctype="multipart/form-data" action="{{ route('add-plant-query') }}">
                    @csrf
                    @include('plants.form-fields', ['plant' => null])
                    <button class="btn" type="submit">Add plant</button>
                </form>
            </div>
        </div>
    </div>
@endsection
