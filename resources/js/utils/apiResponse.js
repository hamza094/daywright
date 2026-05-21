const isPlainObject = (value) => value !== null && typeof value === 'object' && !Array.isArray(value);

const isAxiosResponse = (value) =>
  isPlainObject(value) && 'data' in value && ('status' in value || 'headers' in value || 'config' in value);

const normalizePayload = (value) => {
  if (isAxiosResponse(value)) {
    return isPlainObject(value.data) ? value.data : {};
  }

  return isPlainObject(value) ? value : {};
};

const firstValidationMessage = (errors) => {
  if (!isPlainObject(errors)) {
    return '';
  }

  return (
    Object.values(errors)
      .flat()
      .find((message) => typeof message === 'string' && message.trim() !== '') || ''
  );
};

export const readResponsePayload = (response) => normalizePayload(response);

export const readResourceData = (response) => {
  const payload = readResponsePayload(response);

  return Object.prototype.hasOwnProperty.call(payload, 'data') ? payload.data : null;
};

export const readPaginatedResponse = (response) => {
  const payload = readResponsePayload(response);

  return {
    data: Array.isArray(payload.data) ? payload.data : [],
    meta: isPlainObject(payload.meta) ? payload.meta : {},
    links: isPlainObject(payload.links) ? payload.links : {},
  };
};

export const readMessage = (response) => {
  const payload = readResponsePayload(response);

  return typeof payload.message === 'string' ? payload.message : '';
};

export const readErrorPayload = (error) => normalizePayload(error?.response ?? error);

export const readErrorCode = (error) => {
  const payload = readErrorPayload(error);

  return typeof payload.code === 'string' ? payload.code : '';
};

export const readValidationErrors = (error) => {
  const payload = readErrorPayload(error);

  return isPlainObject(payload.errors) ? payload.errors : {};
};

export const readErrorMeta = (error) => {
  const payload = readErrorPayload(error);

  return isPlainObject(payload.meta) ? payload.meta : {};
};

export const readErrorMessage = (error, fallback = '') => {
  const payload = readErrorPayload(error);

  if (typeof payload.message === 'string' && payload.message.trim() !== '') {
    return payload.message;
  }

  if (typeof payload.error === 'string' && payload.error.trim() !== '') {
    return payload.error;
  }

  const validationMessage = firstValidationMessage(payload.errors);

  if (validationMessage !== '') {
    return validationMessage;
  }

  return fallback;
};
