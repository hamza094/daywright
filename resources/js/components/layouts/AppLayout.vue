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
          <router-link class="navbar-brand link-no-hover" :to="{ name: 'Dashboard' }"><b>DayWright</b></router-link>
          <div class="ml-auto d-flex align-items-right">
            <notifications v-if="loggedIn"></notifications>
          </div>
        </nav>
        <div v-if="showTrialAlert" class="alert alert-info mt-2" role="alert">
          <b>
            Your Pro trial is active
            <template v-if="trialEndsAt">until {{ trialEndsAt }}</template>
            <template v-else>for a limited time</template>.
            <router-link class="link-no-hover" :to="{ name: 'Subscription' }"><span>Subscribe</span></router-link> to
            keep Pro access after it ends.
          </b>
        </div>
        <div v-else-if="showGraceAlert" class="alert alert-warning mt-2" role="alert">
          <b>
            Your Pro access ends on {{ gracePeriodEndsAt }}.
            <router-link class="link-no-hover" :to="{ name: 'Subscription' }"><span>Renew</span></router-link> to keep
            Pro access.
          </b>
        </div>
        <div v-else-if="showFreeAlert" class="alert alert-dark mt-2" role="alert">
          <b>
            You're on the Free plan.
            <router-link class="link-no-hover" :to="{ name: 'Subscription' }"><span>Upgrade to Pro</span></router-link>
            to unlock all features.
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
    subscription: {
      type: Object,
      default: () => ({}),
    },
    alertState: {
      type: String,
      default: null,
    },
    loggedIn: {
      type: Boolean,
      required: true,
    },
  },
  computed: {
    showTrialAlert() {
      return this.loggedIn && this.alertState === 'trial';
    },
    showGraceAlert() {
      return this.loggedIn && this.alertState === 'grace';
    },
    showFreeAlert() {
      return this.loggedIn && this.alertState === 'free';
    },
    trialEndsAt() {
      return this.subscription?.trial?.ends_at || null;
    },
    gracePeriodEndsAt() {
      return this.subscription?.grace_period?.ends_at || 'the end of your grace period';
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
