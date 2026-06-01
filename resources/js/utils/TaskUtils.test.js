import test from 'node:test';
import assert from 'node:assert/strict';

import { calculateRemainingTime } from './TaskUtils.js';

test('calculateRemainingTime returns an empty string when due_at is missing', () => {
  assert.equal(calculateRemainingTime(undefined, '2026-06-01T12:00:00Z'), '');
  assert.equal(calculateRemainingTime({}, '2026-06-01T12:00:00Z'), '');
});
