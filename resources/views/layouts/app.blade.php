<!doctype html>
{{--<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">--}}
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>
    <!-- Scripts -->
    <script src="{{ asset('js/app.js') }}" defer></script>

    <!-- Fonts -->

    <!-- Styles -->
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">

</head>
<body>
{{--<nav>--}}
{{--    <ul class="topnav" id="myTopnav2">--}}
{{--        <li class="navbar-items-center text-black">--}}
{{--            <a class="brand text-black" href="{{route('welcome')}}">bloomify</a></li>--}}
{{--        @auth--}}
{{--            <li class="navbar-items-right text-black">--}}
{{--                <a href="{{route('users')}}">Hello, gardener!</a>--}}
{{--            <li class="navbar-items-right ">--}}
{{--                <a href="{{route('add-plant')}}" class="text-black">Add a plant</a></li>--}}
{{--            <li class="navbar-items-right text-black">--}}
{{--                <a href="{{route('browse')}}" class="text-black">Your garden</a></li>--}}


{{--        @endauth--}}
{{--        <li class="-icon">--}}
{{--            <a href="javascript:void(0);" onclick="topnav('myTopnav2')">☰</a>--}}
{{--        @guest--}}
{{--            <li class="navbar-items-right text-black">--}}
{{--                <a href="{{route('register')}}" class="text-black">Register</a></li>--}}
{{--            <li class="navbar-items-right text-black">--}}
{{--                <a href="{{route('login')}}" class="text-black">Login</a></li>--}}


{{--            @endguest--}}
{{--            </li>--}}
{{--    </ul>--}}
{{--</nav>--}}

<nav class="navbar navbar-expand-lg navbar-light bg-light">
    <div class="container-fluid">
        <a class="navbar-brand" href="{{route('welcome')}}">bloomify</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent"
                aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarSupportedContent">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">

            </ul>
            <ul class="navbar-nav">
                @auth
                    <li class="nav-item">
                        <a class="nav-link active" aria-current="page" href="{{route('browse')}}">your garden</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{route('add-plant')}}">add a plant</a>
                    </li>

                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button"
                           data-bs-toggle="dropdown" aria-expanded="false"> hello, gardener! </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                            <li><a class="dropdown-item" href="{{route('logout')}}">logout</a></li>

                        </ul>
                @endauth
                @guest
                    <li class="nav-item">
                        <a class="nav-link active" aria-current="page" href="{{route('login')}}">login</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{route('register')}}">register</a>
                    </li>
                    @endguest
                    </li>
            </ul>
        </div>
    </div>
</nav>
</body>

<main class="py-4">
    @yield('content')
</main>
</html>
