import Vue from 'vue';
import Router from 'vue-router';
import store from './store';

Vue.use(Router);

import Home from './components/Home.vue';
import Register from './components/Authentication/Register.vue';
import Login from './components/Authentication/Login.vue';
import TwoFACode from './components/Authentication/TwoFACode.vue';
import OAuth from './components/Authentication/OAuth.vue';
import ZoomAuth from './components/Authentication/ZoomAuth.vue';
import Dashboard from './components/Dashboard/ProjectDashboard.vue';
import ForgotPassword from './components/Authentication/ForgotPassword.vue';
import ResetPassword from './components/Authentication/ResetPassword.vue';
import VerifyPassword from './components/Authentication/VerifyPassword.vue';
import ProjectPage from './components/Project/ProjectPage.vue';
import Activities from './components/Project/Activities.vue';
import Projects from './components/Projects.vue';
import Profile from './components/Profile/ProfilePage.vue';
import Subscription from './components/Subscription.vue';
import AdminPanel from './components/Admin/Dashboard.vue';
import ProjectPanel from './components/Admin/Projects.vue';
import TaskPanel from './components/Admin/Tasks.vue';
import UserPanel from './components/Admin/Users.vue';
import NotFound from './components/Error.vue';
import UserNotification from './components/UserNotification.vue';

const guest = (to, from, next) => {
  if (!store.state.currentUser.loggedIn) {
    return next();
  }
  return next('/home');
};

const auth = (to, from, next) => {
  if (store.state.currentUser.loggedIn) {
    return next();
  }
  return next('/login');
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
    return next('/home');
  }
  return next('/login');
};

let router = new Router({
  mode: 'history',
  linkActiveClass: 'font-semibold',
  routes: [
    {
      path: '*',
      component: NotFound,
    },
    {
      path: '/auth/callback/:provider',
      component: OAuth,
      name: 'OAuth',
      beforeEnter: guest,
    },
    {
      path: '/oauth/zoom/callback',
      component: ZoomAuth,
      name: 'ZoomAuth',
      beforeEnter: auth,
    },
    {
      path: '/home',
      component: Home,
      name: 'Home',
      beforeEnter: auth,
    },
    {
      path: '/admin/panel',
      component: AdminPanel,
      name: 'AdminPanel',
      beforeEnter: auth,
    },
    {
      path: '/admin/projects',
      component: ProjectPanel,
      name: 'ProjectPanel',
      beforeEnter: auth,
    },
    {
      path: '/admin/tasks',
      component: TaskPanel,
      name: 'TaskPanel',
      beforeEnter: auth,
    },
    {
      path: '/admin/users',
      component: UserPanel,
      name: 'UserPanel',
      beforeEnter: auth,
    },
    {
      path: '/register',
      component: Register,
      name: 'Register',
      beforeEnter: guest,
    },
    {
      path: '/login',
      component: Login,
      name: 'Login',
      beforeEnter: guest,
    },
    {
      path: '/2fa/code',
      component: TwoFACode,
      name: 'TwoFACode',
      beforeEnter: twofaGuard,
    },
    {
      path: '/forgot-password',
      component: ForgotPassword,
      name: 'Forgot',
      beforeEnter: guest,
    },
    {
      path: '/api/v1/password/reset/:token',
      component: ResetPassword,
      name: 'Reset',
      beforeEnter: guest,
    },
    {
      path: '/api/v1/email/verify/:user',
      component: VerifyPassword,
      name: 'verification.verify',
    },
    {
      path: '/dashboard',
      component: Dashboard,
      name: 'Dashboard',
      beforeEnter: auth,
    },
    {
      path: '/projects/:slug',
      component: ProjectPage,
      name: 'ProjectPage',
      beforeEnter: auth,
    },
    {
      path: '/project/:name/:slug/activities',
      component: Activities,
      name: 'Activities',
      props: true,
      beforeEnter: auth,
    },
    {
      path: '/projects',
      component: Projects,
      name: 'Projects',
      beforeEnter: auth,
    },
    {
      path: '/user/:uuid/profile',
      component: Profile,
      name: 'Profile',
      beforeEnter: auth,
    },
    {
      path: '/subscriptions',
      component: Subscription,
      name: 'Subscription',
      beforeEnter: auth,
    },
    {
      path: '/user-notifications',
      component: UserNotification,
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
