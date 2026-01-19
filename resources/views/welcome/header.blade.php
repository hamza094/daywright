<Doctype html>

<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>DayWright - Work on big ideas</title>

      <script>window.Laravel = {csrfToken: '{{ csrf_token() }}'}</script>

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <link rel="shortcut icon" type="image/png" href="{{'/img/daywrightlogo.png'}}">


    <!-- Fonts -->
    <link rel="dns-prefetch" href="//fonts.gstatic.com">

    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.8.2/css/all.css" integrity="sha384-oS3vJWv+0UjzBfQzYUhtDYW+Pj2yciDJxpsK1OYPAYjqT085Qq/1cq5FLXAZQ7Ay" crossorigin="anonymous">

    @vite(['resources/sass/app.scss', 'resources/js/app.js'])

</head>
<body>
                    <header class="landing-header">
                        <nav class="navbar navbar-expand-md navbar-light landing-nav" id="app">
                            <div class="container">
                                <a class="navbar-brand d-flex align-items-center landing-nav_brand" href="{{ url('/') }}">
                                    <img class="landing-nav_logo" src="{{ asset('img/D2.png') }}" alt="DayWright logo">
                                    <span class="landing-nav_title">DayWright</span>
                                </a>
                                <button class="navbar-toggler collapsed" type="button" data-toggle="collapse" data-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="{{ __('Toggle navigation') }}">
                                    <span class="navbar-toggler-icon"></span> 
                                </button>

                                <div class="collapse navbar-collapse" id="navbarSupportedContent">
                                    <ul class="navbar-nav ml-auto align-items-md-center">
                                        <li class="nav-item">
                                            <a class="nav-link landing-nav_link" href="#about">About</a>
                                        </li>
                                        <li class="nav-item">
                                            <a class="nav-link landing-nav_link" href="#features">Features</a>
                                        </li>
                                        <li class="nav-item">
                                            <a class="nav-link landing-nav_link" href="#pricing">Pricing</a>
                                        </li>
                                        <li class="nav-item d-flex align-items-center">
                                            <a class="landing-nav_icon" href="https://github.com/hamza094/daywright" target="_blank" rel="noopener noreferrer" aria-label="DayWright on GitHub">
                                                <i class="fab fa-github" aria-hidden="true"></i>
                                            </a>
                                        </li>
                                        <li class="nav-item">
                                            <a class="landing-nav_cta" href="{{ auth()->check() ? '/dashboard' : '/login' }}">Get started</a>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </nav>
                    </header>
