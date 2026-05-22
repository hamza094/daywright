import assert from 'node:assert/strict';
import test from 'node:test';

import {
  getVerificationFailureReason,
  isTwoFactorChallenge,
  parseAuthSession,
  parseTwoFactorResponse,
  parseVerificationResponse,
} from './authResponse.js';

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

test('parseAuthSession reads wrapped login payloads', () => {
  const response = makeResponse({
    data: {
      user: { uuid: 'user-1', name: 'Test User' },
      features: { chat: true },
    },
  });

  assert.deepEqual(parseAuthSession(response), {
    user: { uuid: 'user-1', name: 'Test User' },
    features: { chat: true },
    twoFactorState: '',
  });
});

test('parseTwoFactorResponse detects wrapped two-factor challenge state', () => {
  const response = makeResponse({
    data: {
      two_factor_state: '2fa_required',
      message: 'Two-factor authentication is enabled. Please provide the verification code.',
    },
  });

  assert.equal(parseTwoFactorResponse(response).state, '2fa_required');
  assert.equal(isTwoFactorChallenge(response), true);
});

test('parseTwoFactorResponse reads qr codes and recovery codes from snake_case payloads', () => {
  const statusResponse = makeResponse({
    data: {
      two_factor_state: 'in_progress',
      qr_code: '<svg></svg>',
    },
  });

  const recoveryCodesResponse = makeResponse({
    data: {
      recovery_codes: ['alpha', 'beta', 'gamma'],
    },
  });

  assert.equal(parseTwoFactorResponse(statusResponse).state, 'in_progress');
  assert.equal(parseTwoFactorResponse(statusResponse).qrCode, '<svg></svg>');
  assert.deepEqual(parseTwoFactorResponse(recoveryCodesResponse).recoveryCodes, ['alpha', 'beta', 'gamma']);
});

test('verification helpers prefer verification-specific messages over generic error codes', () => {
  const verifiedResponse = makeResponse({
    data: {
      verified: true,
    },
  });

  const invalidSignatureError = makeError(
    {
      message: 'verification.invalid',
      code: 'bad_request',
      errors: {},
      meta: {},
    },
    400,
  );

  const alreadyVerifiedError = makeError({
    message: 'Validation failed.',
    code: 'validation_error',
    errors: {
      email: ['verification.already_verified'],
    },
    meta: {},
  });

  assert.equal(parseVerificationResponse(verifiedResponse).verified, true);
  assert.equal(getVerificationFailureReason(invalidSignatureError), 'verification.invalid');
  assert.equal(getVerificationFailureReason(alreadyVerifiedError), 'verification.already_verified');
});

test('parseAuthSession collapses non-object feature payloads to an empty map', () => {
  const response = makeResponse({
    data: {
      user: { uuid: 'user-1' },
      features: [],
    },
  });

  assert.deepEqual(parseAuthSession(response).features, {});
});
