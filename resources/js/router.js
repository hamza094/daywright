import Vue from 'vue';
import Router from 'vue-router';
import store from './store';

Vue.use(Router);

const guest = (to, from, next) => {
  if (!store.state.currentUser.loggedIn) {
    return next();
  }
  return next('/dashboard');
};

const auth = (to, from, next) => {
  if (store.state.currentUser.loggedIn) {
    return next();
  }
  return next('/login');
};

const admin = (to, from, next) => {
  if (!store.state.currentUser.loggedIn) {
    return next('/login');
  }

  if (store.state.currentUser.user?.is_admin) {
    return next();
  }

  return next('/dashboard');
};

// 2FA guard: only allow if twofa_pending is set and not logged in
const twofaGuard = (to, from, next) => {
  const twofaPending = localStorage.getItem('twofa_pending');
  const twofaTimestamp = localStorage.getItem('twofa_timestamp');

  // Check if 2FA session is expired
  if (twofaTimestamp) {
    const now = Date.now();
    const fiveMinutes = 5 * 60 * 1000;
    if (now - twofaTimestamp > fiveMinutes) {
      // Clear expired 2FA session
      localStorage.removeItem('twofa_pending');
      localStorage.removeItem('twofa_timestamp');
      return next('/login');
    }
  }

  if (twofaPending === 'true') {
    return next();
  }
  if (store.state.currentUser.loggedIn) {
    return next('/dashboard');
  }
  return next('/login');
};

let router = new Router({
  mode: 'history',
  linkActiveClass: 'font-semibold',
  routes: [
    {
      path: '*',
      component: () => import('./components/Error.vue'),
    },
    {
      path: '/auth/callback/:provider',
      component: () => import('./components/Authentication/OAuth.vue'),
      name: 'OAuth',
      beforeEnter: guest,
      meta: { layout: 'auth' },
    },
    {
      path: '/oauth/zoom/callback',
      component: () => import('./components/Authentication/ZoomAuth.vue'),
      name: 'ZoomAuth',
      beforeEnter: auth,
    },
    {
      path: '/admin/panel',
      component: () => import('./components/Admin/Dashboard.vue'),
      name: 'AdminPanel',
      beforeEnter: admin,
    },
    {
      path: '/admin/projects',
      component: () => import('./components/Admin/Projects.vue'),
      name: 'ProjectPanel',
      beforeEnter: admin,
    },
    {
      path: '/admin/tasks',
      component: () => import('./components/Admin/Tasks.vue'),
      name: 'TaskPanel',
      beforeEnter: admin,
    },
    {
      path: '/admin/users',
      component: () => import('./components/Admin/Users.vue'),
      name: 'UserPanel',
      beforeEnter: admin,
    },
    {
      path: '/register',
      component: () => import('./components/Authentication/Register.vue'),
      name: 'Register',
      beforeEnter: guest,
      meta: { layout: 'auth' },
    },
    {
      path: '/login',
      component: () => import('./components/Authentication/Login.vue'),
      name: 'Login',
      beforeEnter: guest,
      meta: { layout: 'auth' },
    },
    {
      path: '/2fa/code',
      component: () => import('./components/Authentication/TwoFACode.vue'),
      name: 'TwoFACode',
      beforeEnter: twofaGuard,
      meta: { layout: 'auth' },
    },
    {
      path: '/forgot-password',
      component: () => import('./components/Authentication/ForgotPassword.vue'),
      name: 'Forgot',
      beforeEnter: guest,
      meta: { layout: 'auth' },
    },
    {
      path: '/api/v1/password/reset/:token',
      component: () => import('./components/Authentication/ResetPassword.vue'),
      name: 'Reset',
      beforeEnter: guest,
      meta: { layout: 'auth' },
    },
    {
      path: '/api/v1/email/verify/:user',
      component: () => import('./components/Authentication/VerifyPassword.vue'),
      name: 'verification.verify',
      meta: { layout: 'auth' },
    },
    {
      path: '/dashboard',
      component: () => import('./components/Dashboard/Dashboard.vue'),
      name: 'Dashboard',
      beforeEnter: auth,
    },
    {
      path: '/projects/:slug',
      component: () => import('./components/Project/ProjectPage.vue'),
      name: 'ProjectPage',
      beforeEnter: auth,
    },
    {
      path: '/project/:name/:slug/activities',
      component: () => import('./components/Project/Activities.vue'),
      name: 'Activities',
      props: true,
      beforeEnter: auth,
    },
    {
      path: '/projects',
      component: () => import('./components/Projects.vue'),
      name: 'Projects',
      beforeEnter: auth,
    },
    {
      path: '/user/:uuid/profile',
      component: () => import('./components/Profile/ProfilePage.vue'),
      name: 'Profile',
      beforeEnter: auth,
    },
    {
      path: '/subscriptions',
      component: () => import('./components/Subscription.vue'),
      name: 'Subscription',
      beforeEnter: auth,
    },
    {
      path: '/user-notifications',
      component: () => import('./components/UserNotification.vue'),
      name: 'UserNotification',
      beforeEnter: auth,
    },
  ],
});

router.beforeEach((to, from, next) => {
  if (document.getElementById('zoom-sdk-video-canvas')) {
    const userConfirmed = globalThis.confirm(
      'Are you sure you want to leave this page? Your video session may be interrupted.',
    );
    if (userConfirmed) {
      next();
    } else {
      next(false);
    }
  } else {
    next();
  }
});

export default router;
