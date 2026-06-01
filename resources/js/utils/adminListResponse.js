const hasValue = (value) => {
  return value !== undefined && value !== null && value !== '';
};

const trimString = (value) => {
  return typeof value === 'string' ? value.trim() : '';
};

export const createEmptyPaginatedState = () => ({
  data: [],
  meta: {},
  links: {},
});

export const toggleSortToken = (currentSort, field = 'created_at') => {
  const ascendingToken = field;
  const descendingToken = `-${field}`;

  return currentSort === ascendingToken ? descendingToken : ascendingToken;
};

export const buildAdminProjectParams = ({
  page = 1,
  searchTerm = '',
  currentSort = '-created_at',
  filters = {},
} = {}) => {
  const filter = {};
  const trimmedSearchTerm = trimString(searchTerm);

  if (trimmedSearchTerm !== '') {
    filter.search = trimmedSearchTerm;
  }

  if (filters.projects) {
    filter.state = filters.projects;
  }

  if (filters.hasMembers) {
    filter.members = true;
  }

  if (filters.status) {
    filter.status = filters.status;
  }

  if (filters.activeTasks) {
    filter.tasks = true;
  }

  if (hasValue(filters.stage)) {
    filter.stage = filters.stage;
  }

  if (filters.startdate) {
    filter.from = filters.startdate;
  }

  if (filters.enddate) {
    filter.to = filters.enddate;
  }

  const params = { page };

  if (currentSort) {
    params.sort = currentSort;
  }

  if (Object.keys(filter).length > 0) {
    params.filter = filter;
  }

  return params;
};

export const buildAdminTaskParams = ({ page = 1, state = 'all', searchTerm = '' } = {}) => {
  const filter = {};
  const trimmedSearchTerm = trimString(searchTerm);

  if (state !== 'all') {
    filter.state = state;
  }

  if (trimmedSearchTerm !== '') {
    filter.search = trimmedSearchTerm;
  }

  const params = { page };

  if (Object.keys(filter).length > 0) {
    params.filter = filter;
  }

  return params;
};

export const buildAdminUserParams = ({ page = 1, searchTerm = '' } = {}) => {
  const trimmedSearchTerm = trimString(searchTerm);

  return trimmedSearchTerm === ''
    ? { page }
    : {
        page,
        filter: {
          search: trimmedSearchTerm,
        },
      };
};
