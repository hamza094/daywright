import { getPaginatedData } from './apiResponse.js';

const asNonEmptyString = (value) => {
  return typeof value === 'string' && value.trim() !== '' ? value.trim() : '';
};

const asNumber = (value, fallback = 0) => {
  return typeof value === 'number' && Number.isFinite(value) ? value : fallback;
};

export const buildProjectListParams = ({ page = 1, search = '', sort = '-created_at', extraFilters = {} }) => {
  const filter = {};
  const searchTerm = asNonEmptyString(search);

  if (searchTerm !== '') {
    filter.search = searchTerm;
  }

  if (extraFilters.member === true) {
    filter.member = true;
  }

  if (extraFilters.abandoned === true) {
    filter.abandoned = true;
  }

  const params = {
    page,
    sort,
  };

  if (Object.keys(filter).length > 0) {
    params.filter = filter;
  }

  return params;
};

export const readProjectList = (response) => {
  const paginated = getPaginatedData(response);

  return {
    projects: paginated.data,
    pagination: paginated,
    total: asNumber(paginated.meta.total, paginated.data.length),
  };
};
