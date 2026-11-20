<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#f7f8f2">
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/icon-192.png') }}">
    <title>@hasSection('title')@yield('title') · @endif Bloomify</title>
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">
    <script src="{{ asset('js/app.js') }}" defer></script>
</head>
<body>
    <a class="visually-hidden-focusable bloom-skip-link" href="#main-content">Skip to content</a>

    <header class="bloom-header">
        <nav class="navbar navbar-expand-md" aria-label="Main navigation">
            <div class="container">
                <a class="navbar-brand bloom-brand" href="{{ route('welcome') }}">
                    <span class="bloom-brand-mark" aria-hidden="true"><span></span></span>
                    bloomify
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#bloom-navigation"
                        aria-controls="bloom-navigation" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="bloom-navigation">
                    <ul class="navbar-nav ms-auto align-items-md-center">
                        @auth
                            <li class="nav-item">
                                <a @class(['nav-link', 'active' => request()->routeIs('browse', 'plants', 'displayEditPlant')])
                                   @if (request()->routeIs('browse')) aria-current="page" @endif href="{{ route('browse') }}">Your garden</a>
                            </li>
                            @if (Route::has('care.today'))
                                <li class="nav-item">
                                    <a @class(['nav-link', 'active' => request()->routeIs('care.today')])
                                       @if (request()->routeIs('care.today')) aria-current="page" @endif href="{{ route('care.today') }}">Today’s care</a>
                                </li>
                            @endif
                            <li class="nav-item dropdown">
                                <button @class(['nav-link', 'dropdown-toggle', 'active' => request()->routeIs('users')])
                                        type="button" data-bs-toggle="dropdown" aria-expanded="false" id="bloom-account">Account</button>
                                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="bloom-account">
                                    <li>
                                        <a class="dropdown-item" @if (request()->routeIs('users')) aria-current="page" @endif
                                           href="{{ route('users') }}">Profile</a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" @if (request()->routeIs('garden-sharing.*')) aria-current="page" @endif
                                           href="{{ route('garden-sharing.index') }}">Garden sharing</a>
                                    </li>
                                    <li>
                                        <form method="POST" action="{{ route('logout') }}">
                                            @csrf
                                            <button class="dropdown-item" type="submit">Log out</button>
                                        </form>
                                    </li>
                                </ul>
                            </li>
                            <li class="nav-item bloom-nav-action">
                                <a class="btn btn-primary" @if (request()->routeIs('add-plant')) aria-current="page" @endif
                                   href="{{ route('add-plant') }}"><span aria-hidden="true">+</span> Add a plant</a>
                            </li>
                        @endauth
                        @guest
                            <li class="nav-item">
                                <a @class(['nav-link', 'active' => request()->routeIs('login')])
                                   @if (request()->routeIs('login')) aria-current="page" @endif href="{{ route('login') }}">Log in</a>
                            </li>
                            <li class="nav-item bloom-nav-action">
                                <a class="btn btn-primary" @if (request()->routeIs('register')) aria-current="page" @endif
                                   href="{{ route('register') }}">Create an account</a>
                            </li>
                        @endguest
                    </ul>
                </div>
            </div>
        </nav>
    </header>

    <main class="bloom-main" id="main-content">
        @if (session('care_notice'))
            <div class="container">
                <p class="alert alert-info" role="status">{{ session('care_notice') }}</p>
            </div>
        @endif
        @yield('content')
    </main>
</body>
</html>
