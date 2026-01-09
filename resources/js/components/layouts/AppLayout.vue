<template>
  <div class="container-fluid">
    <div class="row">
      <div class="col-lg-2 panel-left d-none d-lg-block">
        <sidebar-nav-menu :user="user" :logged-in="loggedIn" :on-sign-out="handleSignOut">
          <template #new-project>
            <project-button></project-button>
          </template>
        </sidebar-nav-menu>
      </div>

      <div class="col-12 col-lg-10 panel-right">
        <vue-progress-bar></vue-progress-bar>

        <nav class="navbar navbar-expand-md navbar-light bg-white">
          <button
            v-if="loggedIn"
            class="btn nav-btn d-lg-none mr-2"
            type="button"
            aria-label="Open menu"
            @click.prevent="openSidebarDrawer">
            <i class="fa-solid fa-bars"></i>
          </button>
          <router-link class="navbar-brand" :to="{ name: 'Home' }"><b>DayWright</b></router-link>
          <div class="ml-auto d-flex align-items-right">
            <notifications v-if="loggedIn"></notifications>
          </div>
        </nav>
        <div v-if="loggedIn && showAlertNotice" class="alert alert-dark mt-2" role="alert">
          <b>
            Upgrade your experience now!
            <router-link :to="{ name: 'Subscription' }"><span>Subscribe</span></router-link> now to unlock all features.
          </b>
        </div>
        <router-view />
      </div>
    </div>
  </div>
</template>

<script>
import SidebarNavMenu from './SidebarNavMenu.vue';

export default {
  name: 'AppLayout',
  components: {
    SidebarNavMenu,
  },
  props: {
    user: {
      type: Object,
      required: true,
    },
    showAlertNotice: {
      type: Boolean,
      default: false,
    },
    loggedIn: {
      type: Boolean,
      required: true,
    },
  },
  methods: {
    openSidebarDrawer() {
      if (!this.loggedIn) return;

      const panelHandle = this.$showPanel({
        component: 'sidebar-nav-panel',
        openOn: 'left',
        width: 300,
        disableBgClick: false,
        keepAlive: true,
        props: {
          user: this.user,
          loggedIn: this.loggedIn,
          onSignOut: () => this.handleSignOut(),
        },
      });

      panelHandle.promise.then(() => {});
    },
    handleSignOut() {
      this.$emit('sign-out');
    },
  },
};
</script>
