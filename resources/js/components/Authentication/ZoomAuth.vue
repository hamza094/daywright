<template>
  <div class="container">
    <div class="text-center mt-5">
      <h3>Thank you for your patience. We are currently setting up your Zoom credentials</h3>
      <div class="d-flex mt-3 justify-content-center align-items-center">
        <ring-loader :color="color" :size="100" />
      </div>
    </div>
  </div>
</template>

<script>
import { mapState } from 'vuex';
export default {
  name: 'ZoomAuth',
  data() {
    return {
      color: '#301934',
      loading: false,
    };
  },
  computed: {
    ...mapState('currentUser', ['user']),
  },
  mounted() {
    this.zoomAuth();
  },
  methods: {
    zoomAuth() {
      if (this.loading) return;
      this.loading = true;
      const code = this.$route.query.code;
      const state = this.$route.query.state;

      axios
        .get('/oauth/zoom/callback', { params: { code, state } })
        .then((response) => {
          this.$router.push(`/user/${this.user.uuid}/profile`);
          this.$vToastify.success(response.data.success);
        })
        .catch((error) => {
          this.$router.push('/dashboard');
          this.$vToastify.warning(error?.response?.data?.error || 'An error occurred during Zoom authentication.');
        })
        .finally(() => {
          this.loading = false;
        });
    },
  },
};
</script>
