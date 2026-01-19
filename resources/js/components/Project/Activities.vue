<template>
  <div>
    <div class="container-fluid">
      <!-- Breadcrumb Navigation -->
      <nav aria-label="breadcrumb" class="pl-1">
        <ol class="breadcrumb">
          <li class="breadcrumb-item">Projects</li>
          <li class="breadcrumb-item">
            <router-link :to="{ name: 'ProjectPage', params: { slug: $route.params.slug } }" class="dashboard-link">
              {{ $route.params.name }}
            </router-link>
          </li>
          <li class="breadcrumb-item active text-primary" aria-current="page">
            Activities
            <span class="text-muted">/</span>
            <span class="ml-1">{{ currentTitle }}</span>
          </li>
        </ol>
      </nav>

      <div class="row">
        <!-- Main Section -->
        <main class="col-md-8 page pd-r order-2 order-md-1">
          <!-- Activities Section -->
          <section class="container">
            <div class="activity mb-4">
              <div class="mt-3" v-if="!activities.data">
                <h3>No related activities found</h3>
              </div>

              <ul v-else>
                <li v-for="activity in activities.data" :key="activity.id">
                  <!-- Icon + Description -->
                  <span class="activity-icon" :class="activityColor(activity.description)">
                    <i :class="activityIcon(activity.description)"></i>
                  </span>

                  {{ activity.description }}

                  <!-- Display affected users if available -->
                  <template v-if="activity.affected_users?.length">
                    <span v-for="(user, userIndex) in activity.affected_users" :key="user.id">
                      <router-link
                        :to="{ name: 'Profile', params: { uuid: user.uuid } }"
                        class="text-primary font-weight-bold">
                        {{ user.name }}
                      </router-link>

                      <span v-if="userIndex !== activity.affected_users.length - 1">,</span>
                    </span>
                  </template>

                  <!-- Activity User Info -->
                  <p class="activity-info mt-1">
                    <span>{{ activity.user.name }}</span>
                    <span class="activity-info_dot mx-2"></span>
                    <span>{{ activity.time }}</span>
                  </p>
                </li>
              </ul>
            </div>
          </section>

          <!-- Pagination (mobile only) placed after Activities section -->
          <div class="w-100 p-4 d-block d-md-none">
            <pagination :data="activities" @pagination-change-page="getResults"></pagination>
          </div>
        </main>
        <!-- Sidebar Section -->
        <aside class="col-md-4 order-1 order-md-2">
          <section class="card">
            <header class="card-header">
              <p>Search Related Activities:</p>
            </header>
            <div class="card-body activity-search">
              <!-- Filter List -->
              <ul>
                <li v-for="activity in activityTypes" :key="activity.status">
                  <a
                    href="#"
                    :class="['activity-icon_' + activity.color, { 'activity-font': status === activity.status }]"
                    @click.prevent="fetchActivities(activity)">
                    <i :class="['fa-solid', activity.icon, 'mr-2', 'activity-icon_' + activity.color]"></i>
                    {{ activity.label }}
                  </a>
                </li>
              </ul>
            </div>
          </section>

          <!-- Pagination (desktop only) -->
          <div class="mt-4 d-none d-md-block">
            <pagination :data="activities" @pagination-change-page="getResults"></pagination>
          </div>
        </aside>
      </div>
    </div>
  </div>
</template>
<script>
import activityMixins from '../../mixins/activityMixins';

export default {
  mixins: [activityMixins],
  data() {
    return {
      activities: {},
      status: 'all',
      auth: this.$store.state.currentUser.user,
      currentTitle: 'All Project Activities',
      currentQuery: '',
      activityTypes: activityMixins.data().activityTypes,
    };
  },

  mounted() {
    this.fetchActivities(this.activityTypes[0]);
  },
  methods: {
    // Fetch activities design from mixin
    activityIcon(description) {
      return this.getIcon(description);
    },

    activityColor(description) {
      return this.getColor(description);
    },
    async getData(suffix) {
      await axios
        .get(`/projects/${this.$route.params.slug}/activities${suffix}`)
        .then((response) => {
          this.activities = response.data;
        })
        .catch((error) => {
          this.handleErrorResponse(error);
        });
    },

    async fetchActivities(activity) {
      this.status = activity.status;
      this.currentTitle = activity.label;
      this.currentQuery = activity.query || '';
      this.getResults(1);
    },

    getResults(page = 1) {
      let query = this.currentQuery ? `${this.currentQuery}&page=${page}` : `?page=${page}`;
      this.getData(query);
    },
  },
};
</script>
