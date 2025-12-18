<template>
  <div class="auth-page">
    <div class="auth-card auth-card--narrow">
      <div class="auth-form">
        <div class="text-center mb-4">
          <router-link to="/" aria-label="Back to home">
            <img src="/img/D2.png" alt="DayWright" class="auth-logo_img" />
          </router-link>
        </div>

        <h2 class="auth-title text-center mb-2">Reset password</h2>
        <p class="auth-subtitle text-center mb-4">Choose a new password for your account.</p>

        <form method="POST" @submit.prevent="resetPassword">
          <div class="form-group mb-3">
            <label for="email" class="form-label">Email</label>
            <input
              id="email"
              type="email"
              class="form-control"
              name="email"
              v-model="form.email"
              required
              autocomplete="email"
              readonly />
            <span class="text-danger font-italic" v-if="errors.email" v-text="errors.email[0]"></span>
          </div>

          <div class="form-group mb-3">
            <label for="password" class="form-label">New password</label>
            <input
              id="password"
              type="password"
              class="form-control"
              name="password"
              v-model="form.password"
              required
              autocomplete="new-password" />
            <span class="text-danger font-italic" v-if="errors.password" v-text="errors.password[0]"></span>
          </div>

          <div class="form-group mb-4">
            <label for="password-confirm" class="form-label">Confirm password</label>
            <input
              id="password-confirm"
              type="password"
              class="form-control"
              name="password_confirmation"
              v-model="form.password_confirmation"
              required
              autocomplete="new-password" />
            <span
              class="text-danger font-italic"
              v-if="errors.password_confirmation"
              v-text="errors.password_confirmation[0]"></span>
          </div>

          <button type="submit" class="btn btn-primary auth-submit w-100">Update password</button>
        </form>

        <p class="auth-small text-center mt-3 mb-0">
          Remembered it?
          <router-link class="auth-link" to="/login">Return to sign in</router-link>
        </p>
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
        email: '',
        password: '',
        password_confirmation: '',
      },
    };
  },
  mounted() {
    // Get the email from the query parameters and set it in the form
    const emailFromQuery = this.$route.query.email;
    if (emailFromQuery) {
      this.form.email = decodeURIComponent(emailFromQuery);
    }
  },
  methods: {
    resetPassword() {
      this.$Progress.start();
      axios
        .post('/reset-password', {
          email: this.form.email,
          password: this.form.password,
          password_confirmation: this.form.password_confirmation,
          token: this.$route.params.token,
        })
        .then(() => {
          this.$Progress.finish();
          swal.fire('Password Changed', 'Please Login to continue', 'success');
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
