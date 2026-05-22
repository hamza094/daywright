import assert from 'node:assert/strict';
import test from 'node:test';

import {
  getPaginatedData,
  getResponseData,
  getResponseMessage,
  getResponsePayload,
  parseApiError,
} from './apiResponse.js';

const makeResponse = (data) => ({
  data,
  status: 200,
  headers: {},
  config: {},
});

const makeError = (data, status = 422) => ({
  response: {
    data,
    status,
    headers: {},
    config: {},
  },
});

test('getResponsePayload unwraps axios-style responses and ignores invalid payloads', () => {
  assert.deepEqual(getResponsePayload(makeResponse({ data: { id: 1 } })), { data: { id: 1 } });
  assert.deepEqual(getResponsePayload({ message: 'ok' }), { message: 'ok' });
  assert.deepEqual(getResponsePayload(null), {});
  assert.deepEqual(getResponsePayload([]), {});
  assert.equal(getResponseMessage({ message: 'ok' }), 'ok');
});

test('getResponseData keeps falsey resource values when the data key exists', () => {
  assert.equal(getResponseData(makeResponse({ data: false })), false);
  assert.equal(getResponseData(makeResponse({})), null);
});

test('getPaginatedData normalizes non-array collections and nested metadata', () => {
  assert.deepEqual(getPaginatedData(makeResponse({ data: ['a'], meta: { page: 1 }, links: { next: '/next' } })), {
    data: ['a'],
    meta: { page: 1 },
    links: { next: '/next' },
  });

  assert.deepEqual(getPaginatedData(makeResponse({ data: null, meta: null, links: [] })), {
    data: [],
    meta: {},
    links: {},
  });
});

test('parseApiError prefers structured messages and falls back to validation errors', () => {
  const structuredError = makeError({
    message: 'Validation failed.',
    code: 'validation_error',
    errors: { email: ['The email field is required.'] },
    meta: { request_id: 'req-1' },
  });

  const validationOnlyError = makeError({
    errors: { email: ['The email field is required.'] },
  });

  const structuredApiError = parseApiError(structuredError);
  const validationOnlyApiError = parseApiError(validationOnlyError, 'Fallback message');

  assert.equal(structuredApiError.message, 'Validation failed.');
  assert.equal(structuredApiError.code, 'validation_error');
  assert.deepEqual(structuredApiError.errors, { email: ['The email field is required.'] });
  assert.deepEqual(structuredApiError.meta, { request_id: 'req-1' });
  assert.equal(validationOnlyApiError.message, 'The email field is required.');
});
