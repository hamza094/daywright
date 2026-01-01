<template>
  <div
    class="card h-100 insight-detail-card border-start border-4"
    :class="'insight-detail-card_' + (insight.type || 'info')">
    <div class="card-header bg-transparent d-flex justify-content-between align-items-center py-3">
      <div class="d-flex align-items-center">
        <div class="insight-icon" :class="getInsightIconClass(insight)">
          <i :class="getInsightIcon(insight)"></i>
        </div>
        <h5 class="card-title mb-0 fw-semibold">{{ insight.title || getTypeLabel(insight.type) }}</h5>
      </div>
      <span class="badge text-white" :class="getBootstrapBadgeClass(insight.type)">{{ getTypeLabel(insight.type) }}</span>
    </div>

    <div class="card-body">
      <div v-if="insight.data" class="mb-3">
        <div class="primary-value">
          <span class="display-6 fw-bold text-dark">{{ formatValue(insight) }}</span>
          <span v-if="getValueUnit(insight)" class="fs-5 text-muted ms-1">{{ getValueUnit(insight) }}</span>
        </div>
      </div>

      <p class="card-text text-muted">
        <b>{{ getInsightMessage(insight) }}</b>
      </p>

      <div v-if="hasMetadata(insight)" class="insight-metadata d-flex flex-wrap gap-3 mb-3">
        <div v-if="insight.data.trend" class="metadata-item d-flex align-items-center">
          <div class="metadata-icon trend"><i class="fa-solid fa-chart-line"></i></div>
          <span>{{ insight.data.trend }}</span>
        </div>
        <div v-if="insight.data.threshold" class="metadata-item d-flex align-items-center">
          <div class="metadata-icon target"><i class="fa-solid fa-bullseye"></i></div>
          <span>{{ insight.data.threshold }}{{ getValueUnit(insight) }}</span>
        </div>
        <div v-if="insight.data.score" class="metadata-item d-flex align-items-center">
          <div class="metadata-icon score"><i class="fa-solid fa-star"></i></div>
          <span>{{ insight.data.score }}/10</span>
        </div>
      </div>

      <div
        v-if="insight.recommendations && insight.recommendations.length"
        class="recommendations bg-light rounded p-3">
        <h6 class="d-flex align-items-center mb-2 fw-semibold">
          <i class="fa-solid fa-lightbulb me-2"></i> Recommendations
        </h6>
        <ul class="mb-0 ps-3">
          <li v-for="(rec, index) in insight.recommendations" :key="index" class="small text-muted mb-1">{{ rec }}</li>
        </ul>
      </div>
    </div>
  </div>
</template>

<script>
import InsightPresentationMixin from '../../../mixins/InsightPresentationMixin.js';

export default {
  name: 'InsightDetailCard',
  mixins: [InsightPresentationMixin],
  props: {
    insight: { type: Object, required: true },
  },
};
</script>
