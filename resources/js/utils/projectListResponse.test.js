import assert from 'node:assert/strict';
import test from 'node:test';

import { buildProjectListParams, readProjectList } from './projectListResponse.js';

const makeResponse = (data) => ({
  data,
  status: 200,
  headers: {},
  config: {},
});

test('buildProjectListParams nests supported project filters', () => {
  assert.deepEqual(
    buildProjectListParams({
      page: 2,
      search: 'alpha',
      sort: '-name',
      extraFilters: { member: true, abandoned: false },
    }),
    {
      page: 2,
      sort: '-name',
      filter: {
        search: 'alpha',
        member: true,
      },
    },
  );
});

test('readProjectList reads paginated project collections and totals', () => {
  const response = makeResponse({
    data: [{ id: 1, name: 'Alpha' }],
    meta: { total: 14 },
    links: { next: '/api/v1/projects?page=2' },
  });

  assert.deepEqual(readProjectList(response), {
    projects: [{ id: 1, name: 'Alpha' }],
    pagination: {
      data: [{ id: 1, name: 'Alpha' }],
      meta: { total: 14 },
      links: { next: '/api/v1/projects?page=2' },
    },
    total: 14,
  });
});
