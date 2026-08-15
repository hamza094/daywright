<template>
  <modal
    name="change-password"
    height="auto"
    :scrollable="true"
    :shift-x="1"
    width="38%"
    class="modal-design"
    :click-to-close="false">
    <div class="edit-border-top p-3 animate__animated animate__slideInRight">
      <div class="edit-border-bottom">
        <div class="panel-top_content">
          <span class="panel-heading">Change Password</span>
          <button class="panel-exit float-right" type="button" @click.prevent="modalClose">x</button>
        </div>
      </div>
      <div class="panel-form">
        <form action="" @submit.prevent="updatePassword">
          <div class="panel-top_content">
            <button @click="toggleShowCurrentPassword" class="eye-icon float-right" type="button">
              {{ showIcon(showCurrentPassword) }}
            </button>

            <form-input
              label="Current Password:"
              v-model="form.current_password"
              :error="errors.current_password"
              :type="currentPasswordFieldType"
              id="current_password" />

            <button @click="toggleShowPassword" class="eye-icon float-right" type="button">
              {{ showIcon(showPassword) }}
            </button>

            <form-input
              label="New Password:"
              v-model="form.password"
              :error="errors.password"
              id="password"
              :type="passwordFieldType" />

            <form-input
              label="Confirm New Password:"
              v-model="form.password_confirmation"
              :error="errors.password_confirmation"
              id="password_confirmation"
              type="password" />
          </div>

          <div class="panel-bottom">
            <div class="panel-top_content float-right">
              <button class="btn panel-btn_close" @click.prevent="modalClose">Cancel</button>
              <button class="btn panel-btn_save" :disabled="loading">
                {{ loading ? 'Updating...' : 'Update Password' }}
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </modal>
</template>

<script>
import FormInput from '../../FormInput.vue';
import { parseApiError } from '../../../utils/apiResponse.js';

export default {
  components: {
    FormInput,
  },

  data() {
    return {
      showCurrentPassword: false,
      showPassword: false,
      loading: false,
      errors: {},
      form: {
        current_password: '',
        password: '',
        password_confirmation: '',
      },
    };
  },
  computed: {
    currentPasswordFieldType() {
      return this.showCurrentPassword ? 'text' : 'password';
    },
    passwordFieldType() {
      return this.showPassword ? 'text' : 'password';
    },
    showIcon: function () {
      return function (show) {
        return show ? '👁' : '🕶';
      };
    },
  },
  methods: {
    toggleShowCurrentPassword() {
      this.showCurrentPassword = !this.showCurrentPassword;
    },

    toggleShowPassword() {
      this.showPassword = !this.showPassword;
    },

    modalClose() {
      this.$modal.hide('change-password');
      this.resetForm();
    },

    updatePassword() {
      this.loading = true;
      this.errors = {};

      axios
        .put('/users/me/password', this.form)
        .then(() => {
          this.$vToastify.success('Password updated successfully');
          this.modalClose();
        })
        .catch((error) => {
          this.handleErrorResponse(error);
          this.errors = parseApiError(error).errors;

          // Handle 403 from first-party auth middleware
          if (error.response?.status === 403) {
            this.$vToastify.error('Password changes are restricted to web sessions and official mobile apps only');
          }
        })
        .finally(() => {
          this.loading = false;
        });
    },

    resetForm() {
      this.form = {
        current_password: '',
        password: '',
        password_confirmation: '',
      };
      this.errors = {};
    },
  },
};
</script>

<style>
.eye-icon {
  cursor: pointer;
  margin-left: 10px;
  background: none;
  border: none;
  padding: 0;
  font-size: inherit;
}
</style>
