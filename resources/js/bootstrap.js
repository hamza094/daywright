import _ from 'lodash';
window._ = _;

import Vue from 'vue';
window.Vue = Vue;

Vue.config.productionTip = false;
Vue.prototype.$user = '';

import * as Popper from '@popperjs/core';

import jQuery from 'jquery';
import 'bootstrap';

window.Popper = Popper;
window.$ = window.jQuery = jQuery;

import axios from 'axios';

window.axios = axios;

// Default headers
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
axios.defaults.headers.common['Accept'] = 'application/json';
axios.defaults.withCredentials = true;
axios.defaults.withXSRFToken = true;
axios.defaults.xsrfCookieName = true;
axios.defaults.xsrfHeaderName = true;

axios.defaults.baseURL = import.meta.env?.VITE_API_BASE_URL || '/api/v1';

/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allows your team to easily build robust real-time web applications.
 */

import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
window.Pusher = Pusher;

const broadcastAuthEndpoint = new URL('/api/broadcasting/auth', window.location.origin).toString();

window.Echo = new Echo({
  broadcaster: 'pusher',
  key: import.meta.env.VITE_PUSHER_APP_KEY,
  cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER,
  encrypted: true,
  forceTLS: false, // true for production
  authorizer: (channel) => ({
    authorize: (socketId, callback) => {
      axios
        .post(broadcastAuthEndpoint, {
          socket_id: socketId,
          channel_name: channel.name,
        })
        .then((response) => {
          callback(false, response.data);
        })
        .catch((error) => {
          callback(true, error);
        });
    },
  }),
});
