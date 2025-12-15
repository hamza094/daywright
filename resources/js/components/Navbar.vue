<template>
  <div class="container-fluid">
    <div class="row">
      <div class="col-md-1 panel-left">
        <div class="panel">
          <a href="/"><img src="/img/D1.png" class="main-img" alt="" /></a>

          <div v-if="loggedIn">
            <router-link :to="{ name: 'Dashboard' }" class="panel-list_item">
              <p>
                <span class="icon">
                  <i class="icon-logo fa-regular fa-calendar"></i>
                  <span class="icon-name">Dashboard</span>
                </span>
              </p>
            </router-link>

            <router-link :to="{ name: 'Projects' }" class="panel-list_item">
              <p>
                <span class="icon">
                  <i class="icon-logo fa-brands fa-product-hunt"></i>
                  <span class="icon-name">Projects</span>
                </span>
              </p>
            </router-link>

            <project-button></project-button>

            <router-link :to="{ name: 'Profile', params: { uuid: user.id } }" class="panel-list_item">
              <p>
                <span class="icon">
                  <i class="icon-logo fa-solid fa-user-circle"></i>
                  <span class="icon-name">Profile</span>
                </span>
              </p>
            </router-link>

            <router-link :to="{ name: 'Subscription' }" class="panel-list_item">
              <p>
                <span class="icon">
                  <i class="icon-logo fa-regular fa-credit-card"></i>
                  <span class="icon-name">Subsctiption</span>
                </span>
              </p>
            </router-link>

            <router-link :to="{ name: 'AdminPanel' }" class="panel-list_item">
              <p>
                <span class="icon">
                  <i class="icon-logo fa-solid fa-user-lock"></i>
                  <span class="icon-name">Admin Panel</span>
                </span>
              </p>
            </router-link>

            <a href="" @click.prevent="signOut()" class="panel-list_item">
              <p>
                <span class="icon"
                  ><i class="icon-logo fa-solid fa-sign-out-alt"></i><span class="icon-name">Logout</span></span
                >
              </p>
            </a>
          </div>
        </div>
      </div>

      <div class="col-md-11 panel-right">
        <vue-progress-bar></vue-progress-bar>

        <nav class="navbar navbar-expand-md navbar-light bg-white">
          <router-link class="navbar-brand" :to="{ name: 'Home' }"><b>DayWright</b></router-link>

          <button
            class="navbar-toggler"
            type="button"
            data-toggle="collapse"
            data-target="#navbarSupportedContent"
            aria-controls="navbarSupportedContent"
            aria-expanded="false"
            aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
          </button>
          <div class="collapse navbar-collapse" id="navbarSupportedContent">
            <!-- Left Side Of Navbar -->
            <ul class="navbar-nav mr-auto"></ul>
            <!-- Right Side Of Navbar -->
            <ul class="navbar-nav ml-auto">
              <!-- Authentication Links -->
              <li class="nav-item" v-if="!loggedIn">
                <router-link class="nav-link" :to="{ name: 'Login' }">Sign In</router-link>
              </li>
              <li class="nav-item" v-if="!loggedIn">
                <router-link class="nav-link" :to="{ name: 'Register' }">Sign Up</router-link>
              </li>
              <notifications class="mr-3" v-if="loggedIn"></notifications>
            </ul>
          </div>
        </nav>
        <div v-if="loggedIn && showAlertNotice" class="alert alert-dark mt-2" role="alert">
          <b>
            Upgrade your experience now!
            <router-link :to="{ name: 'Subscription' }"><span>Subscribe</span></router-link> now to unlock all features.
          </b>
        </div>
        <router-view> </router-view>
      </div>
    </div>
  </div>
</template>
<script>
import { mapState, mapActions } from 'vuex';

export default {
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
