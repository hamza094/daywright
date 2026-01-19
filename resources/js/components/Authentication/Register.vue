<template>
  <div class="auth-page">
    <div class="auth-card">
      <div class="row g-0 align-items-stretch">
        <div class="col-lg-6 d-none d-lg-flex auth-visual">
          <img src="/img/sign_in.svg" alt="Sign up illustration" class="auth-visual_img" />
        </div>
        <div class="col-lg-6 col-12 auth-form">
          <div class="auth-logo text-center mb-4">
            <a href="/" aria-label="Back to welcome">
              <img src="/img/D2.png" alt="DayWright" class="auth-logo_img" />
            </a>
          </div>
          <h2 class="auth-title text-center mb-2">Create your account</h2>
          <p class="auth-subtitle text-center mb-4">Join DayWright to collaborate with your team.</p>

          <form method="POST" @submit.prevent="RegisterUser">
            <div class="form-group mb-3">
              <label for="name" class="form-label">Name</label>
              <div class="input-group auth-input">
                <span class="input-group-text">
                  <i class="fa-regular fa-user"></i>
                </span>
                <input
                  id="name"
                  type="text"
                  class="form-control"
                  name="name"
                  v-model="form.name"
                  required
                  autocomplete="name"
                  autofocus />
              </div>
              <span class="text-danger font-italic" v-if="errors.name" v-text="errors.name[0]"></span>
            </div>

            <div class="form-group mb-3">
              <label for="email" class="form-label">Email</label>
              <div class="input-group auth-input">
                <span class="input-group-text">
                  <i class="fa-regular fa-envelope"></i>
                </span>
                <input
                  id="email"
                  type="email"
                  class="form-control"
                  name="email"
                  v-model="form.email"
                  required
                  autocomplete="email" />
              </div>
              <span class="text-danger font-italic" v-if="errors.email" v-text="errors.email[0]"></span>
            </div>

            <div class="form-group mb-3">
              <label for="password" class="form-label">Password</label>
              <div class="input-group auth-input">
                <span class="input-group-text">
                  <i class="fa-solid fa-lock"></i>
                </span>
                <input
                  id="password"
                  type="password"
                  class="form-control"
                  name="password"
                  v-model="form.password"
                  required
                  autocomplete="new-password" />
              </div>
              <span class="text-danger font-italic" v-if="errors.password" v-text="errors.password[0]"></span>
            </div>

            <div class="form-group mb-4">
              <label for="password-confirm" class="form-label">Confirm Password</label>
              <div class="input-group auth-input">
                <span class="input-group-text">
                  <i class="fa-solid fa-lock"></i>
                </span>
                <input
                  id="password-confirm"
                  type="password"
                  class="form-control"
                  name="password_confirmation"
                  v-model="form.password_confirmation"
                  required
                  autocomplete="new-password" />
              </div>
              <span
                class="text-danger font-italic"
                v-if="errors.password_confirmation"
                v-text="errors.password_confirmation[0]"></span>
            </div>

            <button type="submit" class="btn btn-primary auth-submit w-100">Sign Up</button>

            <p class="auth-small text-center mt-4 mb-0">
              Already have an account?
              <router-link class="auth-link" to="/login">Sign in</router-link>
            </p>
          </form>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  data() {
    return {
      errors: {},
      form: {
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
      },
    };
  },
  methods: {
    RegisterUser() {
      this.errors = {};
      this.$Progress.start();
      axios
        .post('/register', this.form, {})
        .then(() => {
          this.$Progress.finish();
          swal.fire('Account Registered', 'Please Verify your account and login', 'success');
          this.$router.push('/login');
        })
        .catch((error) => {
          this.$Progress.fail();
          this.errors = error.response.data.errors;
        });
    },
  },
};
</script>
