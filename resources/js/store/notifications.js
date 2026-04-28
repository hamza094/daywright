const state = {
  notifications: { data: [], links: {}, meta: {} },
  allNotifications: {
    data: [],
    links: {},
    meta: {},
  },
};

const mutations = {
  setNotifications(state, payload) {
    state.notifications = payload; // Assign the entire response object
  },

  setAllNotifications(state, payload) {
    state.allNotifications = payload; // Assign the entire response object
  },

  addNotification(state, notification) {
    state.notifications.data.unshift(notification); // Add to the beginning of the `data` array
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
      page: 1,
      mutation: 'setNotifications',
    });
  },

  async getAllNotifications({ dispatch }, { filter = null, page = 1 } = {}) {
    return dispatch('fetchNotificationsFromApi', {
      filter,
      page,
      mutation: 'setAllNotifications',
    });
  },

  async fetchNotificationsFromApi({ commit }, { filter = null, page = 1, mutation }) {
    const axiosParams = {};
    if (filter) axiosParams.filter = filter;
    if (page) axiosParams.page = page;

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
    return axios.patch('/notifications/read').then(() => {
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

export default {
  state,
  mutations,
  actions,
};
