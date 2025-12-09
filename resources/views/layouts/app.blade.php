<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>DayWright</title>

    @vite(['resources/sass/app.scss', 'resources/js/app.js'])

  <!-- Fonts: Google Fonts (kept in Blade) - use preconnect + css2 + display=swap for better performance & privacy -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="shortcut icon" type="image/png" href="/img/icons/profresh.png">

     @paddleJS

</head>
<body>
<div id="app">
    <main class="">

      <navbar></navbar>

    </main>

    <slideout-panel></slideout-panel>

</div>
</body>

   <!--<section class="footer">
     <footer class="page-footer font-small blue">

  <div class="footer-copyright text-center py-3 ft-p">© 2023 Copyright:
    <a href="http://localhost:8000/"> DayWright.com</a>
  </div>

</footer>
   </section>-->
