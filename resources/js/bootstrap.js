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

axios.defaults.baseURL = import.meta.env?.VITE_API_BASE_URL || '/api/v1';

// Read CSRF token from the meta tag instead of relying on a global script
const tokenMeta = document.head.querySelector('meta[name="csrf-token"]');

if (tokenMeta) {
  axios.defaults.headers.common['X-CSRF-TOKEN'] = tokenMeta.content;
} else {
  console.error('CSRF token meta tag not found: <meta name="csrf-token" content="...">');
}

/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allows your team to easily build robust real-time web applications.
 */

import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
window.Pusher = Pusher;

const appUrl = import.meta.env?.VITE_APP_URL;
const broadcastAuthEndpoint = appUrl ? `${appUrl.replace(/\/$/, '')}/api/broadcasting/auth` : '/api/broadcasting/auth';

window.Echo = new Echo({
  broadcaster: 'pusher',
  key: import.meta.env.VITE_PUSHER_APP_KEY,
  cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER,
  encrypted: true,
  forceTLS: false, // true for production
  authEndpoint: broadcastAuthEndpoint,
  auth: {
    headers: {
      Accept: 'application/json',
    },
    withCredentials: true,
  },
});
