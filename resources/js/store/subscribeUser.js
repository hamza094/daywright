const state = {
  subscription: {},
  errors: {},
};

const getters = {
  isPro(state) {
    return state.subscription.entitled === true;
  },

  isOnTrial(state) {
    return state.subscription?.trial?.active === true;
  },

  isInGracePeriod(state) {
    return state.subscription?.grace_period?.active === true;
  },

  isFreeUser(state, localGetters) {
    return localGetters.plan === 'free' && !localGetters.isOnTrial && !localGetters.isInGracePeriod;
  },

  isActivelyBilling(state) {
    return state.subscription.subscribed === true;
  },

  plan(state) {
    return state.subscription.plan || 'free';
  },

  accountLimits(state) {
    return Array.isArray(state.subscription.limits) ? state.subscription.limits : [];
  },
};

const actions = {
  userSubscription({ commit }) {
    return axios
      .get('/user/subscriptions', {})
      .then((response) => {
        commit('setSubscription', response.data.subscription);
        commit('setErrors', '');
        return response;
      })
      .catch((error) => {
        const safeErrors = error?.response?.data?.errors ?? error?.message ?? 'Unknown error';
        commit('setErrors', safeErrors);
        // Re-throw so callers can chain if they need to
        throw error;
      });
  },

  userLogout({ commit }) {
    commit('setSubscription', {});
  },

  // TODO: call API to cancel subscription and update store
  deleteSubscription() {
    return Promise.reject(new Error('deleteSubscription not implemented.'));
  },
};

const mutations = {
  setErrors(state, data) {
    state.errors = data;
  },

  setSubscription(state, data) {
    state.subscription = data || {};
  },
};

export default {
  namespaced: true,
  state,
  getters,
  actions,
  mutations,
};
