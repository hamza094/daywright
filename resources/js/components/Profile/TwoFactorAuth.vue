<template>
  <div class="card mt-4 twofa-card" role="region" aria-labelledby="twofa-title">
    <div class="card-header d-flex align-items-center justify-content-between">
      <h5 id="twofa-title" class="mb-0">
        <i class="bi bi-shield-lock me-2" aria-hidden="true"></i>
        Two-Factor Authentication
      </h5>
      <span
        v-if="twoFactorLoading"
        class="spinner-border spinner-border-sm"
        role="status"
        aria-label="Loading 2FA status"></span>
    </div>

    <div class="card-body" v-if="!twoFactorLoading">
      <!-- Error Display -->
      <div v-if="twoFactorError" class="alert alert-danger mb-3" role="alert" aria-live="polite">
        <i class="bi bi-exclamation-triangle me-2" aria-hidden="true"></i>
        {{ twoFactorError }}
      </div>

      <!-- Enabled State -->
      <section v-if="isTwoFactorEnabled" aria-label="2FA Enabled Status">
        <div class="alert alert-info mb-4" role="alert">
          <div class="d-flex">
            <i class="bi bi-shield-check me-3 fs-4" aria-hidden="true"></i>
            <div>
              <h6 class="alert-heading mb-2">
                <i class="bi bi-shield-lock me-2" aria-hidden="true"></i>
                Two-Factor Authentication Enabled
              </h6>
              <p class="mb-3">Your account is now protected with an additional security layer.</p>
              <ul class="mb-0 small">
                <li><strong>Authenticator App:</strong> Use your authenticator app to generate 6-digit codes</li>
                <li><strong>Recovery Codes:</strong> Store your recovery codes securely as backup access</li>
                <li><strong>Security:</strong> Never share your 2FA codes or recovery codes</li>
              </ul>
            </div>
          </div>
        </div>

        <div class="mb-4">
          <p class="text-success mb-0">
            <i class="bi bi-check-circle me-2" aria-hidden="true"></i>
            <strong>2FA is enabled</strong> on your account.
          </p>
        </div>

        <!-- Recovery Codes Section -->
        <section class="mb-4" aria-label="Recovery Codes Management">
          <div class="d-flex align-items-center mb-3">
            <h6 class="mb-0 me-2">
              <i class="bi bi-key me-2" aria-hidden="true"></i>
              Recovery Codes
              <i
                class="bi bi-info-circle ms-1"
                tabindex="0"
                role="button"
                data-bs-toggle="tooltip"
                :title="recoveryCodesTooltip"
                aria-label="Information about recovery codes"></i>
            </h6>
            <button
              class="btn btn-outline-secondary btn-sm ms-2"
              @click="copyRecoveryCodes"
              aria-label="Copy recovery codes">
              <i class="bi bi-clipboard me-1" aria-hidden="true"></i> Copy
            </button>
            <button
              class="btn btn-outline-secondary btn-sm ms-2"
              @click="toggleRecoveryCodesVisibility(openPasswordModal)"
              :aria-label="showRecoveryCodes ? 'Hide recovery codes' : 'Show recovery codes'">
              <i class="bi" :class="showRecoveryCodes ? 'bi-eye-slash' : 'bi-eye'" aria-hidden="true"></i>
              {{ showRecoveryCodes ? 'Hide' : 'Show' }}
            </button>
          </div>

          <div v-if="showRecoveryCodes" class="mb-3">
            <ul class="list-group" role="list" aria-label="Recovery codes list">
              <li
                v-for="(rc, index) in recoveryCodes"
                :key="rc"
                class="list-group-item d-flex justify-content-between align-items-center"
                role="listitem">
                <span class="font-monospace">{{ rc }}</span>
                <small class="text-muted">Code {{ index + 1 }}</small>
              </li>
            </ul>
          </div>

          <div class="d-flex gap-2 flex-wrap">
            <button
              class="btn btn-warning btn-sm"
              :disabled="twoFactorRegenerateLoading"
              @click="openPasswordModal('regenerate')"
              aria-label="Regenerate recovery codes">
              <span
                v-if="twoFactorRegenerateLoading"
                class="spinner-border spinner-border-sm me-1"
                role="status"
                aria-hidden="true"></span>
              <i v-else class="bi bi-arrow-clockwise me-1" aria-hidden="true"></i> Regenerate
            </button>
            <button
              class="btn btn-danger btn-sm"
              :disabled="twoFactorDisableLoading"
              @click="showDisableConfirm = true"
              aria-label="Disable 2FA">
              <span
                v-if="twoFactorDisableLoading"
                class="spinner-border spinner-border-sm me-1"
                role="status"
                aria-hidden="true"></span>
              <i v-else class="bi bi-shield-x me-1" aria-hidden="true"></i> Disable
            </button>
          </div>
        </section>
      </section>

      <!-- In Progress State -->
      <section v-else-if="isTwoFactorInProgress" aria-label="2FA Setup in Progress">
        <div v-if="twoFactorQrCode" class="text-center my-4">
          <div class="mb-3">
            <h6 class="mb-2"><i class="bi bi-qr-code me-2" aria-hidden="true"></i> Scan QR Code</h6>
            <p class="text-muted small">Scan this QR code with your authenticator app</p>
          </div>
          <div v-safe-html="twoFactorQrCode" aria-label="QR code for 2FA setup"></div>
        </div>

        <div class="mb-4">
          <p class="text-muted">
            <i class="bi bi-info-circle me-2" aria-hidden="true"></i>
            Enter the 6-digit code from your authenticator app.
          </p>
        </div>

        <form @submit.prevent="verifyTwoFactor" class="mb-3" aria-label="2FA verification form">
          <div class="mb-3">
            <label for="twofa-code" class="form-label"
              ><i class="bi bi-key me-2" aria-hidden="true"></i> 2FA Code</label
            >
            <input
              id="twofa-code"
              type="text"
              v-model="twoFactorCode"
              class="form-control"
              placeholder="Enter 6-digit code"
              maxlength="6"
              autocomplete="one-time-code"
              required
              aria-describedby="code-error"
              @input="validateTwoFactorCode" />
            <div v-if="twoFactorCodeError" id="code-error" class="text-danger small mt-1">
              <i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i> {{ twoFactorCodeError }}
            </div>
          </div>

          <button
            class="btn btn-primary w-100 mb-2"
            type="submit"
            :disabled="twoFactorVerifyLoading || !!twoFactorCodeError"
            aria-label="Verify 2FA code">
            <span
              v-if="twoFactorVerifyLoading"
              class="spinner-border spinner-border-sm me-2"
              role="status"
              aria-hidden="true"></span>
            <i v-else class="bi bi-check-circle me-2" aria-hidden="true"></i> Verify Code
          </button>
        </form>

        <button
          class="btn btn-danger btn-sm w-100"
          :disabled="twoFactorDisableLoading"
          @click="showDisableConfirm = true"
          aria-label="Cancel 2FA setup">
          <span
            v-if="twoFactorDisableLoading"
            class="spinner-border spinner-border-sm me-1"
            role="status"
            aria-hidden="true"></span>
          <i v-else class="bi bi-x-circle me-1" aria-hidden="true"></i> Cancel Setup
        </button>
      </section>

      <!-- Disabled State -->
      <section v-else aria-label="2FA Disabled Status">
        <div class="text-center py-4">
          <div class="mb-4">
            <i class="bi bi-shield-x fs-1 text-muted mb-3" aria-hidden="true"></i>
            <h6 class="text-muted">Two-Factor Authentication is <strong>disabled</strong></h6>
            <p class="text-muted">Enable 2FA to add an extra layer of security to your account.</p>
          </div>
          <button class="btn btn-primary" @click="openPasswordModal('enable')">
            <i class="bi bi-shield-lock me-2" aria-hidden="true"></i> Enable Two-Factor Authentication
          </button>
        </div>
      </section>
    </div>

    <!-- Modals -->
    <ConfirmPasswordModal @submit="handlePasswordSubmit" :loading="passwordModalLoading" />
    <Disable2FAConfirmModal
      :show="showDisableConfirm"
      @cancel="showDisableConfirm = false"
      @confirm="handleDisableConfirm"
      :loading="twoFactorDisableLoading"
      :errors="disable2FAErrors" />
  </div>
</template>

<script>
import ConfirmPasswordModal from './Partials/ConfirmPasswordModal.vue';
import Disable2FAConfirmModal from './Partials/Disable2FAConfirmModal.vue';
import twoFactorAuthMixin from '../mixins/twoFactorAuthMixin.js';

export default {
  name: 'TwoFactorAuth',
  components: {
    ConfirmPasswordModal,
    Disable2FAConfirmModal,
  },
  mixins: [twoFactorAuthMixin],

  data() {
    return {
      passwordModalLoading: false,
      passwordModalAction: null,
    };
  },

  mounted() {
    this.checkTwoFactorStatus();
    this.initializeTooltips();
    this.setupKeyboardShortcuts();
  },

  beforeDestroy() {
    this.cleanupKeyboardShortcuts();
  },

  methods: {
    initializeTooltips() {
      this.$nextTick().then(() => {
        if (window.bootstrap) {
          const tooltipTriggerList = Array.from(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
          tooltipTriggerList.forEach((tooltipTriggerEl) => {
            new window.bootstrap.Tooltip(tooltipTriggerEl);
          });
        }
      });
    },

    setupKeyboardShortcuts() {
      document.addEventListener('keydown', this.handleKeyboardShortcut);
    },

    cleanupKeyboardShortcuts() {
      document.removeEventListener('keydown', this.handleKeyboardShortcut);
    },

    handleKeyboardShortcut(e) {
      if (e.key === 'Escape') {
        this.showDisableConfirm = false;
      }
    },

    openPasswordModal(action) {
      this.passwordModalAction = action;
      this.$modal.show('ConfirmPassword');
    },

    async handlePasswordSubmit(password) {
      this.$modal.hide('ConfirmPassword');
      this.passwordModalLoading = true;

      try {
        const actionHandlers = {
          enable: () => this.enableTwoFactor(password),
          regenerate: () => this.regenerateRecoveryCodes(password),
          fetch: () => this.fetchRecoveryCodes(password),
        };

        const handler = actionHandlers[this.passwordModalAction];
        if (handler) await handler();
      } finally {
        this.passwordModalLoading = false;
        this.passwordModalAction = null;
      }
    },

    handleDisableConfirm(credentials) {
      this.showDisableConfirm = false;
      this.disableTwoFactor(credentials);
    },
  },
};
</script>
