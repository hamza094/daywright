import axios from 'axios';

const IDEMPOTENCY_RETRY_STATUS = 409;

function createIdempotencyKey() {
  if (globalThis.crypto?.randomUUID) {
    return globalThis.crypto.randomUUID();
  }

  return `${Date.now()}-${Math.random().toString(36).slice(2)}`;
}

function shouldReuseKey(error) {
  const status = error?.response?.status;

  if (!error?.response) {
    return true;
  }

  return status === IDEMPOTENCY_RETRY_STATUS;
}

// Use one helper instance per logical submit action.
export function createIdempotentRequest(client = axios) {
  let activeKey = null;
  let activeRequest = null;

  function reset() {
    activeKey = null;
    activeRequest = null;
  }

  function getKey(method, url, data, config = {}) {
    const nextRequest = JSON.stringify({
      method,
      url,
      data: data ?? null,
      params: config.params ?? null,
    });

    if (activeRequest !== nextRequest || !activeKey) {
      activeRequest = nextRequest;
      activeKey = createIdempotencyKey();
    }

    return activeKey;
  }

  async function send(method, url, data, config = {}) {
    const idempotencyKey = getKey(method, url, data, config);

    try {
      const response = await client({
        ...config,
        method,
        url,
        data,
        headers: {
          ...(config.headers || {}),
          'Idempotency-Key': idempotencyKey,
        },
      });

      reset();

      return response;
    } catch (error) {
      if (!shouldReuseKey(error)) {
        reset();
      }

      throw error;
    }
  }

  return {
    reset,

    post(url, data, config = {}) {
      return send('post', url, data, config);
    },

    patch(url, data, config = {}) {
      return send('patch', url, data, config);
    },
  };
}
