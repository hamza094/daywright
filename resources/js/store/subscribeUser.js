import { getObjectData } from '../utils/apiResponse.js';

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
      .get('/users/me/subscription', {})
      .then((response) => {
        commit('setSubscription', getObjectData(response));
        commit('setErrors', {});
        return response;
      })
      .catch((error) => {
        const safeErrors = error?.response?.data?.errors ?? error?.message ?? 'Unknown error';
        commit('setErrors', safeErrors);
        // Do not re-throw after recording handled errors to avoid unhandled
        // promise rejections in fire-and-forget dispatchers; callers that need
        // to inspect failures should read `subscribeUser.errors` or check the
        // returned value (null indicates a handled failure).
        return null;
      });
  },

  userLogout({ commit }) {
    commit('setSubscription', {});
    commit('setErrors', {});
  },

  // TODO: call API to cancel subscription and update store
  /*deleteSubscription() {
    return Promise.reject(new Error('deleteSubscription not implemented.'));
  },*/
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
