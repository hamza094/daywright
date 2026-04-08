<template>
  <modal
    name="ConfirmPassword"
    height="auto"
    :scrollable="true"
    width="40%"
    class="modal-design"
    :click-to-close="false">
    <div class="edit-border-top p-3">
      <div class="edit-border-bottom">
        <div class="panel-top_content d-flex justify-content-between align-items-center">
          <span class="panel-heading">Confirm your current password</span>
          <span class="panel-exit" role="button" @click.prevent="closeModal" aria-label="Close">x</span>
        </div>
      </div>
      <div class="panel-form">
        <form @submit.prevent="submitForm">
          <div class="mb-3">
            <label for="confirm-password" class="form-label">Current Password</label>
            <input
              id="confirm-password"
              type="password"
              v-model="password"
              class="form-control"
              placeholder="Enter your current password"
              autocomplete="current-password"
              required
              aria-describedby="password-error"
              :class="{ 'is-invalid': passwordError }" />
            <div v-if="passwordError" id="password-error" class="invalid-feedback">
              {{ passwordError }}
            </div>
          </div>
          <div class="d-flex justify-content-end gap-2">
            <button class="btn btn-secondary" @click.prevent="closeModal" :disabled="loading">Cancel</button>
            <button class="btn btn-success" type="submit" :disabled="loading || !password.trim()">
              <span
                v-if="loading"
                class="spinner-border spinner-border-sm me-1"
                role="status"
                aria-hidden="true"></span>
              <i v-else class="bi bi-check-circle me-1" aria-hidden="true"></i>
              Submit
            </button>
          </div>
        </form>
      </div>
    </div>
  </modal>
</template>

<script>
export default {
  name: 'ConfirmPasswordModal',
  props: {
    loading: {
      type: Boolean,
      default: false,
    },
  },
  emits: ['submit'],
  data() {
    return {
      password: '',
      passwordError: '',
    };
  },
  methods: {
    closeModal() {
      this.$modal.hide('ConfirmPassword');
      this.password = ''; // Reset password on close
      this.passwordError = ''; // Reset error
    },
    submitForm() {
      this.passwordError = ''; // Clear previous errors

      if (!this.password.trim()) {
        this.passwordError = 'Password is required';
        return;
      }

      if (this.password.trim()) {
        this.$emit('submit', this.password);
        this.password = ''; // Reset password after submit
        this.passwordError = ''; // Reset error
      }
    },
  },
};
</script>
