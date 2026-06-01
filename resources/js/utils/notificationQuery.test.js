import assert from 'node:assert/strict';
import test from 'node:test';

import { buildNotificationIndexParams } from './notificationQuery.js';

test('buildNotificationIndexParams omits the filter bag for all notifications', () => {
  assert.deepEqual(buildNotificationIndexParams('all', 2), { page: 2 });
  assert.deepEqual(buildNotificationIndexParams(null, 1), { page: 1 });
});

test('buildNotificationIndexParams nests read-state filters under filter.status', () => {
  assert.deepEqual(buildNotificationIndexParams('unread', 3), {
    page: 3,
    filter: { status: 'unread' },
  });

  assert.deepEqual(buildNotificationIndexParams('read'), {
    page: 1,
    filter: { status: 'read' },
  });
});
