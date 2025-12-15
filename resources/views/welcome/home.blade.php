@include('welcome.header')

<main class="container-fluid bg-white">
  <div class="home-content">
    @include('welcome.sections.hero')
    @include('welcome.sections.about')
    @include('welcome.sections.features')
    @include('welcome.sections.pricing')
    @include('welcome.sections.subscribe')
  </div>
</main>

@include('welcome.footer')
