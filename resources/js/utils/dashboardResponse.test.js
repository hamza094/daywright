import assert from 'node:assert/strict';
import test from 'node:test';

import { buildDashboardTaskParams, readDashboardProjects, readDashboardTasks } from './dashboardResponse.js';

const makeResponse = (data) => ({
  data,
  status: 200,
  headers: {},
  config: {},
});

test('readDashboardProjects reads project collections and top-level totals', () => {
  const response = makeResponse({
    data: [{ id: 1, name: 'Project One' }],
    meta: { total: 9 },
  });

  assert.deepEqual(readDashboardProjects(response), {
    projects: [{ id: 1, name: 'Project One' }],
    pagination: {
      data: [{ id: 1, name: 'Project One' }],
      meta: { total: 9 },
      links: {},
    },
    total: 9,
    message: '',
  });
});

test('buildDashboardTaskParams nests dashboard task filters under filter', () => {
  assert.deepEqual(buildDashboardTaskParams({ assigned: true, created: false, activeFilter: 'overdue' }), {
    filter: {
      task_assigned: true,
      overdue: true,
    },
  });
});

test('readDashboardTasks reads task collections with applied filter labels', () => {
  const response = makeResponse({
    data: [{ id: 7, title: 'Finish docs' }],
    meta: {
      applied_filters: ['Assigned', 'Overdue'],
      total: 1,
    },
  });

  assert.deepEqual(readDashboardTasks(response), {
    tasks: [{ id: 7, title: 'Finish docs' }],
    appliedFilters: ['Assigned', 'Overdue'],
    total: 1,
  });
});
