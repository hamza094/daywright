import { parseApiError } from '../utils/apiResponse.js';

function isCanceledRequestError(error) {
  const message = typeof error?.message === 'string' ? error.message : '';

  return (
    error?.code === 'ERR_CANCELED' || error?.name === 'CanceledError' || /cancelled|canceled|aborted/i.test(message)
  );
}

export function logApiError(error) {
  if (isCanceledRequestError(error)) {
    return;
  }

  const response = error?.response;
  const { payload } = parseApiError(error);

  if (import.meta?.env?.DEV) {
    console.debug('API error response', {
      status: response?.status,
      message: payload?.message,
      code: payload?.code,
      errors: payload?.errors,
      meta: payload?.meta,
    });
  }
}

function markGlobalApiHandled(error) {
  error.__globalApiHandled = true;
}

function showToastError(toast, message) {
  if (typeof toast?.error !== 'function') {
    return false;
  }

  toast.error(message);

  return true;
}

function showPlanLimitModal(modal, message, meta) {
  if (typeof modal?.show !== 'function') {
    return false;
  }

  modal.show('PlanLimitModal', {
    message,
    reason: meta.reason,
    limitType: meta.limit_type,
    limitLabel: meta.limit_label,
    currentUsage: meta.current_usage,
    maxAllowed: meta.max_allowed,
    limitScope: meta.limit_scope,
    canUpgrade: meta.can_upgrade,
  });

  return true;
}

function redirectToSubscription(router) {
  if (typeof router?.push !== 'function' || router?.currentRoute?.name === 'Subscription') {
    return false;
  }

  router.push({ name: 'Subscription' }).catch((err) => {
    if (import.meta?.env?.DEV) {
      console.warn('Navigation to Subscription failed:', err);
    }
  });

  return true;
}

export function handleGlobalApiError(error, { modal, toast, router } = {}) {
  const response = error?.response;
  const { code, message, meta } = parseApiError(error);

  if (isCanceledRequestError(error)) {
    markGlobalApiHandled(error);

    return true;
  }

  if (!response && error?.request) {
    const surfaced = showToastError(toast, 'Unable to reach the server. Please check your connection and try again.');

    if (!surfaced) {
      return false;
    }

    markGlobalApiHandled(error);

    return true;
  }

  if (code === 'plan_limit_exceeded') {
    const surfaced =
      showPlanLimitModal(modal, message, meta) || showToastError(toast, message || 'Plan limit reached.');

    if (!surfaced) {
      return false;
    }

    markGlobalApiHandled(error);

    return true;
  }

  if (code === 'subscription_required') {
    const showedToast = showToastError(toast, message || 'An active subscription is required to perform this action.');
    const redirected = redirectToSubscription(router);
    const surfaced = showedToast || redirected;

    if (!surfaced) {
      return false;
    }

    markGlobalApiHandled(error);

    return true;
  }

  return false;
}

export default {
  methods: {
    handleErrorResponse(error) {
      const { errors: validationErrors, message } = parseApiError(error);

      logApiError(error);

      if (isCanceledRequestError(error)) {
        return;
      }

      if (error?.__globalApiHandled) {
        return;
      }

      if (Object.keys(validationErrors).length > 0) {
        if (this.errors !== undefined) {
          this.errors = validationErrors;
        }

        const firstValidationMessage = Object.values(validationErrors)
          .flat()
          .find((message) => typeof message === 'string' && message.trim() !== '');

        if (firstValidationMessage) {
          this.$vToastify.error(firstValidationMessage);
          return;
        }

        if (message) {
          this.$vToastify.error(message);
          return;
        }

        return;
      }

      if (message) {
        this.$vToastify.error(message);
        return;
      }

      this.$vToastify.error('An unexpected error occurred.');
    },
  },
};
