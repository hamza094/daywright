import { getObjectData } from './apiResponse.js';

const asArray = (value) => {
  return Array.isArray(value) ? value : [];
};

export const createEmptyTaskPage = () => ({
  data: [],
  meta: {},
  links: {},
});

export const buildArchivedTaskParams = ({ page = 1 } = {}) => ({
  page,
  filter: {
    state: 'archived',
  },
});

export const readTaskStatusIndex = (response) => {
  const payload = getObjectData(response);

  return {
    statuses: asArray(payload.statuses),
    dueNotifies: asArray(payload.due_notifies),
  };
};
