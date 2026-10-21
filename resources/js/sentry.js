import * as Sentry from '@sentry/vue';

const VALID_ENVIRONMENTS = ['development', 'staging', 'production'];
const rawEnv = import.meta.env.VITE_APP_ENV || import.meta.env.MODE;
const sentryEnvironment = VALID_ENVIRONMENTS.includes(rawEnv) ? rawEnv : import.meta.env.MODE;
const isMonitored = import.meta.env.PROD || Boolean(import.meta.env.VITE_SENTRY_DSN);
const isProduction = sentryEnvironment === 'production';

/**
 * Initialize Sentry error monitoring
 * Only initializes in production or when VITE_SENTRY_DSN is set
 */
export function initSentry(Vue, router) {
  if (isMonitored && import.meta.env.VITE_SENTRY_DSN) {
    Sentry.init({
      Vue,
      dsn: import.meta.env.VITE_SENTRY_DSN,
      environment: sentryEnvironment,
      integrations: [
        Sentry.browserTracingIntegration({ router }),
        Sentry.replayIntegration({
          maskAllText: true,
          blockAllMedia: true,
        }),
      ],
      // 100% traces in staging for QA; 20% in production to save quota
      tracesSampleRate: isProduction ? 0.2 : 1.0,
      tracePropagationTargets: ['localhost', /^https:\/\/.*\.daywright\.com\/api/],

      // 100% error replays in staging; conservative in production
      replaysSessionSampleRate: isProduction ? 0.05 : 0.1,
      replaysOnErrorSampleRate: isProduction ? 0.5 : 1.0,
      ignoreErrors: [
        // Harmless browser engine layout loop notifications
        'ResizeObserver loop completed with undelivered notifications.',
        'ResizeObserver loop limit exceeded',
        // Vue Router redundant navigation
        'NavigationDuplicated',
        /NavigationDuplicated: Avoided redundant navigation/,
        // Browser extension injection errors
        /^chrome-extension:\/\//i,
        /^moz-extension:\/\//i,
        'top.GLOBALS',
        // Stale asset chunk loading (user has old index open while new build deployed)
        /Loading (CSS )?chunk \d+ failed/i,
        /Failed to fetch dynamically imported module/i,
      ],
      beforeSend(event, hint) {
        if (event.exception) {
          event.contexts = {
            ...event.contexts,
            vue: {
              componentName: hint?.componentName || 'Unknown',
            },
          };
        }
        return event;
      },
      beforeBreadcrumb(breadcrumb) {
        // Redact sensitive payload bodies from XHR/Fetch breadcrumbs
        if (breadcrumb.category === 'xhr' || breadcrumb.category === 'fetch') {
          if (breadcrumb.data) {
            delete breadcrumb.data.body;
            delete breadcrumb.data.response;
          }
        }
        return breadcrumb;
      },
    });
  }
}

/**
 * Global Vue error handler
 * Captures Vue component errors and reports to Sentry
 */
export function setupVueErrorHandler(Vue) {
  Vue.config.errorHandler = (err, vm, info) => {
    console.error('Vue error:', err, info);

    if (isMonitored && import.meta.env.VITE_SENTRY_DSN) {
      Sentry.captureException(err, {
        contexts: {
          vue: {
            componentName: vm?.$options?.name || 'Unknown',
            info,
          },
        },
      });
    }

    if (vm && vm.$vToastify) {
      vm.$vToastify.error('An unexpected error occurred. Please refresh the page.', 'Error');
    }
  };
}

/**
 * Global unhandled promise rejection handler
 * Captures unhandled promise rejections and reports to Sentry
 */
export function setupUnhandledRejectionHandler(Vue) {
  window.addEventListener('unhandledrejection', (event) => {
    const reason = event.reason;

    // Skip already-handled API errors
    if (reason?.__globalApiHandled) return;

    // Skip standard non-crash HTTP client responses (401, 403, 404, 422, 429)
    const status = reason?.response?.status;
    if (status && [401, 403, 404, 422, 429].includes(status)) return;

    // Skip aborted/canceled requests
    if (reason?.code === 'ERR_CANCELED' || reason?.name === 'CanceledError') return;

    console.error('Unhandled promise rejection:', reason);

    if (isMonitored && import.meta.env.VITE_SENTRY_DSN) {
      Sentry.captureException(reason);
    }

    if (Vue.prototype.$vToastify) {
      Vue.prototype.$vToastify.error('An unexpected error occurred. Please refresh the page.', 'Error');
    }
  });
}

/**
 * Set non-PII user context and diagnostic tags
 * Associates errors with a specific user ID for impact analysis
 */
export function setSentryUser(user) {
  if (!user || !user.id) {
    Sentry.setUser(null);
    return;
  }

  // Database ID only (non-PII)
  Sentry.setUser({
    id: String(user.id),
  });

  // Diagnostic tags
  if (user.company_id) {
    Sentry.setTag('company_id', user.company_id);
  }
  if (user.is_admin !== undefined) {
    Sentry.setTag('is_admin', Boolean(user.is_admin));
  }
  if (user.timezone) {
    Sentry.setTag('timezone', user.timezone);
  }
}

/**
 * Clear Sentry user context
 * Call on logout or session expiration
 */
export function clearSentryUser() {
  Sentry.setUser(null);
}

/**
 * Setup all Sentry error handlers
 */
export function setupSentry(Vue, router) {
  initSentry(Vue, router);
  setupVueErrorHandler(Vue);
  setupUnhandledRejectionHandler(Vue);
}
