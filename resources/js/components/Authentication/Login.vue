<template>
  <div class="auth-page">
    <div class="auth-card">
      <div class="row g-0 align-items-stretch">
        <div class="col-lg-6 d-none d-lg-flex auth-visual">
          <img src="/img/sign_in.svg" alt="Sign in illustration" class="auth-visual_img" />
        </div>
        <div class="col-lg-6 col-12 auth-form">
          <div class="auth-logo text-center mb-4">
            <a href="/" aria-label="Back to welcome">
              <img src="/img/D2.png" alt="DayWright" class="auth-logo_img" />
            </a>
          </div>
          <h2 class="auth-title text-center mb-2">Hello again!</h2>
          <p class="auth-subtitle text-center mb-4">Welcome back — sign in to continue.</p>

          <form method="POST" @submit.prevent="login()">
            <div class="form-group mb-3 mr-2">
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
                  v-model="user.email"
                  required
                  autocomplete="email"
                  autofocus />
              </div>
              <span class="text-danger font-italic" v-if="errors.email" v-text="errors.email[0]"></span>
            </div>

            <div class="form-group mb-3 mr-2">
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
                  v-model="user.password"
                  required
                  autocomplete="current-password" />
              </div>
              <span class="text-danger font-italic" v-if="errors.password" v-text="errors.password[0]"></span>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-3">
              <div class="form-check ml-2">
                <input class="form-check-input" type="checkbox" name="remember" v-model="user.remember" id="remember" />
                <label class="form-check-label form-label" for="remember"> Remember me </label>
              </div>
              <router-link class="auth-link mr-2" to="/forgot-password">Forgot password?</router-link>
            </div>

            <button type="submit" class="btn btn-primary auth-submit w-100 mb-3">Sign In</button>

            <div class="auth-divider">or continue with</div>

            <div class="d-grid gap-2 auth-social">
              <button class="btn btn-outline-dark w-100" type="button" @click="loginWithProvider('github')">
                <i class="fa-brands fa-github fa-lg me-2"></i>
                Github
              </button>
              <button class="btn btn-outline-dark w-100" type="button" @click="loginWithProvider('google')">
                <i class="fa-brands fa-google me-2" aria-hidden="true"></i>
                Google
              </button>
            </div>

            <p class="auth-small text-center mt-4 mb-0">
              Don’t have an account?
              <router-link class="auth-link" to="/register">Sign up</router-link>
            </p>
          </form>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { mapActions } from 'vuex';

export default {
  beforeRouteLeave(to, from, next) {
    this.$store.commit('currentUser/clearErrors');
    next();
  },
  data() {
    return {
      user: {
        email: '',
        password: '',
        remember: '',
      },
    };
  },
  computed: {
    errors() {
      return this.$store.state.currentUser.errors;
    },
  },
  methods: {
    ...mapActions('currentUser', ['loginUser']),
    login() {
      this.loginUser(this.user);
    },
    loginWithProvider(provider) {
      axios
        .get(`/auth/redirect/${provider}`)
        .then((response) => {
          window.location.href = response.data.redirect_url;
        })
        .catch((error) => {
          console.error(error);
        });
    },
  },
};
</script>
