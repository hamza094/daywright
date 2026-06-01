import axios from 'axios';

const IDEMPOTENCY_RETRY_STATUS = 409;

function createRandomHex(bytesLength = 16) {
  const randomBytes = new Uint8Array(bytesLength);

  globalThis.crypto?.getRandomValues(randomBytes);

  return Array.from(randomBytes, (byte) => byte.toString(16).padStart(2, '0')).join('');
}

function createIdempotencyKey() {
  if (globalThis.crypto?.randomUUID) {
    return globalThis.crypto.randomUUID();
  }

  if (globalThis.crypto?.getRandomValues) {
    return `${Date.now()}-${createRandomHex()}`;
  }

  throw new Error('Secure random source unavailable for idempotency key generation.');
}

function shouldReuseKey(error) {
  const status = error?.response?.status;

  if (!error?.response) {
    return true;
  }

  return status === IDEMPOTENCY_RETRY_STATUS;
}

/*
 * Use this helper only for UI actions whose backend route applies Idempotent middleware.
 * Keep one helper instance per logical submit action, call reset() on teardown, and do not
 * inject Idempotency-Key globally with an axios interceptor.
 */
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
          ...(config.headers ?? {}),
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
