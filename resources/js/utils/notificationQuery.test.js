import assert from 'node:assert/strict';
import test from 'node:test';

import { buildNotificationIndexParams } from './notificationQuery.js';

test('buildNotificationIndexParams omits the filter bag for all notifications', () => {
  assert.deepEqual(buildNotificationIndexParams('all', 'cursor-123'), { cursor: 'cursor-123' });
  assert.deepEqual(buildNotificationIndexParams(null, null), {});
});

test('buildNotificationIndexParams nests read-state filters under filter.status', () => {
  assert.deepEqual(buildNotificationIndexParams('unread', 'cursor-456'), {
    cursor: 'cursor-456',
    filter: { status: 'unread' },
  });

  assert.deepEqual(buildNotificationIndexParams('read'), {
    filter: { status: 'read' },
  });
});
