<template>
  <div class="auth-page">
    <div class="auth-card auth-card-narrow">
      <div class="auth-form text-center">
        <div class="text-center mb-4">
          <a href="/" aria-label="Back to home">
            <img src="/img/D2.png" alt="DayWright" class="auth-logo_img" />
          </a>
        </div>

        <h2 class="auth-title text-center mb-2">Account verification</h2>
        <p class="auth-subtitle text-center mb-4">We are finishing up your setup.</p>

        <div v-if="success" class="alert alert-success mb-3" role="alert">
          Your account has been verified. Please log in to continue.
        </div>
        <div v-else-if="error === 'verification.already_verified'" class="alert alert-info mb-3" role="alert">
          Account already verified. Please log in to continue.
        </div>
        <div v-else-if="error === 'verification.invalid'" class="alert alert-danger mb-3" role="alert">
          Verification error. Please log in to request a new link.
        </div>
        <div v-else class="text-muted mb-3">Completing verification...</div>

        <router-link class="btn btn-primary auth-submit w-100" to="/login">Go to sign in</router-link>
      </div>
    </div>
  </div>
</template>

<script>
// Use axios `params` for query encoding instead of manual builder
export default {
  async beforeRouteEnter(to, from, next) {
    try {
      const { data } = await axios.post(`/email/verify/${encodeURIComponent(to.params.user)}`, null, {
        params: to.query,
      });
      next((vm) => {
        vm.success = data.status;
        vm.$store.dispatch('currentUser/updateVerifiedStatus', true);
      });
    } catch (e) {
      next((vm) => {
        vm.error = e.response?.data?.status || 'verification.invalid';
      });
    }
  },
  data: () => ({
    error: '',
    success: '',
  }),
};
</script>
