<template>
  <div class="app-root">
    <component
      :is="layoutComponent"
      :user="user"
      :logged-in="loggedIn"
      :show-alert-notice="showAlertNotice"
      @sign-out="signOut" />
  </div>
</template>
<script>
import { mapState, mapActions } from 'vuex';
import AppLayout from './layouts/AppLayout.vue';
import AuthLayout from './layouts/AuthLayout.vue';

export default {
  components: {
    AppLayout,
    AuthLayout,
  },
  computed: {
    ...mapState('currentUser', ['user']),
    ...mapState('subscribeUser', ['subscription']),
    loggedIn() {
      return this.$store.state.currentUser.loggedIn;
    },
    showAlertNotice() {
      return this.loggedIn && this.subscriptionLoaded && !this.subscription.subscribed;
    },
    subscriptionLoaded() {
      return Object.keys(this.subscription).length !== 0;
    },
    layoutComponent() {
      return this.$route.meta.layout === 'auth' ? 'AuthLayout' : 'AppLayout';
    },
  },
  methods: {
    ...mapActions('subscribeUser', ['userLogout']),
    signOut() {
      this.$store.dispatch('currentUser/logoutUser').then(() => {
        this.userLogout();
      });
    },
  },
};
</script>
