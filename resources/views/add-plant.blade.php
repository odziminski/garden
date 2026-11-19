@extends('layouts.app')

@section('title', 'Add a plant')

@section('content')
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <header class="bloom-page-heading"><h1>Add a plant</h1></header>
                <form class="bloom-panel" method="POST" enctype="multipart/form-data" action="{{ route('add-plant-query') }}">
                    @csrf
                    @include('plants.form-fields', ['plant' => null])
                    <button class="btn btn-primary" type="submit">Add plant</button>
                </form>
            </div>
        </div>
    </div>
@endsection
