<template>
  <modal
    name="project-insights-modal"
    :adaptive="true"
    :scrollable="true"
    :reset="true"
    width="90%"
    :max-width="1200"
    height="auto"
    :max-height="900"
    @before-open="handleBeforeOpen"
    @closed="handleClosed">
    <div class="insights-modal">
      <!-- Header -->
      <div class="insights-modal-header border-bottom bg-light p-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <div class="d-flex align-items-center">
            <div class="project-avatar rounded-circle d-flex align-items-center justify-content-center me-3">
              <span>{{ (project.name || '').substring(0, 1).toUpperCase() }}</span>
            </div>
            <div>
              <h3 class="h4 mb-0 text-dark fw-bold">{{ project.name || 'Project Insights' }}</h3>
              <p class="text-muted small mb-0">Comprehensive project analysis and recommendations</p>
            </div>
          </div>
          <button @click="closeModal" class="btn-close" aria-label="Close"></button>
        </div>

        <!-- Section Filters -->
        <InsightFilters
          :sections="availableSections"
          :active-sections="activeSections"
          @filter-change="handleFilterChange" />
      </div>

      <!-- Body -->
      <div class="insights-modal-body flex-grow-1 p-4 overflow-auto">
        <div v-if="loading" class="text-center py-5">
          <div class="spinner-border text-primary mb-3" role="status">
            <span class="visually-hidden">Loading...</span>
          </div>
          <p class="text-muted">Loading project insights...</p>
        </div>

        <div v-else-if="error" class="text-center py-5">
          <i class="fa-solid fa-exclamation-triangle display-4 text-danger mb-3"></i>
          <h4 class="text-danger">Unable to Load Insights</h4>
          <p class="text-muted">{{ error }}</p>
          <button @click="fetchInsights" class="btn btn-primary mt-2">
            <i class="fa-solid fa-redo me-2"></i> Retry
          </button>
        </div>

        <div v-else-if="insights.length === 0" class="text-center py-5">
          <i class="fa-solid fa-chart-line display-4 text-muted mb-3"></i>
          <h4 class="text-muted">No Insights Available</h4>
          <p class="text-muted">No insights match your current filter selection.</p>
        </div>

        <div v-else class="insights-content">
          <!-- Detailed Insights -->
          <div>
            <h4 class="h5 d-flex align-items-center mb-4">
              <i class="fa-solid fa-chart-bar me-2"></i>
              Detailed Analysis
            </h4>
            <div class="row">
              <div
                v-for="insight in prioritizedInsights"
                :key="insight.title || insight.type"
                class="col-xl-6 col-lg-12 mb-4">
                <InsightDetailCard :insight="insight" />
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Footer -->
      <div
        class="insights-modal-footer d-flex justify-content-between align-items-center p-3 border-top bg-light"
        v-if="!loading && !error">
        <div>
          <small class="text-muted d-flex align-items-center gap-3">
            <i class="fa-regular fa-clock me-2"></i>
            <span v-if="generatedAt">Generated {{ generatedAt }}</span>
            <span v-if="sectionsRequested && sectionsRequested.length">
              • Sections: {{ sectionsRequested.join(', ') }}</span
            >
          </small>
        </div>
        <div class="d-flex gap-2">
          <button @click="refreshInsights" class="btn btn-primary btn-sm">
            <i class="fa-solid fa-sync me-2"></i> Refresh
          </button>
        </div>
      </div>
    </div>
  </modal>
</template>

<script>
import ProjectInsightsService from '../../../services/ProjectInsightsService.js';
import InsightFilters from './InsightFilters.vue';
import InsightDetailCard from './InsightDetailCard.vue';
import ProjectInsightsMixin from '../../../mixins/ProjectInsightsMixin.js';
import InsightPresentationMixin from '../../../mixins/InsightPresentationMixin.js';

const PRIORITY_ORDER = { critical: 1, warning: 2, info: 3, success: 4 };

export default {
  name: 'ProjectInsightsModal',
  components: { InsightFilters, InsightDetailCard },
  mixins: [ProjectInsightsMixin, InsightPresentationMixin],
  props: {
    project: { type: Object, required: true },
  },
  data() {
    return {
      loading: false,
      error: null,
      insights: [],
      generatedAt: null,
      sectionsRequested: [],
      activeSections: ['all'],
      availableSections: ProjectInsightsService.getAvailableSections(),
    };
  },
  computed: {
    prioritizedInsights() {
      const rank = (insight) => {
        const p = String((insight && (insight.priority || insight.type)) || '').toLowerCase();
        return PRIORITY_ORDER[p] || 999;
      };
      return [...this.insights].sort((a, b) => rank(a) - rank(b));
    },
  },
  methods: {
    async handleBeforeOpen(event) {
      const modalProject = (event && event.params && event.params.project) || this.project;
      if (!modalProject) return;
      this.$emit('project-changed', modalProject);
      await this.fetchInsights();
    },
    handleClosed() {
      this.error = null;
      this.insights = [];
      this.generatedAt = null;
      this.sectionsRequested = [];
      this.activeSections = ['all'];
    },
    async fetchInsights(bypassCache = false) {
      this.loading = true;
      this.error = null;
      try {
        // Treat empty or ['all'] as null so backend defaults apply
        const current = Array.isArray(this.activeSections) ? this.activeSections.filter(Boolean) : [];
        const useSections = !current.length || current.includes('all') ? null : current;

        const result = await this.loadCurrentProjectInsights(useSections, !bypassCache);
        // Assign from flattened API response
        this.insights = Array.isArray(result.insights) ? result.insights : [];
        this.generatedAt = result.generated_at || null;
        this.sectionsRequested = Array.isArray(result.sections_requested)
          ? result.sections_requested
          : Array.isArray(useSections)
            ? useSections
            : [];
      } catch (e) {
        this.handleErrorResponse(e);
        this.error = (e && e.message) || 'Failed to load project insights';
      } finally {
        this.loading = false;
      }
    },

    async handleFilterChange(newSections) {
      this.activeSections = Array.isArray(newSections) ? [...newSections] : ['all'];
      await this.fetchInsights();
    },
    async refreshInsights() {
      // Clear cache for this project to force a fresh fetch
      this.clearCurrentProjectInsightsCache();
      await this.fetchInsights(true);
    },
    closeModal() {
      this.$modal.hide('project-insights-modal');
    },
  },
};
</script>
