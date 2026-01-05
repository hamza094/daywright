<template>
  <modal
    name="edit-profile"
    height="auto"
    :scrollable="true"
    :shift-x="1"
    width="38%"
    class="model-desin"
    :click-to-close="false">
    <div class="edit-border-top p-3 animate__animated animate__slideInRight">
      <div class="edit-border-bottom">
        <div class="panel-top_content">
          <span class="panel-heading">Edit Profile {{ user.name }}</span>
          <span class="panel-exit float-right" role="button" @click.prevent="modalClose">x</span>
        </div>
      </div>
      <div class="panel-form">
        <form action="" @submit.prevent="updateProfile">
          <div class="panel-top_content">
            <form-input label="Name:" v-model="form.name" :error="errors.name" id="name" />

            <form-input label="Username:" v-model="form.username" :error="errors.username" id="username" />

            <form-input label="Email:" v-model="form.email" :error="errors.email" id="email" />

            <form-input label="Company:" v-model="form.company" :error="errors.company" id="company" />

            <form-input label="Mobile:" v-model="form.mobile" :error="errors.mobile" id="mobile" />

            <form-input label="Position:" v-model="form.position" :error="errors.position" id="position" />

            <form-input label="Address:" v-model="form.address" :error="errors.address" id="address" />

            <div class="form-group">
              <label for="bio" class="label-name">Your Bio:</label>
              <textarea v-model="form.bio" id="bio" name="bio" class="form-control"></textarea>
              <span class="text-danger font-italic" v-if="errors.bio" v-text="errors.bio[0]"></span>
            </div>
            <hr />
            <h3>Update Password:</h3>

            <span @click="toggleShowCurrentPassword" class="eye-icon float-right" role="button" tabindex="0">{{
              showIcon(showCurrentPassword)
            }}</span>

            <form-input
              label="Current Password:"
              v-model="form.current_password"
              :error="errors.current_password"
              :type="currentPasswordFieldType"
              id="current_password" />

            <span @click="toggleShowPassword" class="eye-icon float-right" role="button" tabindex="0">{{
              showIcon(showPassword)
            }}</span>

            <form-input
              label="New Password:"
              v-model="form.password"
              :error="errors.password"
              id="password"
              :type="passwordFieldType" />
          </div>

          <div class="panel-bottom">
            <div class="panel-top_content float-right">
              <button class="btn panel-btn_close" @click.prevent="modalClose">Cancel</button>
              <button class="btn panel-btn_save">Update</button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </modal>
</template>

<script>
import FormInput from '../FormInput.vue';
import { mapMutations } from 'vuex';

export default {
  components: {
    FormInput,
  },

  props: {
    user: { type: Object, required: true },
  },
  data() {
    return {
      showCurrentPassword: false,
      showPassword: false,
      owner: this.user,
      errors: {},
      form: {
        name: '',
        username: '',
        email: '',
        company: '',
        mobile: '',
        position: '',
        address: '',
        current_password: '',
        password: '',
        bio: '',
      },
      originalData: {},
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
        // return plain Unicode characters (safe to render via interpolation)
        return show ? '👁' : '🕶';
      };
    },
  },
  mounted() {
    this.form.name = this.user.name;
    this.form.username = this.user.username;
    this.form.email = this.user.email;
    this.form.company = this.user.info.company;
    this.form.mobile = this.user.info.mobile;
    this.form.position = this.user.info.position;
    this.form.address = this.user.info.address;
    this.form.bio = this.user.info.bio;

    // Prefer structuredClone (native, faster). Fallback to JSON clone for older browsers.
    this.originalData =
      typeof structuredClone === 'function' ? structuredClone(this.form) : JSON.parse(JSON.stringify(this.form));
  },
  methods: {
    ...mapMutations('profile', ['updateUser']),
    modalClose() {
      this.$modal.hide('edit-profile');
      this.resetForm();
    },

    toggleShowCurrentPassword() {
      this.showCurrentPassword = !this.showCurrentPassword;
    },

    toggleShowPassword() {
      this.showPassword = !this.showPassword;
    },

    updateProfile() {
      if (_.isEqual(this.form, this.originalData)) {
        this.$vToastify.info('Form has not changed');
        return;
      }

      axios
        .patch(`/users/${this.user.id}`, this.form)
        .then((response) => {
          this.$vToastify.success('Profile Updated Successfully');
          this.updateUser(response.data.user);
          this.modalClose();
        })
        .catch((error) => {
          this.errors = error.response.data.errors;
        });
    },
    resetForm() {
      this.form = {
        name: this.user.name,
        username: this.user.username,
        email: this.user.email,
        company: this.user.info.company,
        mobile: this.user.info.mobile,
        position: this.user.info.position,
        address: this.user.info.address,
        current_password: '',
        password: '',
        bio: this.user.info.bio,
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
}
</style>
