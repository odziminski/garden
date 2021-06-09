<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>
    <!-- Scripts -->
    <script src="https://rawgit.com/outboxcraft/beauter/master/beauter.min.js" defer></script>
    <!-- Fonts -->

    <!-- Styles -->
    <link href="https://rawgit.com/outboxcraft/beauter/master/beauter.min.css" rel="stylesheet">
    <link href="{{ asset('css/styles.css') }}" rel="stylesheet">

</head>
<body>
<nav>
    <ul class="topnav" id="myTopnav2">
        <li class="navbar-items-center text-black">
            <a class="brand text-black" href="{{route('welcome')}}">bloomify</a></li>
        @auth
            <li class="navbar-items-right text-black">
                <a href="{{route('users')}}">Hello, gardener!</a>
            <li class="navbar-items-right ">
                <a href="{{route('add-plant')}}" class="text-black">Add a plant</a></li>
            <li class="navbar-items-right text-black">
                <a href="{{route('browse')}}" class="text-black">Your garden</a></li>


        @endauth
        <li class="-icon">
            <a href="javascript:void(0);" onclick="topnav('myTopnav2')">☰</a>
        @guest
            <li class="navbar-items-right text-black">
                <a href="{{route('register')}}" class="text-black">Register</a></li>
            <li class="navbar-items-right text-black">
                <a href="{{route('login')}}" class="text-black">Login</a></li>


            @endguest
            </li>
    </ul>
</nav>
</body>

<main class="py-4">
    @yield('content')
</main>
</html>
