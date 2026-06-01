import { getResponseData, parseApiError } from './apiResponse.js';

const asObject = (value) => {
  return value !== null && typeof value === 'object' && !Array.isArray(value) ? value : {};
};

const asNonEmptyString = (value) => {
  return typeof value === 'string' && value.trim() !== '' ? value : '';
};

const asStringArray = (value) => {
  if (!Array.isArray(value)) {
    return [];
  }

  return value.filter((item) => typeof item === 'string' && item.trim() !== '');
};

const getAuthPayload = (response) => {
  return asObject(getResponseData(response));
};

const readSessionUser = (payload) => {
  return payload.user !== null && typeof payload.user === 'object' && !Array.isArray(payload.user)
    ? payload.user
    : null;
};

const normalizeFeatureFlags = (features) => {
  return asObject(features);
};

export const parseAuthSession = (response) => {
  const payload = getAuthPayload(response);

  return {
    user: readSessionUser(payload),
    features: normalizeFeatureFlags(payload.features),
    twoFactorState: asNonEmptyString(payload.two_factor_state),
  };
};

export const parseTwoFactorResponse = (response) => {
  const payload = getAuthPayload(response);

  return {
    state: asNonEmptyString(payload.two_factor_state),
    qrCode: asNonEmptyString(payload.qr_code) || null,
    recoveryCodes: asStringArray(payload.recovery_codes),
  };
};

export const isTwoFactorChallenge = (response) => parseTwoFactorResponse(response).state === '2fa_required';

export const parseVerificationResponse = (response) => {
  const payload = getAuthPayload(response);

  return {
    verified: payload.verified === true,
  };
};

export const getVerificationFailureReason = (error) => {
  const { errors, message } = parseApiError(error);
  const emailErrorMessage = errors.email?.[0] || '';

  if (emailErrorMessage.startsWith('verification.')) {
    return emailErrorMessage;
  }

  if (message.startsWith('verification.')) {
    return message;
  }

  return 'verification.invalid';
};
