<template>
  <div>
    <!-- Page Title -->
    <div class="page-top margin-small">Your Membership</div>

    <div class="container">
      <!-- If user is subscribed, show subscription info and actions -->
      <div v-if="isSubscribed" class="m-5 text-center">
        <h3>You are currently subscribed to our {{ subscription.plan }} plan</h3>

        <!-- Grace period alert -->
        <div v-if="subscription.grace_period" class="alert alert-primary" role="alert">
          <i class="fa-solid fa-exclamation-circle"></i> Alert: Your subscription has been canceled, and you are
          currently in the grace period.which is valid till <b>{{ subscription.grace_period_ends_at }}</b> Please note
          that during this time, you still have access to all subscription benefits.
        </div>
        <div v-if="subscription" class="alert alert-success" role="alert">
          <i class="fa-solid fa-exclamation-circle"> </i> You have created DayWright Subscription
          <b> {{ subscription.created_at }}</b>
        </div>

        <!-- Subscription actions (swap/cancel) -->
        <div v-if="!subscription.grace_period">
          <p>
            <button v-if="subscription.plan === 'monthly'" class="btn btn-lg btn-link" @click.prevent="swap('yearly')">
              Change Subscription to Yearly ($100/year)
            </button>
            <button v-else class="btn btn-lg btn-link" @click.prevent="swap('monthly')">
              Change Subscription to Monthly ($12/month)
            </button>
          </p>
          <p>
            <button class="btn btn-sm btn-danger" @click.prevent="cancelSubscription()">Cancel Subscription</button>
          </p>
        </div>
      </div>

      <!-- If not subscribed, show available plans -->
      <div v-else class="row m-5 subscription-plans align-items-stretch">
        <div v-for="plan in plans" :key="plan.name" class="col-md-6 mb-4">
          <div
            class="card text-center h-100 subscription-plan-card"
            :class="{ 'subscription-plan-card-featured border border-primary': plan.featured }">
            <div class="card-body d-flex flex-column p-4">
              <div class="mb-3">
                <p class="card-title subscription_heading mb-0">{{ plan.label }} Subscription</p>
              </div>

              <div class="mb-4">
                <span class="subscription_value">${{ plan.price }}</span>
                <span class="text-muted ml-2">/ {{ plan.intervalLabel }}</span>
              </div>

              <button
                class="btn btn-primary btn-lg btn-block mt-auto"
                @click="subscribe(plan.name)"
                :disabled="isIframeOpen || isOpeningIframe">
                Subscribe
              </button>
            </div>
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
            :src="$options.filters.safeUrl(iframeSrc)"
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
                    <a :href="$options.filters.safeUrl(receipt.receipt_url)" target="_blank" rel="noopener noreferrer"
                      >Download</a
                    >
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
          <h3>Important Notice !</h3>
          <div class="alert alert-primary mt-2" role="alert">
            <p>
              Your Next payment is scheduled on <b>{{ subscription.next_payment.date | reciept_date }}</b> with an
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
import { mapState, mapMutations } from 'vuex';

export default {
  // Component state
  data() {
    return {
      // Modal and payment state
      isIframeOpen: false,
      isOpeningIframe: false,
      iframeSrc: '',

      // Available plans
      plans: [
        { name: 'monthly', label: 'Monthly', intervalLabel: 'month', price: 12, featured: false },
        { name: 'yearly', label: 'Yearly', intervalLabel: 'year', price: 100, featured: true },
      ],
    };
  },

  // Computed properties for derived state
  computed: {
    ...mapState('subscribeUser', ['subscription']),
    // Whether the user is currently subscribed
    isSubscribed() {
      return !!this.subscription.subscribed;
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
          this.$vToastify.success(response.data.message);
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
      const plan = this.subscription.plan;
      const result = await this.sweetAlert('Yes, Cancel Subscription');
      if (result.value) {
        this.$Progress.start();
        try {
          const response = await axios.get(`/user/subscription/${encodeURIComponent(plan)}/cancel`);
          this.setSubscription(response.data.subscription);
          this.$vToastify.info(response.data.message);
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
      let message = 'An error occurred.';
      if (error.response && error.response.data) {
        if (error.response.data.errors) {
          // Show the first validation error
          const firstError = Object.values(error.response.data.errors)[0][0];
          message = firstError;
        } else if (error.response.data.message) {
          message = error.response.data.message;
        }
      }
      this.$vToastify.error(message);
    },
  },
};
</script>
