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

const looksLikeAxiosResponse = (value) => {
  const candidate = asObject(value);

  return 'status' in candidate || 'headers' in candidate || 'config' in candidate;
};

export const getResponsePayload = (value) => {
  if (looksLikeAxiosResponse(value)) {
    return asObject(value.data);
  }

  return asObject(value);
};

const normalizeValidationErrors = (errors) => {
  return Object.fromEntries(
    Object.entries(asObject(errors)).map(([field, messages]) => [field, asStringArray(messages)]),
  );
};

const findFirstValidationMessage = (errors) => {
  for (const messages of Object.values(errors)) {
    if (messages.length > 0) {
      return messages[0];
    }
  }

  return '';
};

export const getResponseData = (response) => {
  const payload = getResponsePayload(response);

  return Object.prototype.hasOwnProperty.call(payload, 'data') ? payload.data : null;
};

export const getObjectData = (response) => {
  return asObject(getResponseData(response));
};

export const getArrayData = (response) => {
  const data = getResponseData(response);

  return Array.isArray(data) ? data : [];
};

export const getPaginatedData = (response) => {
  const payload = getResponsePayload(response);

  return {
    data: Array.isArray(payload.data) ? payload.data : [],
    meta: asObject(payload.meta),
    links: asObject(payload.links),
  };
};

export const getResponseMessage = (response) => {
  return asNonEmptyString(getResponsePayload(response).message);
};

export const parseApiError = (error, fallback = '') => {
  const payload = getResponsePayload(error?.response ?? error);
  const errors = normalizeValidationErrors(payload.errors);
  const message = asNonEmptyString(payload.message) || findFirstValidationMessage(errors) || fallback;

  return {
    payload,
    code: asNonEmptyString(payload.code),
    errors,
    message,
    meta: asObject(payload.meta),
  };
};
