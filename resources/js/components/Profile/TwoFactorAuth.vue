<template>
  <!-- Main 2FA Management Card -->
  <div class="card mt-4 twofa-card" role="region" aria-labelledby="twofa-title">
    <!-- Card Header with Title and Loading Indicator -->
    <div class="card-header d-flex align-items-center justify-content-between">
      <h5 id="twofa-title" class="mb-0">
        <i class="bi bi-shield-lock me-2" aria-hidden="true"></i>
        Two-Factor Authentication
      </h5>
      <span
        v-if="loading"
        class="spinner-border spinner-border-sm"
        role="status"
        aria-label="Loading 2FA status"></span>
    </div>

    <!-- Main Content Area -->
    <div class="card-body" v-if="!loading">
      <!-- ===== STEPPER INDICATOR ===== -->
      <section v-if="isInProgress || isEnabled" class="mb-4" aria-label="2FA Setup Progress">
        <div
          class="stepper d-flex align-items-center"
          role="progressbar"
          aria-valuenow="1"
          aria-valuemin="1"
          aria-valuemax="2">
          <!-- Step 1: Setup -->
          <div :class="['step', isEnabled ? 'completed' : isInProgress ? 'active' : '']" aria-label="Setup step">1</div>
          <div class="step-label">Setup</div>
          <div class="step-line" aria-hidden="true"></div>

          <!-- Step 2: Confirm -->
          <div
            :class="['step', isEnabled ? 'completed' : isInProgress ? 'active' : 'disabled']"
            aria-label="Confirm step">
            2
          </div>
          <div class="step-label">Confirm</div>
        </div>
      </section>

      <!-- ===== ERROR DISPLAY ===== -->
      <div v-if="error" class="alert alert-danger mb-3" role="alert" aria-live="polite">
        <i class="bi bi-exclamation-triangle me-2" aria-hidden="true"></i>
        {{ error }}
      </div>

      <!-- ===== ENABLED STATE ===== -->
      <section v-if="isEnabled" aria-label="2FA Enabled Status">
        <!-- Security Information Alert -->
        <div class="alert alert-info mb-4" role="alert">
          <div class="d-flex">
            <i class="bi bi-shield-check me-3 fs-4" aria-hidden="true"></i>
            <div>
              <h6 class="alert-heading mb-2">
                <i class="bi bi-shield-lock me-2" aria-hidden="true"></i>
                Two-Factor Authentication Enabled
              </h6>
              <p class="mb-3">
                Your account is now protected with an additional security layer. Here's what you need to know:
              </p>
              <ul class="mb-0 small">
                <li>
                  <strong>Authenticator App:</strong> Use your authenticator app (Google Authenticator, Authy, etc.) to
                  generate 6-digit codes for login
                </li>
                <li>
                  <strong>Recovery Codes:</strong> Store your recovery codes securely - they're your backup access
                  method if you lose your device
                </li>
                <li><strong>Security:</strong> Never share your 2FA codes or recovery codes with anyone</li>
              </ul>
            </div>
          </div>
        </div>

        <!-- Status Confirmation -->
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
              aria-label="Copy recovery codes to clipboard">
              <i class="bi bi-clipboard me-1" aria-hidden="true"></i>
              Copy
            </button>
            <button
              class="btn btn-outline-secondary btn-sm ms-2"
              @click="toggleShowCodes"
              :aria-label="showCodes ? 'Hide recovery codes' : 'Show recovery codes'">
              <i class="bi" :class="showCodes ? 'bi-eye-slash' : 'bi-eye'" aria-hidden="true"></i>
              {{ showCodes ? 'Hide' : 'Show' }}
            </button>
          </div>

          <!-- Recovery Codes List -->
          <div v-if="showCodes" class="mb-3">
            <ul class="list-group" role="list" aria-label="Recovery codes list">
              <li
                v-for="(rc, index) in recoveryCodes"
                :key="rc.code"
                class="list-group-item d-flex justify-content-between align-items-center"
                role="listitem">
                <span class="font-monospace">{{ rc.code }}</span>
                <small class="text-muted">Code {{ index + 1 }}</small>
              </li>
            </ul>
          </div>

          <!-- Action Buttons -->
          <div class="d-flex gap-2 flex-wrap">
            <button
              class="btn btn-warning btn-sm"
              :disabled="regenerateLoading"
              @click="regenerateCodes"
              aria-label="Regenerate recovery codes">
              <span
                v-if="regenerateLoading"
                class="spinner-border spinner-border-sm me-1"
                role="status"
                aria-hidden="true"></span>
              <i v-else class="bi bi-arrow-clockwise me-1" aria-hidden="true"></i>
              Regenerate
            </button>
            <button
              class="btn btn-danger btn-sm"
              :disabled="disableLoading"
              @click="showDisableConfirm = true"
              aria-label="Disable 2FA">
              <span
                v-if="disableLoading"
                class="spinner-border spinner-border-sm me-1"
                role="status"
                aria-hidden="true"></span>
              <i v-else class="bi bi-shield-x me-1" aria-hidden="true"></i>
              Disable
            </button>
          </div>
        </section>
      </section>

      <!-- ===== IN PROGRESS STATE ===== -->
      <section v-else-if="isInProgress" aria-label="2FA Setup in Progress">
        <!-- QR Code Display -->
        <div v-if="qrCode" class="text-center my-4">
          <div class="mb-3">
            <h6 class="mb-2">
              <i class="bi bi-qr-code me-2" aria-hidden="true"></i>
              Scan QR Code
            </h6>
            <p class="text-muted small">Scan this QR code with your authenticator app</p>
          </div>
          <div v-safe-html="qrCode" aria-label="QR code for 2FA setup"></div>
        </div>

        <!-- Setup Instructions -->
        <div class="mb-4">
          <p class="text-muted">
            <i class="bi bi-info-circle me-2" aria-hidden="true"></i>
            Two-Factor Authentication setup is <strong>in progress</strong>. Enter the 6-digit code from your
            authenticator app.
          </p>
        </div>

        <!-- Code Entry Form -->
        <form @submit.prevent="verify2FA" class="mb-3" aria-label="2FA verification form">
          <div class="mb-3">
            <label for="twofa-code" class="form-label">
              <i class="bi bi-key me-2" aria-hidden="true"></i>
              2FA Code
            </label>
            <input
              id="twofa-code"
              type="text"
              v-model="code"
              class="form-control"
              placeholder="Enter 6-digit code"
              maxlength="6"
              autocomplete="one-time-code"
              required
              aria-describedby="code-error"
              @input="validateCode" />
            <div v-if="codeError" id="code-error" class="text-danger small mt-1">
              <i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>
              {{ codeError }}
            </div>
          </div>

          <button
            class="btn btn-primary w-100 mb-2"
            type="submit"
            :disabled="verifyLoading || !!codeError"
            aria-label="Verify 2FA code">
            <span
              v-if="verifyLoading"
              class="spinner-border spinner-border-sm me-2"
              role="status"
              aria-hidden="true"></span>
            <i v-else class="bi bi-check-circle me-2" aria-hidden="true"></i>
            Verify Code
          </button>
        </form>

        <!-- Cancel Setup Button -->
        <button
          class="btn btn-danger btn-sm w-100"
          :disabled="disableLoading"
          @click="showDisableConfirm = true"
          aria-label="Cancel 2FA setup">
          <span
            v-if="disableLoading"
            class="spinner-border spinner-border-sm me-1"
            role="status"
            aria-hidden="true"></span>
          <i v-else class="bi bi-x-circle me-1" aria-hidden="true"></i>
          Cancel Setup
        </button>
      </section>

      <!-- ===== DISABLED STATE ===== -->
      <section v-else aria-label="2FA Disabled Status">
        <div class="text-center py-4">
          <div class="mb-4">
            <i class="bi bi-shield-x fs-1 text-muted mb-3" aria-hidden="true"></i>
            <h6 class="text-muted">Two-Factor Authentication is <strong>disabled</strong></h6>
            <p class="text-muted">Enable 2FA to add an extra layer of security to your account.</p>
          </div>

          <button class="btn btn-primary" @click="openModal">
            <i class="bi bi-shield-lock me-2" aria-hidden="true"></i>
            Enable Two-Factor Authentication
          </button>
        </div>
      </section>
    </div>

    <!-- ===== MODALS ===== -->
    <!-- Password Confirmation Modal -->
    <ConfirmPasswordModal @submit="enable2FA" :loading="enableLoading" />

    <!-- Disable 2FA Confirmation Modal -->
    <Disable2FAConfirmModal
      :show="showDisableConfirm"
      @cancel="showDisableConfirm = false"
      @confirm="confirmDisable2FA"
      :loading="disableLoading" />
  </div>
</template>

<script>
import ConfirmPasswordModal from './Partials/ConfirmPasswordModal.vue';
import Disable2FAConfirmModal from './Partials/Disable2FAConfirmModal.vue';
export default {
  name: 'TwoFactorAuth',
  components: {
    ConfirmPasswordModal,
    Disable2FAConfirmModal,
  },
  data() {
    return {
      status: '',
      qrCode: null,
      code: '',
      codeError: '',
      recoveryCodes: [],
      loading: false,
      verifyLoading: false,
      disableLoading: false,
      regenerateLoading: false,
      enableLoading: false,
      error: '',
      showCodes: false,
      showDisableConfirm: false,
      recoveryCodesTooltip:
        'These are one-time use codes you can use to access your account if you lose access to your authenticator app. Store them securely.',
    };
  },
  computed: {
    isEnabled() {
      return this.status === 'enabled';
    },
    isInProgress() {
      return this.status === 'in_progress';
    },
    isDisabled() {
      return !this.status || this.status === 'disabled';
    },
  },
  mounted() {
    this.check2FAStatus();
    document.addEventListener('keydown', this.handleEscape);
    // Initialize Bootstrap tooltips
    this.$nextTick().then(() => {
      if (window.bootstrap) {
        const tooltipTriggerList = Array.from(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.forEach(function (tooltipTriggerEl) {
          new window.bootstrap.Tooltip(tooltipTriggerEl);
        });
      }
    });
  },
  beforeDestroy() {
    document.removeEventListener('keydown', this.handleEscape);
  },
  methods: {
    handleEscape(e) {
      if (e.key === 'Escape') {
        this.closeModal();
        this.showDisableConfirm = false;
      }
    },
    openModal() {
      this.$modal.show('ConfirmPassword');
    },
    closeModal() {
      this.$modal.hide('ConfirmPassword');
    },
    toggleShowCodes() {
      this.showCodes = !this.showCodes;
    },
    copyRecoveryCodes() {
      const codes = this.recoveryCodes.map((code) => code.code).join('\n');
      navigator.clipboard.writeText(codes).then(
        () => {
          this.$vToastify.success('Recovery codes copied to clipboard!');
        },
        () => {
          this.$vToastify.warning('Failed to copy recovery codes.');
        },
      );
    },
    confirmDisable2FA() {
      this.showDisableConfirm = false;
      this.disable2FA();
    },
    validateCode() {
      this.code = this.code.replace(/[^0-9]/g, '').slice(0, 6);
      if (this.code.length > 0 && this.code.length < 6) {
        this.codeError = 'Code must be 6 digits.';
      } else {
        this.codeError = '';
      }
    },
    extractError(e, fallback = 'An error occurred.') {
      return (
        e.response?.data?.errors?.code?.[0] ||
        e.response?.data?.errors?.two_factor?.[0] ||
        e.response?.data?.message ||
        fallback
      );
    },
    resetState(loadingKey) {
      this.error = '';
      if (loadingKey) this[loadingKey] = true;
    },
    async handleApiCall(apiFn, args = [], onSuccess = () => {}, loadingKey = '') {
      this.error = '';
      if (loadingKey) this[loadingKey] = true;
      try {
        const res = await apiFn(...args);
        await onSuccess(res);
      } catch (e) {
        this.handleErrorResponse(e);
        this.error = this.extractError(e);
      } finally {
        if (loadingKey) this[loadingKey] = false;
      }
    },
    async check2FAStatus() {
      await this.handleApiCall(
        () => axios.get('/twofactor/fetch-user'),
        [],
        async (res) => {
          this.status = res.data.status;
          if (this.status === 'in_progress') {
            this.qrCode = res.data.qr_code;
          }
          if (this.status === 'enabled') {
            // Always fetch recovery codes when enabled
            await this.fetchRecoveryCodes();
          }
        },
        'loading',
      );
    },
    async fetchRecoveryCodes() {
      await this.handleApiCall(
        () => axios.get('/twofactor/recovery-codes'),
        [],
        (res) => {
          this.recoveryCodes = res.data.recoveryCodes;
        },
      );
    },
    async enable2FA(password) {
      await this.handleApiCall(
        () => axios.post('/twofactor/setup', { password }),
        [],
        (res) => {
          this.status = res.data.status;
          this.qrCode = res.data.qr_code;
          this.$vToastify.success('2FA setup started.');
          this.closeModal();
        },
        'enableLoading',
      );
    },
    async verify2FA() {
      await this.handleApiCall(
        () => axios.post('/twofactor/confirm', { code: this.code }),
        [],
        async (res) => {
          this.recoveryCodes = res.data.recoveryCodes;
          this.status = res.data.status;
          this.qrCode = null;
          this.code = '';
          this.$vToastify.success('2FA successfully verified.');
        },
        'verifyLoading',
      );
    },
    async regenerateCodes() {
      await this.handleApiCall(
        () => axios.get('/twofactor/recovery-codes'),
        [],
        (res) => {
          this.recoveryCodes = res.data.recoveryCodes;
          this.$vToastify.success('Recovery codes regenerated.');
        },
        'regenerateLoading',
      );
    },
    async disable2FA() {
      await this.handleApiCall(
        () => axios.delete('/twofactor/disable'),
        [],
        (res) => {
          this.status = res.data.status;
          this.recoveryCodes = [];
          this.qrCode = null;
          this.code = '';
          this.$vToastify.success(res.data.message);
        },
        'disableLoading',
      );
    },
  },
};
</script>
