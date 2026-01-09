<template>
  <div class="panel-left panel-drawer">
    <div class="panel-drawer_header">
      <button type="button" class="panel-drawer_close" aria-label="Close menu" @click.prevent="closePanel">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>

    <sidebar-nav-menu :user="user" :logged-in="loggedIn" :on-sign-out="onSignOut" :on-close="closePanel" />
  </div>
</template>

<script>
import SidebarNavMenu from './SidebarNavMenu.vue';

export default {
  name: 'SidebarNavPanel',
  components: {
    SidebarNavMenu,
  },
  props: {
    user: { type: Object, default: null },
    loggedIn: { type: Boolean, default: false },
    onSignOut: { type: Function, default: null },
  },
  methods: {
    closePanel() {
      this.$emit('closePanel', {});
    },
  },
};
</script>
