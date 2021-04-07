@extends('layouts.app')


@section('content')
    @auth

        <div class="add_plant_text_center card_thin">
            <label class="font-weight-bold">Email :</label> {{ $user->email }} <br />
            <label class="font-weight-bold">Joined at: </label> {{ $user->created_at }} <br /> <br />
            <button onclick="openmodal('myModal')">Edit <profile></profile></button>
            <div id="myModal" class="modalbox-modal ">
                <div class="modalbox-modal-content">
                    <span class="-close" id="modalbox-close">✖</span>
                    <form method="POST" enctype="multipart/form-data" action="{{ route('editUserProfile') }}" id="editForm">
                        @method('PUT')@csrf
                        <label>Email </label>
                        <input type="email" name="email" class="form-control" placeholder="{{$user->email}}">
                        <label>Password</label>
                        <input type="password" name="password" class="form-control" placeholder="********">
                        <label>Confirm password</label>
                        <input type="password" name="passwordConfirm" class="form-control" placeholder="********"><br />
                        <input form="editForm" type="submit" value="Edit">
                    </form>
                </div>
            </div>



            <a href="{{ route('logout') }}">
            {{ __('Logout') }}
        </div>
        </div>

    @endauth
@endsection
