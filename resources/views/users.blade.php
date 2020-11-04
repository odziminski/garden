@extends('layouts.app')


@section('content')
    @if($user ?? '')
            <table class="table">
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
            
    @endif    
@endsection 