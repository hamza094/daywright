import { readErrorMessage } from './apiResponse.js';

// Small, reusable auth-related helpers for Vue/Vuex code

export const twoFaKeys = ['twofa_pending', 'twofa_timestamp'];

export const clearTwoFaState = () => {
  try {
    twoFaKeys.forEach((key) => localStorage.removeItem(key));
  } catch (err) {
    // Intentionally swallow the error but log it for local debugging.
    // Keep logs out of production consoles.
    if (process.env.NODE_ENV !== 'production') {
      console.debug('utils/auth.js: clearTwoFaState error', err);
    }
  }
};

export const markTwoFaPending = () => {
  try {
    localStorage.setItem('twofa_pending', 'true');
    localStorage.setItem('twofa_timestamp', Date.now());
  } catch (err) {
    // Intentionally swallow the error but log it for local debugging.
    if (process.env.NODE_ENV !== 'production') {
      console.debug('utils/auth.js: markTwoFaPending error', err);
    }
  }
};

// Accepts either a component instance (vm) or a plain router instance via appRouter
// and pushes any valid "to" location (string or object)
export const pushRoute = (vm, to, appRouter) => {
  const r = vm?.$router || appRouter;
  if (!r || !to) return;
  try {
    r.push(to);
  } catch (err) {
    // Intentionally swallow the error but log it for local debugging.
    if (process.env.NODE_ENV !== 'production') {
      console.debug('utils/auth.js: pushRoute error', err);
    }
  }
};

export const getErrorMessage = (error, fallback = 'An unexpected error occurred.') => {
  return readErrorMessage(error, fallback);
};
