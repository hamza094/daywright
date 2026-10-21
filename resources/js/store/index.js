import Vue from 'vue';
import Vuex from 'vuex';

import createPersistedState from 'vuex-persistedstate';
import currentUser from './currentUser';
import subscribeUser from './subscribeUser.js';
import profile from './profile';
import project from './project';
import task from './task';
import SingleTask from './SingleTask';
import stage from './stage';
import status from './status';
import meeting from './meeting';
import notifications from './notifications';

Vue.use(Vuex);

// Purge legacy persisted state to prevent cross-account data leakage
// createPersistedState with paths: [] prevents writing new data,
// but existing localStorage.getItem('vuex') is still read on app load
try {
  localStorage.removeItem('vuex');
} catch {
  // Ignore storage errors in restricted browser contexts (Safari private browsing, etc.)
}

export default new Vuex.Store({
  plugins: [
    createPersistedState({
      paths: [], // Disable persistence to prevent cross-account data leakage
    }),
  ],
  modules: {
    currentUser,
    subscribeUser,
    profile,
    project,
    task,
    SingleTask,
    stage,
    status,
    meeting,
    notifications,
  },
});
