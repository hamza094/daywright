<template>
  <div class="container mt-5 d-flex justify-content-center align-items-center" style="min-height: 60vh">
    <div class="card shadow-sm p-4" style="max-width: 400px; width: 100%">
      <div v-if="status === 'enabled'">
        <h5 class="mb-3 text-center text-success">✅ 2FA is enabled on your account.</h5>
        <button class="btn btn-primary w-100 mb-2" @click="$router.push('/dashboard')">Go to Dashboard</button>
      </div>
      <div v-else>
        <h5 class="mb-3 text-center">Enter Two-Factor Authentication Code</h5>
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
          <button type="submit" class="btn btn-primary w-100" :disabled="loading">
            <span v-if="loading" class="spinner-border spinner-border-sm mr-1" role="status"></span>
            Verify
          </button>
          <div v-if="error" class="text-danger mt-3 text-center small">{{ error }}</div>
        </form>
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
.card {
  border-radius: 8px;
}
input.form-control {
  font-size: 1.2rem;
  letter-spacing: 0.2em;
}
</style>
