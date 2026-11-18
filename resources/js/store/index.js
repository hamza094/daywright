import Vue from 'vue';
import Vuex from 'vuex';

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
try {
  localStorage.removeItem('vuex');
} catch {
  // Ignore storage errors in restricted browser contexts (Safari private browsing, etc.)
}

export default new Vuex.Store({
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
