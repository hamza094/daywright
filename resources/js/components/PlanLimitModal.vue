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
        <h4 class="plan-limit-modal_title">{{ modalTitle }}</h4>
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

        <p v-if="guidanceMessage" class="plan-limit-modal_hint">
          {{ guidanceMessage }}
        </p>
      </div>

      <div class="plan-limit-modal_footer">
        <button class="btn btn-secondary btn-sm" @click.prevent="close">Close</button>
        <router-link v-if="canUpgrade" to="/subscriptions" class="btn btn-primary btn-sm" @click.native="close">
          {{ primaryActionLabel }}
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
      limitScope: 'account',
      canUpgrade: true,
    };
  },
  computed: {
    isProjectScoped() {
      return this.limitScope === 'project';
    },
    isOverLimit() {
      return this.maxAllowed !== null && this.currentUsage > this.maxAllowed;
    },
    modalTitle() {
      return this.isProjectScoped ? 'Project limit reached' : 'Plan limit reached';
    },
    limitLabel() {
      return LIMIT_LABELS[this.limitType] || this.limitType;
    },
    guidanceMessage() {
      if (this.isOverLimit && this.isProjectScoped && !this.canUpgrade) {
        return `Usage for ${this.limitLabel.toLowerCase()} is already above this project's current plan limit. Ask the owner to reduce usage or upgrade before creating more.`;
      }

      if (this.isOverLimit) {
        const scopeLabel = this.isProjectScoped ? "this project's current plan limit" : 'your current plan limit';

        return `Usage for ${this.limitLabel.toLowerCase()} is already above ${scopeLabel}. Reduce usage or upgrade before creating more.`;
      }

      if (this.isProjectScoped && !this.canUpgrade) {
        return "This project limit is controlled by the project owner's subscription. Ask the owner to upgrade or reduce usage to continue.";
      }

      if (this.reason === 'trial_expired') {
        return 'Your trial has ended. Upgrade to continue using this feature.';
      }

      if (this.isProjectScoped && this.canUpgrade) {
        return 'This project limit is tied to your subscription. Upgrade your plan to increase the project capacity.';
      }

      return '';
    },
    primaryActionLabel() {
      return this.reason === 'trial_expired' ? 'Upgrade to Pro' : 'Manage plan';
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
      this.limitScope = params.limitScope || 'account';
      this.canUpgrade = params.canUpgrade !== false;
    },
    close() {
      this.$modal.hide('PlanLimitModal');
    },
  },
};
</script>
