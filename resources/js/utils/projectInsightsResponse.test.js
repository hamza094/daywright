import assert from 'node:assert/strict';
import test from 'node:test';

import { parseProjectInsightsResponse } from './projectInsightsResponse.js';

const makeResponse = (data) => ({
  data,
  status: 200,
  headers: {},
  config: {},
});

test('parseProjectInsightsResponse reads the wrapped project insights resource', () => {
  const parsed = parseProjectInsightsResponse(
    makeResponse({
      data: {
        project_id: 42,
        project_name: 'Roadmap',
        insights: [{ key: 'health', data: { percentage: 91 } }],
        sections_requested: ['health'],
        generated_at: '2026-05-23T12:00:00Z',
      },
    }),
    ['health'],
  );

  assert.deepEqual(parsed, {
    project_id: 42,
    project_name: 'Roadmap',
    insights: [{ key: 'health', data: { percentage: 91 } }],
    sections_requested: ['health'],
    generated_at: '2026-05-23T12:00:00Z',
  });
});

test('parseProjectInsightsResponse falls back to requested sections and empty insights', () => {
  const parsed = parseProjectInsightsResponse(
    makeResponse({
      data: {
        project_id: null,
        project_name: null,
        insights: null,
      },
    }),
    ['risk'],
  );

  assert.deepEqual(parsed, {
    project_id: null,
    project_name: null,
    insights: [],
    sections_requested: ['risk'],
    generated_at: null,
  });
});
