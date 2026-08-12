import { parseApiError } from '../utils/apiResponse.js';
import { parseTwoFactorResponse } from '../utils/authResponse.js';

export default {
  data() {
    return {
      // 2FA State
      twoFactorStatus: '',
      twoFactorQrCode: null,
      twoFactorCode: '',
      twoFactorCodeError: '',
      recoveryCodes: [],

      // Loading states
      twoFactorLoading: false,
      twoFactorVerifyLoading: false,
      twoFactorDisableLoading: false,
      twoFactorRegenerateLoading: false,
      twoFactorEnableLoading: false,

      // UI states
      showRecoveryCodes: false,
      showDisableConfirm: false,
      disable2FAErrors: {},

      // Error handling
      twoFactorError: '',

      // Tooltip text
      recoveryCodesTooltip:
        'These are one-time use codes you can use to access your account if you lose access to your authenticator app. Store them securely.',
    };
  },

  computed: {
    isTwoFactorEnabled() {
      return this.twoFactorStatus === 'enabled';
    },
    isTwoFactorInProgress() {
      return this.twoFactorStatus === 'in_progress';
    },
    isTwoFactorDisabled() {
      return !this.twoFactorStatus || this.twoFactorStatus === 'disabled';
    },
  },

  methods: {
    // Validation
    validateTwoFactorCode() {
      this.twoFactorCode = this.twoFactorCode.replace(/[^0-9]/g, '').slice(0, 6);
      if (this.twoFactorCode.length > 0 && this.twoFactorCode.length < 6) {
        this.twoFactorCodeError = 'Code must be 6 digits.';
      } else {
        this.twoFactorCodeError = '';
      }
    },

    // Recovery codes management
    toggleRecoveryCodesVisibility(openModal) {
      if (!this.showRecoveryCodes && this.recoveryCodes.length === 0) {
        openModal('fetch');
      } else {
        this.showRecoveryCodes = !this.showRecoveryCodes;
      }
    },

    copyRecoveryCodes() {
      const codes = this.recoveryCodes.join('\n');
      navigator.clipboard.writeText(codes).then(
        () => {
          this.$vToastify.success('Recovery codes copied to clipboard!');
        },
        () => {
          this.$vToastify.warning('Failed to copy recovery codes.');
        },
      );
    },

    // Error handling
    extractTwoFactorError(e, fallback = 'An error occurred.') {
      const { errors, message } = parseApiError(e, fallback);
      return errors.code?.[0] || errors.two_factor?.[0] || errors.password?.[0] || message || fallback;
    },

    // API call wrapper
    async handleTwoFactorApiCall(apiFn, onSuccess, loadingKey, onError = null) {
      this.twoFactorError = '';
      if (loadingKey) this[loadingKey] = true;

      try {
        const res = await apiFn();
        await onSuccess(res);
      } catch (e) {
        this.twoFactorError = this.extractTwoFactorError(e);
        if (onError) onError(e);
      } finally {
        if (loadingKey) this[loadingKey] = false;
      }
    },

    // 2FA Operations
    async checkTwoFactorStatus() {
      await this.handleTwoFactorApiCall(
        () => this.$axios.get('/twofactor/fetch-user'),
        (res) => {
          const twoFactor = parseTwoFactorResponse(res);
          this.twoFactorStatus = twoFactor.state;
          if (this.twoFactorStatus === 'in_progress') {
            this.twoFactorQrCode = twoFactor.qrCode;
          }
          if (this.twoFactorStatus === 'enabled') {
            this.recoveryCodes = [];
          }
        },
        'twoFactorLoading',
      );
    },

    async fetchRecoveryCodes(password) {
      await this.handleTwoFactorApiCall(
        () => this.$axios.post('/twofactor/recovery-codes', { current_password: password }),
        (res) => {
          this.recoveryCodes = parseTwoFactorResponse(res).recoveryCodes;
          this.showRecoveryCodes = true;
        },
      );
    },

    async enableTwoFactor(password) {
      await this.handleTwoFactorApiCall(
        () => this.$axios.post('/twofactor/setup', { password }),
        (res) => {
          const twoFactor = parseTwoFactorResponse(res);
          this.twoFactorStatus = twoFactor.state;
          this.twoFactorQrCode = twoFactor.qrCode;
          this.$vToastify.success('2FA setup started.');
        },
        'twoFactorEnableLoading',
      );
    },

    async verifyTwoFactor() {
      await this.handleTwoFactorApiCall(
        () => this.$axios.post('/twofactor/confirm', { code: this.twoFactorCode }),
        (res) => {
          const twoFactor = parseTwoFactorResponse(res);
          this.recoveryCodes = twoFactor.recoveryCodes;
          this.twoFactorStatus = twoFactor.state;
          this.twoFactorQrCode = null;
          this.twoFactorCode = '';
          this.$vToastify.success('2FA successfully verified.');
        },
        'twoFactorVerifyLoading',
      );
    },

    async regenerateRecoveryCodes(password) {
      await this.handleTwoFactorApiCall(
        () => this.$axios.post('/twofactor/recovery-codes', { current_password: password }),
        (res) => {
          this.recoveryCodes = parseTwoFactorResponse(res).recoveryCodes;
          this.$vToastify.success('Recovery codes regenerated.');
        },
        'twoFactorRegenerateLoading',
      );
    },

    async disableTwoFactor(credentials) {
      await this.handleTwoFactorApiCall(
        () =>
          this.$axios.delete('/twofactor/disable', {
            data: {
              current_password: credentials.password,
              code: credentials.code,
            },
          }),
        (res) => {
          this.twoFactorStatus = parseTwoFactorResponse(res).state;
          this.recoveryCodes = [];
          this.twoFactorQrCode = null;
          this.twoFactorCode = '';
          this.twoFactorCodeError = '';
          this.showRecoveryCodes = false;
          this.disable2FAErrors = {};
          this.$vToastify.success('Two-factor authentication disabled.');
        },
        'twoFactorDisableLoading',
        (error) => {
          const { errors } = parseApiError(error);
          this.disable2FAErrors = errors || {};
          this.showDisableConfirm = true;
        },
      );
    },
  },
};
