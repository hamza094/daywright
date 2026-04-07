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
