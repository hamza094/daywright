import Vue from 'vue';
import Vuex from 'vuex';

import createPersistedState from 'vuex-persistedstate';
import currentUser from './currentUser';
import subscribeUser from './subscribeUser';
import profile from './profile';
import project from './project';
import task from './task';
import SingleTask from './SingleTask';
import stage from './stage';
import status from './status';
import meeting from './meeting';
import conversations from './conversations';
import notifications from './notifications';

Vue.use(Vuex);

export default new Vuex.Store({
  plugins: [createPersistedState()],
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
    conversations,
    notifications,
  },
});
