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
                            <button type="button" class="btn btn-success" data-toggle="modal" data-target="#exampleModal">
                                Edit profile
                              </button>
                              <a  href="{{ route('logout') }}">
                               {{ __('Logout') }}
                           </a>
                              <div class="modal fade" id="exampleModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
                                <div class="modal-dialog" role="document">
                                  <div class="modal-content">
                                    <div class="modal-header">
                                      <h5 class="modal-title" id="updateModal">Edit your profile</h5>
                                      <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                      </button>
                                    </div>
                                    <div class="modal-body">
                                      <form method="POST" enctype="multipart/form-data" action="{{ route('editUserProfile') }}" id="editForm">
                                        @method('PUT')
                                        @csrf
                                        <div class="form-group">
                                          <label>Username</label>
                                          <input type="text" name="name" class="form-control" placeholder="{{$user->name}}">
                                        </div>
                                        <div class="form-group">
                                          <label>Email </label>
                                          <input type="email" name="email" class="form-control" placeholder="{{$user->email}}">
                                        </div>
                                        <div class="form-group">
                                          <label>Password</label>
                                          <input type="password" name="password" class="form-control">
                                        </div>
                                        <div class="form-group">
                                          <label>Confirm password</label>
                                          <input type="password" name="passwordConfirm" class="form-control">
                                        </div>
                                      </form>
                                    </div>
                                    <div class="modal-footer">
                                      <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                      <input form="editForm" type="submit" class="btn btn-success">
                                    </form>
                                    </div>
                                  </div>
                                </div>
                              </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>              
    </div>
    @endauth   
@endsection 