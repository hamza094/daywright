<template>
  <div class="panel-left">
    <sidebar-nav-menu
      :user="user"
      :loggedIn="loggedIn"
      :onSignOut="onSignOut"
      :onClose="closePanel"
    />
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
