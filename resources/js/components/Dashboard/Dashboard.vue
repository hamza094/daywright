<template>
  <div>
    <div class="page-top">Welcome To Your Dashboard</div>
    <div class="alert alert-success" role="alert" v-if="!user.verified">
      To access all features, please verify your account.
      <span class="btn btn-sm btn-primary" @click="resendMail()">Resend verification mail</span>
    </div>
    <div class="dashboard-project m-4">
      <p class="dashboard-heading float-left"><b>Recent Active Projects</b></p>
      <span class="float-right">
        <router-link :to="{ name: 'Projects' }" class="btn btn-sm btn-primary">
          <i class="fa-solid fa-list"></i> View All Projects
        </router-link>
      </span>
    </div>
    <br /><br />
    <div class="dashboard">
      <div class="row">
        <div v-if="message" class="m-3">
          <h5>
            <b>{{ message }}</b>
          </h5>
        </div>

        <div class="col-md-4" v-for="project in projects" :key="project.id">
          <router-link :to="{ name: 'ProjectPage', params: { slug: project.slug } }" class="dashboard-link">
            <div class="dashboard-projects mt-5 active-projects">
              <div class="project-status">
                <b>Active</b>
              </div>
              <div class="project-title">{{ project.name }}</div>
              <div class="project-info">
                <div class="info-item">
                  <span class="info-label">Stage:</span>
                  <span class="info-value">{{ project.stage.name }}</span>
                </div>
                <div class="info-item">
                  <span class="info-label">Health Status:</span>
                  <span class="info-value">{{ project.health_status }}</span>
                </div>
                <div class="info-item">
                  <span class="info-label">Created:</span>
                  <span class="info-value">{{ project.created_at }}</span>
                </div>
              </div>
            </div>
          </router-link>
        </div>
      </div>
    </div>
    <div class="dashboard-project_info m-5">
      <p class="float-left"><b>Your Projects Info</b></p>
      <br />
      <div class="row">
        <div class="col-md-6">
          <ProjectChart> </ProjectChart>
        </div>
        <div class="col-md-6">
          <ActivityCalendar />
        </div>
      </div>
    </div>

    <div class="dashboard-project_info m-5">
      <p class="float-left"><b>Your Tasks Info</b></p>
      <br />
      <div class="row">
        <div class="col-md-6">
          <TasksData> </TasksData>
        </div>
        <div class="col-md-6"></div>
      </div>
    </div>
  </div>
</template>
<script>
import ProjectChart from './ProjectChart.vue';
import TasksData from './TasksData.vue';
import ActivityCalendar from './ActivityCalendar.vue';

export default {
  computed: {
    user: {
      get() {
        return this.$store.state.currentUser.user;
      },
    },
  },
  components: {
    ProjectChart,
    TasksData,
    ActivityCalendar,
  },

  data() {
    return {
      projects: [],
      projectsCount: 0,
      message: '',
    };
  },
  mounted() {
    this.loadDashboardProjects();
  },
  methods: {
     resendMail() {
      axios
        .post('/email/resend/' + this.user.uuid, {})
        .then(() => {
          this.$vToastify.success('Verification link sent successfully');
        })
        .catch((error) => {
          const msg = error?.response?.data?.message || error?.message || 'Error! Please try again';
          this.$vToastify.warning(msg);
        });
    },
    loadDashboardProjects() {
      axios.get('/user/dashboard-projects').then(({ data }) => this.getData(data));
    },

    getData(data) {
      this.projects = data.projects;
      this.projectsCount = data.projectsCount;
      this.message = data.message;
    },
  },
};
</script>
