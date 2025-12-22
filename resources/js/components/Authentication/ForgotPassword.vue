<template>
  <div class="auth-page">
    <div class="auth-card auth-card--narrow">
      <div class="auth-form">
        <div class="text-center mb-4">
          <a href="/" aria-label="Back to home">
            <img src="/img/D2.png" alt="DayWright" class="auth-logo_img" />
          </a>
        </div>

        <h2 class="auth-title text-center mb-2">Forgot password</h2>
        <p class="auth-subtitle text-center mb-4">Enter your email to get a reset link.</p>

        <form method="POST" @submit.prevent="resetLink">
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
              autofocus />
            <span class="text-danger font-italic" v-if="errors.email" v-text="errors.email[0]"></span>
          </div>

          <button type="submit" class="btn btn-primary auth-submit w-100">Send reset link</button>
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
      },
    };
  },

  methods: {
    resetLink() {
      axios
        .post('/forgot-password', this.form, {})
        .then(() => {
          this.$vToastify.success('Reset Email sent successfully check your inbox');
        })
        .catch((error) => {
          this.errors = error.response.data.errors;
        });
    },
  },
};
</script>
