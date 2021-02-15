@extends('layouts.app')


@section('content')
    @auth

        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-body text-left">
                            <label class="font-weight-bold">Username :</label> {{ $user->name }} <br />
                            <label class="font-weight-bold">Email :</label> {{ $user->email }} <br />
                            <label class="font-weight-bold">Joined at: </label> {{ $user->created_at }} <br />
                        </div>
                    </div>
                </div>
            </div>
        </div>              
                    
                            
                            

    </div>
    @endauth   
@endsection 