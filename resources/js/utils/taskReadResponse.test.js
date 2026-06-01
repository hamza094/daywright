import assert from 'node:assert/strict';
import test from 'node:test';

import { buildArchivedTaskParams, createEmptyTaskPage, readTaskStatusIndex } from './taskReadResponse.js';

const makeResponse = (data) => ({
  data,
  status: 200,
  headers: {},
  config: {},
});

test('createEmptyTaskPage returns a fresh paginated task shell', () => {
  const first = createEmptyTaskPage();
  const second = createEmptyTaskPage();

  assert.deepEqual(first, { data: [], meta: {}, links: {} });
  assert.notEqual(first, second);
});

test('buildArchivedTaskParams uses the canonical archived filter bag', () => {
  assert.deepEqual(buildArchivedTaskParams(), {
    page: 1,
    filter: {
      state: 'archived',
    },
  });

  assert.deepEqual(buildArchivedTaskParams({ page: 3 }), {
    page: 3,
    filter: {
      state: 'archived',
    },
  });
});

test('readTaskStatusIndex unwraps wrapped status reference data', () => {
  assert.deepEqual(
    readTaskStatusIndex(
      makeResponse({
        data: {
          statuses: [{ id: 1, name: 'Todo' }],
          due_notifies: ['daily'],
        },
      }),
    ),
    {
      statuses: [{ id: 1, name: 'Todo' }],
      dueNotifies: ['daily'],
    },
  );

  assert.deepEqual(readTaskStatusIndex(makeResponse({ data: {} })), {
    statuses: [],
    dueNotifies: [],
  });
});
