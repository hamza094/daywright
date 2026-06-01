import assert from 'node:assert/strict';
import test from 'node:test';

import {
  buildAdminProjectParams,
  buildAdminTaskParams,
  buildAdminUserParams,
  createEmptyPaginatedState,
  toggleSortToken,
} from './adminListResponse.js';

test('createEmptyPaginatedState returns a fresh paginated shell', () => {
  const first = createEmptyPaginatedState();
  const second = createEmptyPaginatedState();

  assert.deepEqual(first, { data: [], meta: {}, links: {} });
  assert.notEqual(first, second);
});

test('toggleSortToken flips canonical sort tokens for a field', () => {
  assert.equal(toggleSortToken('-created_at'), 'created_at');
  assert.equal(toggleSortToken('created_at'), '-created_at');
  assert.equal(toggleSortToken('-health_score', 'health_score'), 'health_score');
});

test('buildAdminProjectParams nests canonical filters and keeps canonical sort values', () => {
  assert.deepEqual(
    buildAdminProjectParams({
      page: 2,
      searchTerm: '  Alpha  ',
      currentSort: '-health_score',
      filters: {
        projects: 'active',
        activeTasks: true,
        hasMembers: true,
        status: 'hot',
        startdate: '2026-01-01',
        enddate: '2026-01-31',
        stage: 0,
      },
    }),
    {
      page: 2,
      sort: '-health_score',
      filter: {
        search: 'Alpha',
        state: 'active',
        members: true,
        status: 'hot',
        tasks: true,
        stage: 0,
        from: '2026-01-01',
        to: '2026-01-31',
      },
    },
  );
});

test('buildAdminTaskParams and buildAdminUserParams omit empty filters', () => {
  assert.deepEqual(buildAdminTaskParams({ page: 3, state: 'all', searchTerm: '  ' }), { page: 3 });
  assert.deepEqual(buildAdminTaskParams({ page: 1, state: 'trashed', searchTerm: ' client ' }), {
    page: 1,
    filter: {
      state: 'trashed',
      search: 'client',
    },
  });

  assert.deepEqual(buildAdminUserParams({ page: 4, searchTerm: '' }), { page: 4 });
  assert.deepEqual(buildAdminUserParams({ page: 1, searchTerm: '  User Name  ' }), {
    page: 1,
    filter: {
      search: 'User Name',
    },
  });
});
