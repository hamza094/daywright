import { getObjectData } from './apiResponse.js';

export const parseProjectInsightsResponse = (response, requestedSections = []) => {
  const payload = getObjectData(response);

  return {
    insights: Array.isArray(payload.insights) ? payload.insights : [],
    project_id: payload.project_id ?? null,
    project_name: payload.project_name ?? null,
    sections_requested: Array.isArray(payload.sections_requested) ? payload.sections_requested : requestedSections,
    generated_at: payload.generated_at ?? null,
  };
};
