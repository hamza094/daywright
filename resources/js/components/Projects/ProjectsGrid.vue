<template>
  <div class="row ml-1">
    <div class="col-md-6 col-lg-4 mb-4" v-for="project in projects" :key="project.id">
      <router-link :to="{ name: 'ProjectPage', params: { slug: project.slug } }" class="dashboard-link">
        <div :class="`dashboard-projects mt-5 ${currentTab}-projects`">
          <div class="project-status">
            <b>{{ getStatusLabel() }}</b>
          </div>

          <div class="project-title">{{ project.name }}</div>

          <div class="project-info">
            <div class="info-item" v-if="project.stage">
              <span class="info-label">Stage:</span>
              <span class="info-value">{{ project.stage.name }}</span>
            </div>

            <div class="info-item" v-if="currentTab === 'invited' && project.user">
              <span class="info-label">Owner:</span>
              <span class="info-value">{{ project.user.name || 'Unknown' }}</span>
            </div>

            <div class="info-item" v-if="currentTab === 'trashed' && project.deleted_at">
              <span class="info-label">Deleted:</span>
              <span class="info-value">{{ project.deleted_at }}</span>
            </div>

            <div class="info-item" v-if="project.status">
              <span class="info-label">Status:</span>
              <span class="info-value">{{ project.status }}</span>
            </div>

            <div class="info-item">
              <span class="info-label">Created:</span>
              <span class="info-value">
                {{ project.created_at | date }}
              </span>
            </div>
          </div>
        </div>
      </router-link>
    </div>
  </div>
</template>

<script>
export default {
  name: 'ProjectsGrid',

  props: {
    projects: {
      type: Array,
      required: true,
    },
    currentTab: {
      type: String,
      required: true,
    },
    currentTabConfig: {
      type: Object,
      required: true,
    },
  },

  methods: {
    /**
     * Get the appropriate status label based on current tab
     * @returns {string} Status label
     */
    getStatusLabel() {
      return this.currentTabConfig.label.split(' ')[0];
    },
  },
};
</script>
