const defaultMeta = () => ({
  per_page: 25,
  next_cursor: null,
  prev_cursor: null,
  has_more: false,
});

const defaultPage = () => ({
  data: [],
  meta: defaultMeta(),
});

const state = {
  notifications: defaultPage(),
  allNotifications: defaultPage(),
};

const mutations = {
  setNotifications(state, payload) {
    state.notifications = normalizeNotificationsPayload(payload);
  },

  setAllNotifications(state, payload) {
    state.allNotifications = normalizeNotificationsPayload(payload);
  },

  addNotification(state, notification) {
    state.notifications = {
      ...state.notifications,
      data: [normalizeNotification(notification), ...state.notifications.data],
    };
  },

  updateNotification(state, updated) {
    replaceNotification(state.notifications.data, updated);
  },

  updateAllNotification(state, updated) {
    replaceNotification(state.allNotifications.data, updated);
  },

  deleteNotification(state, notificationId) {
    const updatedData = state.notifications.data.filter((n) => n.id !== notificationId);
    state.notifications = { ...state.notifications, data: updatedData };
  },

  deleteAllNotification(state, notificationId) {
    const updatedData = state.allNotifications.data.filter((n) => n.id !== notificationId);
    state.allNotifications = { ...state.allNotifications, data: updatedData };
  },
};

const actions = {
  async fetchNotifications({ dispatch }, { filter = null } = {}) {
    return dispatch('fetchNotificationsFromApi', {
      filter,
      mutation: 'setNotifications',
    });
  },

  async getAllNotifications({ dispatch }, { filter = null, cursor = null } = {}) {
    return dispatch('fetchNotificationsFromApi', {
      filter,
      cursor,
      mutation: 'setAllNotifications',
    });
  },

  async fetchNotificationsFromApi({ commit }, { filter = null, cursor = null, mutation }) {
    const axiosParams = {};
    if (filter) axiosParams.filter = filter;
    if (cursor) axiosParams.cursor = cursor;

    const { data } = await axios.get('/notifications', { params: axiosParams });
    commit(mutation, data);
  },

  deleteNotification({ commit }, notificationId) {
    return axios.delete(`/notifications/${encodeURIComponent(notificationId)}`).then(() => {
      commit('deleteNotification', notificationId);
      commit('deleteAllNotification', notificationId);
    });
  },

  markAsRead({ commit }, notification) {
    return axios.patch(`/notifications/${notification.id}/status`, { status: 'read' }).then(() => {
      commit('updateNotification', { ...notification, read_at: new Date().toISOString() });
      commit('updateAllNotification', { ...notification, read_at: new Date().toISOString() });
    });
  },

  markAsUnread({ commit }, notification) {
    return axios.patch(`/notifications/${notification.id}/status`, { status: 'unread' }).then(() => {
      commit('updateNotification', { ...notification, read_at: null });
      commit('updateAllNotification', { ...notification, read_at: null });
    });
  },

  markAllAsRead({ commit, state }) {
    return axios.get('/notifications/mark-all-read').then(() => {
      // Update the `read_at` field for all notifications in the current page
      const updatedNotifications = {
        ...state.notifications,
        data: state.notifications.data.map((n) => ({ ...n, read_at: new Date().toISOString() })),
      };
      commit('setNotifications', updatedNotifications);

      // If `allNotifications` exists, update it as well
      if (state.allNotifications.data.length) {
        const updatedAllNotifications = {
          ...state.allNotifications,
          data: state.allNotifications.data.map((n) => ({ ...n, read_at: new Date().toISOString() })),
        };
        commit('setAllNotifications', updatedAllNotifications);
      }
    });
  },
};

function replaceNotification(array, updated) {
  const index = array.findIndex((n) => n.id === updated.id);
  if (index !== -1) array.splice(index, 1, updated);
}

function normalizeNotificationsPayload(payload = {}) {
  return {
    ...defaultPage(),
    ...payload,
    data: Array.isArray(payload.data) ? payload.data.map(normalizeNotification) : [],
    meta: {
      ...defaultMeta(),
      ...(payload.meta || {}),
    },
  };
}

function normalizeNotification(notification = {}) {
  const notifier = notification.notifier || {};

  return {
    ...notification,
    read_at: notification.read_at ?? null,
    created_at: notification.created_at ?? 'just now',
    notifier: {
      ...notifier,
      avatar: notifier.avatar ?? notifier.avatar_path ?? null,
    },
  };
}

export default {
  state,
  mutations,
  actions,
};
