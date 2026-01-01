<template>
  <div class="project-status-root">
    <div class="project-icon">
      <div class="project-icon_name">{{ projectInitial }}</div>
    </div>

    <div class="score-dropdown" ref="dropdown" :id="'score-dropdown-' + _uid">
      <span
        role="button"
        tabindex="0"
        :aria-expanded="String(isPop)"
        :aria-controls="'score-dropdown-' + _uid"
        @click.stop="isPop = !isPop"
        class="score-point"
        :class="'score-point_' + status"
        @keydown.enter.prevent="isPop = !isPop"
        @keydown.space.prevent="isPop = !isPop">
        {{ displayScore }}
      </span>

      <div class="score-dropdown_item" v-show="isPop">
        <div class="score">
          <div class="score-content">
            <p class="score-content_para p-2">
              <i class="fa-regular fa-clock"></i>
              The project started {{ project && project.start ? project.start : 'N/A' }}. Currently in its
              <b v-text="project.stage.name"></b> stage
            </p>
          </div>

          <div class="insights-section">
            <div class="insights-header d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom-0">
              <h5 class="mb-0 d-flex align-items-center">
                <i class="fa-solid fa-heartbeat me-2"></i>
                Project Health
              </h5>
            </div>

            <div class="mt-2">
              <div v-if="healthLoading" class="d-flex align-items-center text-muted small py-1">
                <div
                  class="spinner-border spinner-border-sm text-primary me-2"
                  role="status"
                  aria-label="Loading"></div>
                <span>Loading health...</span>
              </div>
              <div v-else-if="healthError" class="text-danger small">{{ healthError }}</div>

              <div v-else-if="healthInsight" class="card insight-detail-card insight-detail-card_info mb-2">
                <div class="card-header bg-transparent d-flex justify-content-between align-items-center py-2">
                  <div class="d-flex align-items-center">
                    <div class="insight-icon health"><i class="fa-solid fa-heartbeat"></i></div>
                    <h6 class="card-title mb-0 fw-semibold">{{ healthInsight.title || 'Health' }}</h6>
                  </div>
                  <span class="badge bg-success text-white insight-badge">Health</span>
                </div>
                <div class="card-body py-2">
                  <div v-if="healthInsight.data" class="primary-value mb-2">
                    <span class="display-6 fw-bold text-dark">{{ formatHealthValue(healthInsight) }}</span>
                    <span class="fs-6 text-muted ms-1">%</span>
                  </div>
                  <p v-if="healthInsight.message" class="card-text text-muted small mb-0">
                    <b>{{ healthInsight.message }}</b>
                  </p>
                </div>
              </div>

              <div class="insights-actions mt-2 text-end">
                <button
                  class="btn-full-insights d-inline-flex align-items-center"
                  @click.stop="openInsightsModal($event)">
                  <i class="fa-solid fa-chart-bar"></i>
                  View Complete Insights
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <ProjectInsightsModal :project="project" />
  </div>
</template>

<script>
import ProjectInsightsModal from './Insights/ProjectInsightsModal.vue';
import ProjectInsightsMixin from '../../mixins/ProjectInsightsMixin.js';

export default {
  name: 'ProjectStatus',
  components: { ProjectInsightsModal },
  mixins: [ProjectInsightsMixin],
  props: {
    project: { type: Object, default: null },
  },
  data() {
    return {
      isPop: false,
      clickOutsideHandler: null,
      healthLoading: false,
      healthError: null,
      healthInsight: null,
    };
  },
  computed: {
    projectInitial() {
      return this.project && this.project.name ? this.project.name.substring(0, 1).toUpperCase() : '';
    },
    displayScore() {
      if (!this.project || this.project.score === null) return 'N/A';
      const n = Number(this.project.score);
      return Number.isFinite(n) ? Math.round(n) : 'N/A';
    },
    status() {
      return this.project && this.project.status ? this.project.status : 'cold';
    },
  },
  watch: {
    isPop(open) {
      if (open) {
        this.addClickOutsideListener();
        if (this.project) this.fetchHealth();
      } else {
        this.removeClickOutsideListener();
      }
    },
  },
  beforeDestroy() {
    this.removeClickOutsideListener();
  },
  methods: {
    toggle() {
      this.isPop = !this.isPop;
    },
    addClickOutsideListener() {
      if (this.clickOutsideHandler) return;
      this.$nextTick().then(() => {
        this.clickOutsideHandler = (e) => {
          if (this.$refs.dropdown && !this.$refs.dropdown.contains(e.target)) this.isPop = false;
        };
        document.addEventListener('click', this.clickOutsideHandler);
      });
    },
    removeClickOutsideListener() {
      if (!this.clickOutsideHandler) return;
      document.removeEventListener('click', this.clickOutsideHandler);
      this.clickOutsideHandler = null;
    },
    openInsightsModal() {
      if (!this.project) return;
      this.isPop = false;
      this.$modal.show('project-insights-modal', { project: this.project });
    },
    async fetchHealth() {
      this.healthLoading = true;
      this.healthError = null;
      this.healthInsight = null;
      try {
        const result = await this.loadCurrentProjectInsights(['health']);
        if (!result || !result.success) throw new Error('Failed to load health insight');
        const arr = Array.isArray(result.insights) ? result.insights : [];
        this.healthInsight = arr[0] || null;
      } catch (e) {
        this.healthError = e && e.message ? e.message : 'Failed to load health insight';
      } finally {
        this.healthLoading = false;
      }
    },
    formatHealthValue(insight) {
      if (!insight || !insight.data) return 'N/A';
      const { value, percentage } = insight.data;
      if (typeof percentage === 'number') return Math.round(percentage);
      if (typeof value === 'number') return Math.round(value);
      return 'N/A';
    },
  },
};
</script>
