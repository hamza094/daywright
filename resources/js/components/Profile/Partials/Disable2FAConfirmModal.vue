<template>
  <div>
    <div
      class="modal fade"
      tabindex="-1"
      :class="{ show: show }"
      style="display: block"
      v-if="show"
      aria-modal="true"
      role="dialog"
      @click.self="$emit('cancel')">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">
              <i class="bi bi-exclamation-triangle me-2 text-warning" aria-hidden="true"></i>
              Disable Two-Factor Authentication
            </h5>
            <button type="button" class="btn-close" @click="$emit('cancel')" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div class="alert alert-warning" role="alert">
              <i class="bi bi-shield-x me-2" aria-hidden="true"></i>
              <strong>Security Warning:</strong> Disabling 2FA will make your account less secure.
            </div>
            <p class="mb-3">To disable Two-Factor Authentication, please verify your identity:</p>

            <form @submit.prevent="handleSubmit">
              <div class="mb-3">
                <label for="disable-password" class="form-label">Current Password</label>
                <input
                  id="disable-password"
                  ref="passwordInput"
                  type="password"
                  v-model="credentials.password"
                  class="form-control"
                  placeholder="Enter your current password"
                  autocomplete="current-password"
                  required
                  :class="{ 'is-invalid': errors.current_password }"
                  :disabled="loading" />
                <div v-if="errors.current_password" class="invalid-feedback">
                  {{ Array.isArray(errors.current_password) ? errors.current_password[0] : errors.current_password }}
                </div>
              </div>

              <div class="mb-3">
                <label for="disable-code" class="form-label">2FA Code</label>
                <input
                  id="disable-code"
                  ref="codeInput"
                  type="text"
                  v-model="credentials.code"
                  class="form-control"
                  placeholder="Enter your 6-digit code or recovery code"
                  autocomplete="one-time-code"
                  required
                  :class="{ 'is-invalid': errors.code }"
                  :disabled="loading" />
                <div v-if="errors.code" class="invalid-feedback">
                  {{ Array.isArray(errors.code) ? errors.code[0] : errors.code }}
                </div>
              </div>
            </form>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" @click="$emit('cancel')" :disabled="loading">Cancel</button>
            <button
              type="button"
              class="btn btn-danger"
              :disabled="loading || !credentials.password || !credentials.code"
              @click="handleSubmit">
              <span
                v-if="loading"
                class="spinner-border spinner-border-sm me-1"
                role="status"
                aria-hidden="true"></span>
              <i v-else class="bi bi-shield-x me-1" aria-hidden="true"></i>
              Disable 2FA
            </button>
          </div>
        </div>
      </div>
    </div>

    <div v-if="show" class="modal-backdrop fade show" @click="$emit('cancel')"></div>
  </div>
</template>

<script>
export default {
  name: 'Disable2FAConfirmModal',
  props: {
    show: {
      type: Boolean,
      default: false,
    },
    loading: {
      type: Boolean,
      default: false,
    },
    errors: {
      type: Object,
      default: () => ({}),
    },
  },
  emits: ['confirm', 'cancel'],

  data() {
    return {
      credentials: {
        password: '',
        code: '',
      },
    };
  },

  watch: {
    show(newVal) {
      if (newVal) {
        document.body.classList.add('modal-open');
        this.$nextTick().then(() => {
          this.$refs.passwordInput?.focus();
        });
      } else {
        this.resetForm();
        document.body.classList.remove('modal-open');
      }
    },
  },

  mounted() {
    if (this.show) {
      document.body.classList.add('modal-open');
    }
  },

  beforeDestroy() {
    document.body.classList.remove('modal-open');
  },

  methods: {
    resetForm() {
      this.credentials = {
        password: '',
        code: '',
      };
    },

    handleSubmit() {
      if (!this.credentials.password.trim() || !this.credentials.code.trim()) {
        return;
      }

      this.$emit('confirm', { ...this.credentials });
    },
  },
};
</script>
