@extends('layouts.app')

@section('content')
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/webcamjs/1.0.25/webcam.min.js"></script>

<div class="add_plant_text_center">
    <form method="POST" enctype="multipart/form-data" action="{{ route('add-plant-query') }}" class="form-group">
        @csrf
        @if ($errors->any())
        <div class="text-danger">
            <ul>
                @foreach ($errors->all() as $error)
                {{ $error }}
                @endforeach
            </ul>
        </div>
        @endif
        <h5>Name of your plant</h5>
        <input type="text" name="name" id="name" value="{{ old('name') }}"> <br />
        <h5>What's the species of your plant?</h5>
        <input type="text" name="species" value="{{ old('species') }}"> <br />
        <h5>Watering frequency required?</h5>
        <div class="text-left">
            <input type="number" class="form-control" name="watering_frequency" value="{{ old('watering_frequency') }}">

        </div>

        <h5>Fertilizing frequency required?</h5>
        <div class="text-left">

            <input type="number" class="form-control" name="fertilizing_frequency" value="{{ old('fertilizing_frequency') }}">


        </div>
        <h5>Picture of your plant </h5> <br />
        <input type="file" name="avatar" accept="image/png, image/jpeg"> <br /> <br />
        <input type="button" value="Or use a webcam" onClick="startWebcam()"> <br /> <br />



        <div class="row" style="visibility:hidden;" id="snapshot">
            <div class="col-md-6 d-none">
                <div id="myCamera"></div>
                <br />
                <input type="button" value="Take Snapshot" onClick="takeSnapshot()">
                <input type="hidden" name="webcamAvatar" class="image-tag" hidden>
            </div>
            <div class="col-md-6">
                <div id="results"></div>
            </div>

        </div>
        <input type="submit" value="Add" class="btn btn-success btn-lg"> <br />

    </form>
</div>
</div>

<script language="JavaScript">
    function startWebcam() {
        Webcam.set({
            width: 490,
            height: 350,
            image_format: 'jpeg',
            jpeg_quality: 100
        });
        Webcam.attach('#myCamera');
        document.getElementById('snapshot').style.visibility = 'visible';
    }

    function takeSnapshot() {
        Webcam.snap(function(data_uri) {
            $(".image-tag").val(data_uri);
            document.getElementById('results').innerHTML = '<img src="' + data_uri + '"/>';
        });
    }
</script>
@endsection