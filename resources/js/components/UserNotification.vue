<template>
  <div class="container mt-4 user-notifications">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h1>Notifications</h1>
      <div class="dropdown">
        <button
          class="btn btn-link"
          type="button"
          id="dropdownSettings"
          data-toggle="dropdown"
          aria-haspopup="true"
          aria-expanded="false">
          <i class="fa-solid fa-cog"></i>
        </button>
        <div class="dropdown-menu dropdown-menu-right" aria-labelledby="dropdownSettings">
          <button class="dropdown-item" @click="markAllAsRead">Mark all as read</button>
          <button class="dropdown-item">Notification settings</button>
        </div>
      </div>
    </div>

    <div class="btn-group mb-3" role="group">
      <button
        type="button"
        class="btn"
        :class="filter === 'all' || !filter ? 'btn-primary' : 'btn-outline-primary'"
        @click="filterNotifications('all')">
        All
      </button>
      <button
        type="button"
        class="btn"
        :class="filter === 'unread' ? 'btn-primary' : 'btn-outline-primary'"
        @click="filterNotifications('unread')">
        Unread
      </button>
    </div>

    <ul class="list-group">
      <li
        v-for="notification in notifications.data"
        :key="notification.id"
        class="list-group-item d-flex justify-content-between align-items-center"
        :class="{ 'notification-unread': !notification.read_at }">
        <div class="d-flex align-items-center">
          <img
            v-if="notification.notifier.avatar"
            :src="$options.filters.safeUrl(notification.notifier.avatar)"
            alt="Avatar"
            class="rounded-circle mr-3"
            style="width: 40px; height: 40px" />
          <div class="notification-user_content">
            <router-link :to="notification.link.slice(7)" class="text-decoration-none">
              <p class="mb-1">
                <strong>{{ notification.notifier.name }}</strong>
                {{ notification.message }}
                <span v-if="!notification.read_at" class="notification-unread_dot"></span>
              </p>
            </router-link>
            <small class="text-muted">{{ notification.created_at }}</small>
          </div>
        </div>
        <div class="dropdown">
          <button
            class="btn btn-link"
            type="button"
            id="dropdownMenuButton"
            data-toggle="dropdown"
            aria-haspopup="true"
            aria-expanded="false">
            <i class="fa-solid fa-cog"></i>
          </button>
          <div class="dropdown-menu dropdown-menu-right" aria-labelledby="dropdownMenuButton">
            <button v-if="notification.read_at" class="dropdown-item" @click="markAsUnread(notification)">
              Mark as unread
            </button>
            <button v-else class="dropdown-item" @click="markAsRead(notification)">Mark as read</button>
            <button class="dropdown-item text-danger" @click="deleteNotification(notification.id)">Delete</button>
          </div>
        </div>
      </li>
    </ul>

    <pagination :data="notifications" @pagination-change-page="getResults"></pagination>
  </div>
</template>

<script>
export default {
  data() {
    return {
      filter: 'all',
    };
  },
  computed: {
    notifications() {
      return this.$store.state.notifications.allNotifications;
    },
  },
  created() {
    this.getResults(1);
  },
  methods: {
    getResults(page) {
      this.$store.dispatch('getAllNotifications', { filter: this.filter, page });
    },
    deleteNotification(notificationId) {
      this.$store.dispatch('deleteNotification', notificationId);
    },
    markAsRead(notification) {
      this.$store.dispatch('markAsRead', notification);
    },
    markAsUnread(notification) {
      this.$store.dispatch('markAsUnread', notification);
    },
    markAllAsRead() {
      this.$store
        .dispatch('markAllAsRead')
        .then(() => {
          this.$vToastify.success('All notifications marked as read.');
        })
        .catch((error) => {
          this.$vToastify.error('Failed to mark all notifications as read.');
          console.error(error);
        });
    },
    filterNotifications(type) {
      this.filter = type;
      this.getResults(1);
    },
  },
};
</script>
