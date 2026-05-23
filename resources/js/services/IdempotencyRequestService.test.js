import assert from 'node:assert/strict';
import test from 'node:test';

import { createIdempotentRequest } from './IdempotencyRequestService.js';

test('createIdempotentRequest adds an idempotency key and rotates it after success', async () => {
  const calls = [];
  const client = async (config) => {
    calls.push(config);

    return { data: { ok: true } };
  };

  const request = createIdempotentRequest(client);

  await request.post('/users/me/subscription', { plan: 'monthly' });
  await request.post('/users/me/subscription', { plan: 'monthly' });

  assert.equal(calls.length, 2);
  assert.ok(calls[0].headers['Idempotency-Key']);
  assert.ok(calls[1].headers['Idempotency-Key']);
  assert.notEqual(calls[0].headers['Idempotency-Key'], calls[1].headers['Idempotency-Key']);
});

test('createIdempotentRequest reuses the same key for identical retries after a network failure', async () => {
  const calls = [];
  let shouldFail = true;
  const client = async (config) => {
    calls.push(config);

    if (shouldFail) {
      shouldFail = false;
      throw new Error('network error');
    }

    return { data: { ok: true } };
  };

  const request = createIdempotentRequest(client);

  await assert.rejects(() => request.patch('/projects/demo/meetings/1', { topic: 'Retry' }));
  await request.patch('/projects/demo/meetings/1', { topic: 'Retry' });

  assert.equal(calls.length, 2);
  assert.equal(calls[0].headers['Idempotency-Key'], calls[1].headers['Idempotency-Key']);
});

test('createIdempotentRequest keeps the same key for 409 retries but clears it for non-retry errors', async () => {
  const calls = [];
  const responses = [
    { response: { status: 409 } },
    { data: { ok: true } },
    { response: { status: 422 } },
    { data: { ok: true } },
  ];

  const client = async (config) => {
    calls.push(config);

    const next = responses.shift();

    if (next?.response) {
      throw next;
    }

    return next;
  };

  const request = createIdempotentRequest(client);

  await assert.rejects(() => request.post('/api-tokens', { name: 'Token' }));
  await request.post('/api-tokens', { name: 'Token' });
  await assert.rejects(() => request.post('/api-tokens', { name: 'Token' }));
  await request.post('/api-tokens', { name: 'Token' });

  assert.equal(calls.length, 4);
  assert.equal(calls[0].headers['Idempotency-Key'], calls[1].headers['Idempotency-Key']);
  assert.notEqual(calls[2].headers['Idempotency-Key'], calls[3].headers['Idempotency-Key']);
});

test('createIdempotentRequest reset clears the active key immediately', async () => {
  const calls = [];
  const client = async (config) => {
    calls.push(config);
    return { data: { ok: true } };
  };

  const request = createIdempotentRequest(client);

  await request.patch('/projects/demo/tasks/1/assign', { members: [3] });
  request.reset();
  await request.patch('/projects/demo/tasks/1/assign', { members: [3] });

  assert.equal(calls.length, 2);
  assert.notEqual(calls[0].headers['Idempotency-Key'], calls[1].headers['Idempotency-Key']);
});
