<template>
  <div>
    <div class="dropdown mr-5">
      <a href="#" data-toggle="dropdown" class="notification">
        <i class="fa-regular fa-bell notification-icon"></i>
        <span v-if="hasUnreadNotifications" class="notification-count"></span>
      </a>
      <ul class="dropdown-menu dropdown-menu-right rt">
        <div class="notify-link">
          <li class="notification-header">Notifications</li>
          <li v-if="!notifications.length" class="mt-2 mr-4 ml-4">No new notifications</li>

          <li
            v-for="notification in notifications"
            :key="notification.id"
            class="notification-list dropdown-item"
            :class="{ 'notification-unread': !notification.read_at }">
            <router-link
              :to="notification.link.slice(7)"
              @click.native="markAsRead(notification)"
              class="notification-wrapper">
              <img
                v-if="notification.notifier.avatar"
                :src="$options.filters.safeUrl(notification.notifier.avatar)"
                :alt="notification.notifier.name"
                class="notification_avatar" />
              <div class="notification-content">
                <div class="notification-message">
                  <strong>{{ notification.notifier.name }}</strong>
                  {{ notification.message }}
                  <span v-if="!notification.read_at" class="notification-unread_dot"></span>
                </div>
                <small class="notification-time"
                  ><i>{{ notification.created_at }}</i></small
                >
              </div>
            </router-link>
          </li>
          <li v-if="notifications.length" class="notification-footer">
            <router-link to="/user-notifications" class="notification-footer_view-all-links">
              Show All Notifications
            </router-link>
          </li>
        </div>
      </ul>
    </div>
  </div>
</template>

<script>
export default {
  data() {
    return {};
  },
  computed: {
    notifications() {
      return this.$store.state.notifications.notifications.data;
    },
    hasUnreadNotifications() {
      return this.notifications.some((notification) => !notification.read_at);
    },
    user: {
      get() {
        return this.$store.state.currentUser.user;
      },
    },
  },
  created() {
    this.fetchNotifications();
    this.listenNotifications();
  },
  methods: {
    fetchNotifications() {
      this.$store.dispatch('fetchNotifications');
    },
    markAsRead(notification) {
      this.$store.dispatch('markAsRead', notification);
    },
    listenNotifications() {
      Echo.private(`App.Models.User.${this.user.id}`).notification((notification) => {
        this.$vToastify.success('You have one new notification');
        this.$store.commit('addNotification', notification);
      });
    },
  },
};
</script>
