<template>
  <div class="float-right">
    <FeatureDropdown :feature-pop.sync="featurePop">
      <ul>
        <li class="feature-dropdown_item-content" @click="abandon()"><i class="fa-solid fa-eye-slash"></i> Abandon</li>

        <li class="feature-dropdown_item-content" @click="$modal.show('project-message')">
          <i class="fa-regular fa-envelope"></i>Send Mail or Sms
        </li>

        <li class="feature-dropdown_item-content" @click="exportProject()">
          <i class="fa-solid fa-upload"></i> Export
        </li>

        <li class="feature-dropdown_item-content" @click="deleteProject"><i class="fa-solid fa-ban"></i> Delete</li>
      </ul>
    </FeatureDropdown>

    <ProjectMessage :slug="slug" :members="members"></ProjectMessage>
  </div>
</template>

<script>
import fileDownload from 'js-file-download';
import ProjectMessage from './Message.vue';
import FeatureDropdown from '../../FeatureDropdown.vue';

export default {
  components: { ProjectMessage, FeatureDropdown },

  props: {
    slug: { type: String, required: true },
    members: { type: Array, default: () => [] },
    name: { type: String, default: '' },
  },
  data() {
    return {
      projectmembers: this.members,
      featurePop: false,
      featureClickOutsideHandler: null,
      errors: {},
    };
  },
  watch: {
    featurePop(open) {
      if (open) this.addFeatureClickOutsideListener();
      else this.removeFeatureClickOutsideListener();
    },
  },
  beforeDestroy() {
    this.removeFeatureClickOutsideListener();
  },
  methods: {
    abandon() {
      this.performAction('Yes, abandon it!', axios.delete('/projects/' + this.slug));
    },

    deleteProject() {
      this.performAction('Yes, delete it!', axios.get('/projects/' + this.slug + '/delete'));
    },
    exportProject() {
      axios
        .get('/projects/' + this.slug + '/export', {
          responseType: 'blob',
          headers: { Accept: 'multipart/form-data' },
        })
        .then((response) => {
          fileDownload(response.data, 'Project ' + this.slug + '.xls');
        })
        .catch((error) => {
          const msg = error?.response?.data?.message || error?.message || 'Export failed';
          // Show a user-friendly message
          if (this && this.$vToastify) {
            this.$vToastify.error(msg);
          }
          // Keep a dev-only console error for debugging
          const isDev = typeof process !== 'undefined' && process.env && process.env.NODE_ENV !== 'production';
          if (isDev) {
            console.error(error);
          }
        });
    },
    async addFeatureClickOutsideListener() {
      if (this.featureClickOutsideHandler) return;
      await this.$nextTick();
      this.featureClickOutsideHandler = (event) => {
        const el = this.$el.querySelector('.feature-dropdown');
        if (el && !el.contains(event.target)) this.featurePop = false;
      };
      document.addEventListener('click', this.featureClickOutsideHandler);
    },
    removeFeatureClickOutsideListener() {
      if (!this.featureClickOutsideHandler) return;
      document.removeEventListener('click', this.featureClickOutsideHandler);
      this.featureClickOutsideHandler = null;
    },
  },
};
</script>
