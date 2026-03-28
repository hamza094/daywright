<template>
  <div>
    <!-- Page Title -->
    <div class="page-top margin-small">Your Membership</div>

    <div class="container">
      <div class="subscription-overview card mb-4">
        <div class="card-body">
          <div class="subscription-overview_header">
            <div>
              <p class="subscription-overview_eyebrow mb-2">Plan &amp; Usage</p>
              <h3 class="subscription-overview_title mb-2">{{ currentPlanLabel }} Plan</h3>
              <p class="subscription-overview_meta mb-0">
                <span v-if="isOnTrial && trialEndsAt">Trial active until {{ trialEndsAt }}.</span>
                <span v-else-if="isOnTrial">Trial active for a limited time.</span>
                <span v-else-if="isInGracePeriod && gracePeriodEndsAt"
                  >Grace period active until {{ gracePeriodEndsAt }}.</span
                >
                <span v-else-if="isActivelyBilling && billingPlanLabel"
                  >You are billed {{ billingPlanLabel.toLowerCase() }}.</span
                >
                <span v-else>You are currently on the {{ currentPlanLabel }} plan.</span>
              </p>
            </div>

            <div class="subscription-overview_badges">
              <span class="subscription-badge subscription-badge-plan">{{ currentPlanLabel }}</span>
              <span v-if="isOnTrial" class="subscription-badge subscription-badge-trial">Trial Active</span>
              <span v-if="isInGracePeriod" class="subscription-badge subscription-badge-grace">Grace Period</span>
            </div>
          </div>

          <div class="subscription-usage">
            <div v-for="item in accountUsageItems" :key="item.key" class="subscription-usage_item">
              <div class="subscription-usage_row">
                <div>
                  <p class="subscription-usage_label mb-1">{{ item.label }}</p>
                  <p class="subscription-usage_value mb-0">{{ formatUsageLimit(item.limit) }}</p>
                </div>
                <span
                  class="subscription-usage_status"
                  :class="usageLimitToneClass(item.limit, 'subscription-usage_bar')">
                  {{ usageLimitStatusLabel(item.limit) }}
                </span>
              </div>

              <div class="subscription-usage_track">
                <div
                  class="subscription-usage_bar"
                  :class="usageLimitToneClass(item.limit, 'subscription-usage_bar')"
                  :style="{ width: usageLimitWidth(item.limit) }"></div>
              </div>
            </div>
          </div>

          <div class="subscription-overview_footnote">
            <router-link
              v-if="showUpgradeCta"
              :to="{ name: 'Subscription' }"
              class="btn btn-primary btn-sm subscription-overview_cta">
              Upgrade to Pro
            </router-link>
          </div>
        </div>
      </div>

      <!-- If user is actively billing, show billing info and actions -->
      <div v-if="isActivelyBilling" class="m-5 text-center">
        <h3>
          You are currently on the {{ currentPlanLabel }} plan
          <span v-if="billingPlanLabel">with {{ billingPlanLabel.toLowerCase() }} billing</span>
        </h3>

        <!-- Grace period alert -->
        <div v-if="isInGracePeriod" class="alert alert-primary" role="alert">
          <i class="fa-solid fa-exclamation-circle"></i> Your subscription has been canceled. You still have Pro access
          until <b>{{ gracePeriodEndsAt }}</b> during your grace period.
        </div>
        <div v-if="subscription.created_at" class="alert alert-success" role="alert">
          <i class="fa-solid fa-exclamation-circle"> </i> Your DayWright subscription started
          <b>{{ subscription.created_at }}</b>
        </div>

        <!-- Subscription actions (swap/cancel) -->
        <div v-if="!isInGracePeriod && billingPlan">
          <p v-if="alternateBillingPlan">
            <button class="btn btn-lg btn-link" @click.prevent="swap(alternateBillingPlan.name)">
              Change Subscription to {{ alternateBillingPlan.label }} ({{ formatPlanPrice(alternateBillingPlan) }}/{{
                alternateBillingPlan.interval_label
              }})
            </button>
          </p>
          <p>
            <button class="btn btn-sm btn-danger" @click.prevent="cancelSubscription()">Cancel Subscription</button>
          </p>
        </div>
      </div>

      <!-- If not subscribed, show available plans -->
      <div v-else class="row m-5 subscription-plans align-items-stretch">
        <div v-for="plan in availablePlans" :key="plan.name" class="col-md-6 mb-4">
          <div
            class="card text-center h-100 subscription-plan-card"
            :class="{ 'subscription-plan-card-featured border border-primary': plan.featured }">
            <div class="card-body d-flex flex-column p-4">
              <div class="mb-3">
                <p class="card-title subscription_heading mb-0">{{ plan.label }} Subscription</p>
              </div>

              <div class="mb-4">
                <span class="subscription_value">{{ formatPlanPrice(plan) }}</span>
                <span class="text-muted ml-2">/ {{ plan.interval_label }}</span>
              </div>

              <button
                v-if="isFreeUser"
                class="btn btn-primary btn-lg btn-block mt-auto"
                @click="subscribe(plan.name)"
                :disabled="isIframeOpen || isOpeningIframe">
                Subscribe
              </button>
            </div>
          </div>
        </div>

        <div v-if="availablePlans.length === 0" class="col-12">
          <div class="alert alert-info mb-0">
            Subscription plans are temporarily unavailable. Please try again shortly.
          </div>
        </div>

        <!-- Modal overlay for payment iframe -->
        <div
          v-if="isIframeOpen"
          class="subscription-modal-overlay"
          @click.self="closeIframe"
          aria-modal="true"
          role="dialog">
          <button @click="closeIframe" class="subscription-modal-close" aria-label="Close payment window">
            <span aria-hidden="true">&times;</span>
          </button>

          <!-- Paddle payment iframe -->
          <iframe
            :src="$safeUrl(iframeSrc)"
            class="subscription-modal-iframe"
            title="Paddle payment"
            @load="isOpeningIframe = false"></iframe>

          <!-- Spinner while iframe is loading -->
          <div v-if="isOpeningIframe" class="subscription-modal-spinner">Loading...</div>

          <!-- Note for closing the modal -->
          <div class="subscription-modal-note">To close this window, use the Close button above.</div>
        </div>

        <!-- Loading overlay before modal opens -->
        <div v-if="isOpeningIframe && !isIframeOpen" class="subscription-modal-overlay" aria-modal="true" role="dialog">
          <div class="subscription-modal-spinner">Loading...</div>
        </div>
      </div>

      <!-- Receipts section -->
      <div class="row">
        <div v-if="hasReceipts" class="col-md-6">
          <h3>Receipts</h3>
          <div class="card">
            <div class="card-body">
              <div v-for="receipt in subscription.receipts" :key="receipt.id">
                <p>
                  <span>{{ receipt.created_at }}</span> -
                  <span>${{ receipt.amount }} {{ receipt.currency }}</span>
                  <span class="float-right">
                    <a :href="$safeUrl(receipt.receipt_url)" target="_blank" rel="noopener noreferrer">Download</a>
                  </span>
                </p>
              </div>
            </div>
          </div>
        </div>
        <div v-else class="margin-small col-md-6">
          <h3>Receipts</h3>
          <div class="alert alert-info">Your receipts will appear here after your first payment is processed.</div>
        </div>
        <div class="col-md-6" v-if="subscription.next_payment">
          <h3>Next Payment</h3>
          <div class="alert alert-primary mt-2" role="alert">
            <p>
              Your next payment is scheduled for <b>{{ subscription.next_payment.date | receipt_date }}</b> in the
              amount of <b>{{ subscription.next_payment.amount }}</b> {{ subscription.next_payment.currency }}
            </p>
            <ul>
              <li>
                If you cancel your subscription, you will not be charged for the next billing cycle, but you will
                continue to have access until the end of your current period (grace period).
              </li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { mapState, mapMutations, mapGetters } from 'vuex';
import alertNotice from '../mixins/alertNotice';
import usageLimitHelpers from '../mixins/usageLimitHelpers';
import { toastInfo, toastSuccess } from '../utils/toast';

export default {
  name: 'Subscription',
  mixins: [alertNotice, usageLimitHelpers],
  // Component state
  data() {
    return {
      // Modal and payment state
      isIframeOpen: false,
      isOpeningIframe: false,
      iframeSrc: '',
    };
  },

  // Computed properties for derived state
  computed: {
    ...mapState('subscribeUser', ['subscription']),
    ...mapGetters('subscribeUser', [
      'accountLimits',
      'isActivelyBilling',
      'isInGracePeriod',
      'isOnTrial',
      'plan',
      'isFreeUser',
    ]),

    availablePlans() {
      return Array.isArray(this.subscription?.available_plans) ? this.subscription.available_plans : [];
    },

    currentPlanLabel() {
      return this.plan === 'pro' ? 'Pro' : 'Free';
    },

    billingPlan() {
      return this.subscription.billing_plan || null;
    },

    billingPlanLabel() {
      if (this.billingPlan === 'monthly') {
        return 'Monthly';
      }

      if (this.billingPlan === 'yearly') {
        return 'Yearly';
      }

      return null;
    },

    alternateBillingPlan() {
      if (!this.billingPlan) {
        return null;
      }

      const targetPlan = this.billingPlan === 'monthly' ? 'yearly' : 'monthly';

      return this.availablePlans.find((plan) => plan.name === targetPlan) || null;
    },

    trialEndsAt() {
      return this.subscription?.trial?.ends_at || null;
    },

    gracePeriodEndsAt() {
      return this.subscription?.grace_period?.ends_at || null;
    },

    accountUsageItems() {
      return [
        {
          key: 'projects',
          label: 'Projects',
          limit: this.accountLimits.projects || { used: 0, max: null },
        },
        {
          key: 'created_meetings',
          label: 'Created meetings',
          limit: this.accountLimits.created_meetings || { used: 0, max: null },
        },
        {
          key: 'api_tokens',
          label: 'API tokens',
          limit: this.accountLimits.api_tokens || { used: 0, max: null },
        },
      ];
    },

    showUpgradeCta() {
      return this.plan === 'free';
    },

    // Whether the user has any receipts
    hasReceipts() {
      return this.subscription && Array.isArray(this.subscription.receipts) && this.subscription.receipts.length > 0;
    },
  },

  // Watchers
  watch: {
    // Prevent background scroll when modal is open
    isIframeOpen(val) {
      document.body.classList.toggle('modal-open', val);
    },
  },

  // Lifecycle hook: fetch subscription info on mount
  mounted() {
    this.fetchSubscription();
  },

  // Methods
  methods: {
    ...mapMutations('subscribeUser', ['setSubscription']),

    formatPlanPrice(plan) {
      if (!plan) {
        return '$0';
      }

      return `${plan.currency_symbol || '$'}${plan.price}`;
    },

    // Fetch the user's subscription info from the API
    async fetchSubscription() {
      try {
        const response = await axios.get('/user/subscriptions');
        this.setSubscription(response.data.subscription);
      } catch (error) {
        this.showError(error);
      }
    },

    // Close the payment modal
    closeIframe() {
      this.isIframeOpen = false;
    },

    // Start the subscription process for a plan
    async subscribe(plan) {
      if (this.isIframeOpen || this.isOpeningIframe) {
        return;
      }
      this.isOpeningIframe = true;
      try {
        const response = await axios.get(`/user/subscribe/${encodeURIComponent(plan)}`);
        this.iframeSrc = response.data.paylink;
        this.isIframeOpen = true;
      } catch (error) {
        this.showError(error);
      } finally {
        this.isOpeningIframe = false;
      }
    },

    // Swap the user's subscription plan
    async swap(plan) {
      const result = await this.sweetAlert('Switch to ' + plan + ' plan');
      if (result.value) {
        this.$Progress.start();
        try {
          const response = await axios.get(`/user/subscription/swap/${encodeURIComponent(plan)}`);
          this.setSubscription(response.data.subscription);
          toastSuccess(response.data.message);
          // Wait 5 seconds, then refresh subscription data once
          setTimeout(() => {
            this.fetchSubscription();
          }, 5000);
        } catch (error) {
          this.showError(error);
        } finally {
          this.$Progress.finish();
        }
      }
    },

    // Cancel the user's subscription
    async cancelSubscription() {
      const plan = this.billingPlan;

      if (!plan) {
        return;
      }

      const result = await this.sweetAlert('Yes, Cancel Subscription');
      if (result.value) {
        this.$Progress.start();
        try {
          const response = await axios.get(`/user/subscription/${encodeURIComponent(plan)}/cancel`);
          this.setSubscription(response.data.subscription);
          toastInfo(response.data.message);
        } catch (error) {
          this.showError(error);
          this.$Progress.fail();
        } finally {
          this.$Progress.finish();
        }
      }
    },

    // Show error messages from API responses
    showError(error) {
      this.handleErrorResponse(error);
    },
  },
};
</script>
