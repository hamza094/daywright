<section class="landing-subscribe py-5">
  <div class="container">
    <div class="row align-items-center g-5">
      <div class="col-lg-6">
        <div class="landing-subscribe_badge mb-3">Stay Updated</div>
        <h2 class="landing-subscribe_title mb-3">Subscribe for product updates and release notes.</h2>
        <p class="landing-subscribe_subtitle mb-4">Get early access to new features, security updates, and roadmap news. No spam—just the essentials for your team.</p>
        <ul class="landing-subscribe_list list-unstyled mb-4">
          <li><i class="fa-solid fa-envelope-open-text" aria-hidden="true"></i>Product releases and changelog summaries</li>
          <li><i class="fa-solid fa-bell" aria-hidden="true"></i>Security and performance notices</li>
          <li><i class="fa-solid fa-seedling" aria-hidden="true"></i>Early access invites and tips</li>
        </ul>
        <form class="landing-subscribe_form" action="/subscribe" method="post" novalidate>
          @csrf
          <div class="landing-subscribe_field">
            <label class="form-label" for="subscribe_name">Full name</label>
            <input class="form-control" type="text" id="subscribe_name" name="name" placeholder="Alex Johnson" required>
          </div>
          <div class="landing-subscribe_field">
            <label class="form-label" for="subscribe_email">Work email</label>
            <input class="form-control" type="email" id="subscribe_email" name="email" placeholder="you@company.com" required>
          </div>
          <div class="landing-subscribe_field landing-subscribe_consent">
            <input class="form-check-input" type="checkbox" id="subscribe_consent" name="consent" required>
            <label class="form-check-label" for="subscribe_consent">I agree to receive updates from DayWright and understand I can unsubscribe anytime.</label>
          </div>
          <button class="landing-subscribe_btn" type="submit">Subscribe</button>
        </form>
      </div>
      <div class="col-lg-6">
        <figure class="landing-subscribe_figure m-0 text-center">
          <img class="img-fluid" src="{{ asset('img/Subscribe.svg') }}" alt="Illustration of subscribing to DayWright updates">
        </figure>
      </div>
    </div>
  </div>
</section>
