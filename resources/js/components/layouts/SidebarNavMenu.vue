<template>
  <div class="panel">
    <a href="/" class="panel-brand" aria-label="DayWright home" @click.prevent="goHome">
      <img src="/img/D1.png" class="main-img" alt="DayWright" />
    </a>

    <nav v-if="loggedIn" class="panel-menu" aria-label="Primary navigation">
      <router-link :to="{ name: 'Dashboard' }" class="panel-list_item" @click.native="handleNavigate">
        <span class="icon">
          <i class="icon-logo fa-regular fa-calendar"></i>
          <span class="icon-name">Dashboard</span>
        </span>
      </router-link>

      <router-link :to="{ name: 'Projects' }" class="panel-list_item" @click.native="handleNavigate">
        <span class="icon">
          <i class="icon-logo fa-brands fa-product-hunt"></i>
          <span class="icon-name">Projects</span>
        </span>
      </router-link>

      <div class="panel-list_item panel-list_action">
        <slot name="new-project">
          <a href="" class="panel-list_item" @click.prevent="handleNewProject">
            <span class="icon">
              <i class="icon-logo fa-solid fa-plus-circle"></i>
              <span class="icon-name">New Project</span>
            </span>
          </a>
        </slot>
      </div>

      <router-link
        :to="{ name: 'Profile', params: { uuid: userId } }"
        class="panel-list_item"
        @click.native="handleNavigate">
        <span class="icon">
          <i class="icon-logo fa-solid fa-user-circle"></i>
          <span class="icon-name">Profile</span>
        </span>
      </router-link>

      <router-link :to="{ name: 'Subscription' }" class="panel-list_item" @click.native="handleNavigate">
        <span class="icon">
          <i class="icon-logo fa-regular fa-credit-card"></i>
          <span class="icon-name">Subscription</span>
        </span>
      </router-link>

      <router-link :to="{ name: 'AdminPanel' }" class="panel-list_item" @click.native="handleNavigate">
        <span class="icon">
          <i class="icon-logo fa-solid fa-user-lock"></i>
          <span class="icon-name">Admin Panel</span>
        </span>
      </router-link>

      <a href="" class="panel-list_item" @click.prevent="handleLogout">
        <span class="icon">
          <i class="icon-logo fa-solid fa-sign-out-alt"></i>
          <span class="icon-name">Logout</span>
        </span>
      </a>
    </nav>
  </div>
</template>

<script>
export default {
  name: 'SidebarNavMenu',
  props: {
    user: { type: Object, default: null },
    loggedIn: { type: Boolean, default: false },
    onSignOut: { type: Function, default: null },
    onClose: { type: Function, default: null },
    onNewProject: { type: Function, default: null },
  },
  computed: {
    userId() {
      // prefer `uuid` where available (routes use :uuid), fall back to `id`
      if (!this.user) return null;
      return this.user.uuid || this.user.id || null;
    },
  },
  methods: {
    handleNavigate() {
      if (typeof this.onClose === 'function') {
        this.onClose();
      }
    },
    goHome() {
      this.handleNavigate();
      this.$router.push({ name: 'Home' });
    },
    handleLogout() {
      if (typeof this.onSignOut === 'function') {
        this.onSignOut();
      }
      this.handleNavigate();
    },
    handleNewProject() {
      if (typeof this.onNewProject === 'function') {
        this.onNewProject();
        this.handleNavigate();
        return;
      }

      this.openProjectForm();
    },
    openProjectForm() {
      const panelHandle = this.$showPanel({
        component: 'project-form',
        openOn: 'right',
        width: 540,
        disableBgClick: true,
        keepAlive: true,
        props: {},
      });

      panelHandle.promise.then(() => {});
      this.handleNavigate();
    },
  },
};
</script>
