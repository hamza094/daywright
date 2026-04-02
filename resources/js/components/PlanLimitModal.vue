<template>
  <modal
    name="PlanLimitModal"
    height="auto"
    :scrollable="true"
    width="420px"
    class="modal-design"
    :click-to-close="true"
    @before-open="onBeforeOpen">
    <div class="plan-limit-modal">
      <div class="plan-limit-modal_header">
        <span class="plan-limit-modal_icon">
          <i class="fas fa-exclamation-circle"></i>
        </span>
        <h4 class="plan-limit-modal_title">Plan limit reached</h4>
        <button class="plan-limit-modal_close" aria-label="Close" @click.prevent="close">&times;</button>
      </div>

      <div class="plan-limit-modal_body">
        <p class="plan-limit-modal_message">{{ message }}</p>

        <div v-if="maxAllowed !== null" class="plan-limit-modal_usage">
          <div class="plan-limit-modal_usage-label">
            <span>{{ limitLabel }}</span>
            <span class="plan-limit-modal_usage-count"> {{ currentUsage }} / {{ maxAllowed }} </span>
          </div>
          <div class="plan-limit-modal_usage-track">
            <div class="plan-limit-modal_usage-bar" :style="{ width: usagePercent + '%' }"></div>
          </div>
        </div>

        <p v-if="reason === 'trial_expired'" class="plan-limit-modal_hint">
          Your trial has ended. Upgrade to continue using this feature.
        </p>
      </div>

      <div class="plan-limit-modal_footer">
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
      if (this.maxAllowed == null) {
        return 0;
      }

      if (this.maxAllowed <= 0) {
        return this.currentUsage > 0 ? 100 : 0;
      }

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
