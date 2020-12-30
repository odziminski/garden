@extends('layouts.app')


@section('content')
    @if($user ?? '')
    <div class="container">

            <table class="table table-striped table-bordered">
                <tbody>
                <tr>
                    <th scope="col">Username</th>
                    <th scope="col">E-mail</th>
                    <th scope="col">Created at</th>
                </tr>
                <tr>
                <td>{{ $user->name }}</td>
                <td>{{ $user->email }}</td>
                <td>{{ $user->created_at }}</td>
                </tr>
                </tbody>
                </table>
            </div>
    @endif    
@endsection 