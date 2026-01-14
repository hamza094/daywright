<template>
  <div class="auth-page">
    <div class="auth-card auth-card-narrow">
      <div class="auth-form">
        <div class="text-center mb-4">
          <a href="/" aria-label="Back to home">
            <img src="/img/D2.png" alt="DayWright" class="auth-logo_img" />
          </a>
        </div>

        <div v-if="status === 'enabled'" class="text-center">
          <h2 class="auth-title mb-2">Two-factor enabled</h2>
          <p class="auth-subtitle mb-4">You are all set. Continue to your dashboard.</p>
          <button class="btn btn-primary auth-submit w-100 mb-1" @click="$router.push('/dashboard')">
            Go to dashboard
          </button>
        </div>

        <div v-else>
          <h2 class="auth-title text-center mb-2">Two-factor code</h2>
          <p class="auth-subtitle text-center mb-4">Enter the 6-digit code from your authenticator app.</p>
          <form @submit.prevent="submitCode">
            <div class="form-group mb-3">
              <input
                type="text"
                v-model="code"
                class="form-control text-center"
                maxlength="6"
                placeholder="6-digit code"
                autocomplete="one-time-code"
                required
                autofocus />
            </div>
            <div v-if="error" class="text-danger text-center small mb-3">{{ error }}</div>
            <button type="submit" class="btn btn-primary auth-submit w-100" :disabled="loading">
              <span v-if="loading" class="spinner-border spinner-border-sm mr-1" role="status"></span>
              Verify code
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { mapActions } from 'vuex';
export default {
  data() {
    return {
      code: '',
      loading: false,
      error: '',
      status: '',
    };
  },
  async mounted() {
    await this.fetch2FAStatus();
  },
  methods: {
    ...mapActions('currentUser', ['twoFactorLogin']),
    async submitCode() {
      this.loading = true;
      this.error = '';
      try {
        await this.twoFactorLogin({ code: this.code, vm: this });
        await this.fetch2FAStatus();
      } catch (e) {
        if (e.response?.status === 422) {
          this.error = e.response?.data?.errors?.code?.[0] || 'Invalid code format';
        } else if (e.response?.status === 401) {
          this.error = 'Session expired. Please login again.';
        } else {
          this.error = 'Network error. Please try again.';
        }
      } finally {
        this.loading = false;
      }
    },
    async fetch2FAStatus() {
      try {
        const res = await this.$axios.get('/twofactor/fetch-user');
        this.status = res.data.status;
      } catch {
        this.status = '';
      }
    },
  },
};
</script>

<style scoped>
input.form-control {
  font-size: 1.2rem;
  letter-spacing: 0.2em;
}
</style>
