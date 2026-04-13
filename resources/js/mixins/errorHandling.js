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
  const data = response?.data;

  if (import.meta?.env?.DEV) {
    console.debug('API error response', {
      status: response?.status,
      message: data?.message,
      error: data?.error,
      errors: data?.errors,
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

function showPlanLimitModal(modal, data) {
  if (typeof modal?.show !== 'function') {
    return false;
  }

  modal.show('PlanLimitModal', {
    message: data.message,
    reason: data.reason,
    limitType: data.limit_type,
    limitLabel: data.limit_label,
    currentUsage: data.current_usage,
    maxAllowed: data.max_allowed,
    limitScope: data.limit_scope,
    canUpgrade: data.can_upgrade,
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
  const data = response?.data;

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

  if (data?.error_type === 'plan_limit_exceeded') {
    const surfaced = showPlanLimitModal(modal, data) || showToastError(toast, data.message || 'Plan limit reached.');

    if (!surfaced) {
      return false;
    }

    markGlobalApiHandled(error);

    return true;
  }

  if (data?.error_type === 'subscription_required') {
    const showedToast = showToastError(
      toast,
      data.message || 'An active subscription is required to perform this action.',
    );
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
      const data = error?.response?.data;

      logApiError(error);

      if (isCanceledRequestError(error)) {
        return;
      }

      if (error?.__globalApiHandled) {
        return;
      }

      if (data?.errors) {
        if (this.errors !== undefined) {
          this.errors = data.errors;
        }

        const firstValidationMessage = Object.values(data.errors)
          .flat()
          .find((message) => typeof message === 'string' && message.trim() !== '');

        if (firstValidationMessage) {
          this.$vToastify.error(firstValidationMessage);
          return;
        }

        if (data?.message) {
          this.$vToastify.error(data.message);
          return;
        }

        return;
      }

      if (data?.error) {
        this.$vToastify.error(data.error);
        return;
      }

      if (data?.message) {
        this.$vToastify.error(data.message);
        return;
      }

      this.$vToastify.error('An unexpected error occurred.');
    },
  },
};
