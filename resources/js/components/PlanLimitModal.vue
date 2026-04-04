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
            <span>{{ displayLimitLabel }}</span>
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
const PLAN_LIMIT_GUIDANCE = {
  trialExpired: 'Your trial has ended. Upgrade to continue using this feature.',
  projectOwnerManaged:
    "This project limit is controlled by the project owner's subscription. Ask the owner to upgrade or reduce usage to continue.",
  projectUpgrade:
    'This project limit is tied to your subscription. Upgrade your plan to increase the project capacity.',
  ownerOverLimitAction: 'Ask the owner to reduce usage or upgrade before creating more.',
  selfOverLimitAction: 'Reduce usage or upgrade before creating more.',
};

export default {
  name: 'PlanLimitModal',
  data() {
    return {
      message: '',
      reason: '',
      limitType: '',
      limitLabel: '',
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
    displayLimitLabel() {
      return this.limitLabel || this.limitType;
    },
    overLimitScopeLabel() {
      return this.isProjectScoped ? "this project's current plan limit" : 'your current plan limit';
    },
    guidanceMessage() {
      if (this.isOverLimit) {
        const resolution =
          this.isProjectScoped && !this.canUpgrade
            ? PLAN_LIMIT_GUIDANCE.ownerOverLimitAction
            : PLAN_LIMIT_GUIDANCE.selfOverLimitAction;

        return `Usage for ${this.displayLimitLabel.toLowerCase()} is already above ${this.overLimitScopeLabel}. ${resolution}`;
      }

      if (this.reason === 'trial_expired') {
        return PLAN_LIMIT_GUIDANCE.trialExpired;
      }

      if (!this.isProjectScoped) {
        return '';
      }

      return this.canUpgrade ? PLAN_LIMIT_GUIDANCE.projectUpgrade : PLAN_LIMIT_GUIDANCE.projectOwnerManaged;
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
      this.limitLabel = params.limitLabel || '';
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
