import { getPaginatedData, getResponseData, getResponsePayload } from './apiResponse.js';

const asStringArray = (value) => {
  if (!Array.isArray(value)) {
    return [];
  }

  return value.filter((item) => typeof item === 'string' && item.trim() !== '');
};

const asNumber = (value, fallback = 0) => {
  return typeof value === 'number' && Number.isFinite(value) ? value : fallback;
};

export const readDashboardProjects = (response) => {
  const paginated = getPaginatedData(response);
  const payload = getResponsePayload(response);

  return {
    projects: paginated.data,
    pagination: paginated,
    total: asNumber(paginated.meta.total, paginated.data.length),
    message: typeof payload.message === 'string' ? payload.message : '',
  };
};

export const buildDashboardTaskParams = ({ assigned, created, activeFilter = 'all' }) => {
  const filter = {};

  if (assigned) {
    filter.task_assigned = true;
  }

  if (created) {
    filter.user_created = true;
  }

  if (activeFilter === 'overdue') {
    filter.overdue = true;
  }

  if (activeFilter === 'remaining') {
    filter.remaining = true;
  }

  if (activeFilter === 'completed') {
    filter.completed = true;
  }

  return {
    filter,
  };
};

export const readDashboardTasks = (response) => {
  const payload = getResponsePayload(response);
  const tasks = getResponseData(response);

  return {
    tasks: Array.isArray(tasks) ? tasks : [],
    appliedFilters: asStringArray(payload.meta?.applied_filters),
    total: asNumber(payload.meta?.total),
  };
};
