<template>
  <div class="app-root">
    <component
      :is="layoutComponent"
      :user="user"
      :logged-in="loggedIn"
      :subscription="subscription"
      :alert-state="alertState"
      @sign-out="signOut" />
  </div>
</template>
<script>
import { mapState, mapActions, mapGetters } from 'vuex';
import AppLayout from './layouts/AppLayout.vue';
import AuthLayout from './layouts/AuthLayout.vue';

export default {
  name: 'Navbar',
  components: {
    AppLayout,
    AuthLayout,
  },
  computed: {
    ...mapState('currentUser', ['user']),
    ...mapState('subscribeUser', ['subscription']),
    ...mapGetters('subscribeUser', ['isOnTrial', 'isInGracePeriod', 'isPro']),
    loggedIn() {
      return this.$store.state.currentUser.loggedIn;
    },
    alertState() {
      if (!this.loggedIn || !this.subscriptionLoaded) {
        return null;
      }

      if (this.isOnTrial) {
        return 'trial';
      }

      if (this.isInGracePeriod) {
        return 'grace';
      }

      if (!this.isPro) {
        return 'free';
      }

      return null;
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
