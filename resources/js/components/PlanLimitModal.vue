<template>
  <modal
    name="PlanLimitModal"
    height="auto"
    :scrollable="true"
    width="420px"
    class="model-desin"
    :click-to-close="true"
    @before-open="onBeforeOpen">
    <div class="plan-limit-modal">
      <div class="plan-limit-modal__header">
        <span class="plan-limit-modal__icon">
          <i class="fas fa-exclamation-circle"></i>
        </span>
        <h4 class="plan-limit-modal__title">Plan limit reached</h4>
        <button class="plan-limit-modal__close" aria-label="Close" @click.prevent="close">&times;</button>
      </div>

      <div class="plan-limit-modal__body">
        <p class="plan-limit-modal__message">{{ message }}</p>

        <div v-if="maxAllowed !== null" class="plan-limit-modal__usage">
          <div class="plan-limit-modal__usage-label">
            <span>{{ limitLabel }}</span>
            <span class="plan-limit-modal__usage-count"> {{ currentUsage }} / {{ maxAllowed }} </span>
          </div>
          <div class="plan-limit-modal__usage-track">
            <div class="plan-limit-modal__usage-bar" :style="{ width: usagePercent + '%' }"></div>
          </div>
        </div>

        <p v-if="reason === 'trial_expired'" class="plan-limit-modal__hint">
          Your trial has ended. Upgrade to continue using this feature.
        </p>
      </div>

      <div class="plan-limit-modal__footer">
        <button class="btn btn-secondary btn-sm" @click.prevent="close">Close</button>
        <router-link to="/subscriptions" class="btn btn-primary btn-sm" @click.native="close">
          Upgrade to Pro
        </router-link>
      </div>
    </div>
  </modal>
</template>

<script>
const LIMIT_LABELS = {
  projects: 'Projects',
  active_tasks_per_project: 'Active tasks',
  members: 'Project members',
  meetings: 'Meetings created',
  api_tokens: 'API tokens',
};

export default {
  name: 'PlanLimitModal',
  data() {
    return {
      message: '',
      reason: '',
      limitType: '',
      currentUsage: 0,
      maxAllowed: null,
    };
  },
  computed: {
    limitLabel() {
      return LIMIT_LABELS[this.limitType] || this.limitType;
    },
    usagePercent() {
      if (!this.maxAllowed) return 0;
      return Math.min(100, Math.round((this.currentUsage / this.maxAllowed) * 100));
    },
  },
  methods: {
    onBeforeOpen(event) {
      const params = event.params || {};
      this.message = params.message || 'You have reached a plan limit.';
      this.reason = params.reason || '';
      this.limitType = params.limitType || '';
      this.currentUsage = params.currentUsage ?? 0;
      this.maxAllowed = params.maxAllowed ?? null;
    },
    close() {
      this.$modal.hide('PlanLimitModal');
    },
  },
};
</script>
