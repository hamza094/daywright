<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>DayWright</title>

    @vite(['resources/sass/app.scss', 'resources/js/app.js'])

    <link rel="shortcut icon" type="image/png" href="/img/daywrightlogo.png">

     @paddleJS

</head>
<body>
<div id="app">
    <main class="">

      <navbar></navbar>

    </main>

    <slideout-panel></slideout-panel>
    <plan-limit-modal></plan-limit-modal>

</div>
</body>
