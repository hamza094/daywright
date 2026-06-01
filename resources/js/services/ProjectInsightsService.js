import axios from 'axios';
import { parseApiError } from '../utils/apiResponse.js';
import { normalizeSectionsForRequest } from '../utils/insightsSections.js';
import { parseProjectInsightsResponse } from '../utils/projectInsightsResponse.js';
const BASE_URL = '';

const AVAILABLE_SECTIONS = [
  { key: 'all', label: 'All', icon: 'fa-solid fa-th' },
  { key: 'health', label: 'Health', icon: 'fa-solid fa-heartbeat' },
  { key: 'task-health', label: 'Task Health', icon: 'fa-solid fa-tasks' },
  { key: 'collaboration', label: 'Collaboration', icon: 'fa-solid fa-handshake' },
  { key: 'risk', label: 'Risk', icon: 'fa-solid fa-exclamation-triangle' },
  { key: 'stage', label: 'Stage', icon: 'fa-solid fa-project-diagram' },
];

// Extract a concise, user-friendly error message from an axios error
function extractErrorMessage(error) {
  return parseApiError(error, 'An error occurred. Please try again later.').message;
}
/**
 * Project Insights API Service
 *
 * Provides interface for interacting with the Laravel Project Insights API
 */

class ProjectInsightsService {
  /**
   * Get insights for a project
   * @param {string} projectSlug - Project slug or ID
   * @param {array} sections - Array of sections to fetch
   * @returns {Promise}
   */
  async getInsights(projectSlug, sections) {
    if (!projectSlug) throw new Error('Project identifier is required');

    try {
      // Normalize allowed sections; omit query when none
      const normalized = normalizeSectionsForRequest(sections);

      const params = normalized.length ? new URLSearchParams(normalized.map((s) => ['sections[]', s])) : null;

      const slug = encodeURIComponent(String(projectSlug));
      const query = params ? `?${params.toString()}` : '';
      const url = `${BASE_URL}/projects/${slug}/insights${query}`;

      const response = await axios.get(url);

      return parseProjectInsightsResponse(response, normalized);
    } catch (error) {
      throw new Error(extractErrorMessage(error));
    }
  }

  /**
   * Get available sections
   * @returns {array}
   */
  getAvailableSections() {
    // Provide only backend-supported sections; keep an 'all' UI option that maps to no param
    return AVAILABLE_SECTIONS;
  }
}

// Export singleton instance
export default new ProjectInsightsService();

// Also export the class for additional usage
export { ProjectInsightsService };
