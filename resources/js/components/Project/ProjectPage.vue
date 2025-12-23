<template>
  <div v-if="show" class="project-page">
    <div class="container-fluid">
      <div class="row">
        <div class="col-12 col-lg-8 page pd-r">
          <div class="page-top">
            <div class="d-flex align-items-center justify-content-between flex-wrap">
              <span>
                <span class="page-top_heading">Projects </span>
                <span class="page-top_arrow"> > </span>
                <span> {{ project.name }}</span>
              </span>

              <div class="d-flex align-items-center">
                <button
                  type="button"
                  class="btn btn-link btn-sm"
                  aria-label="Open project chat"
                  @click.prevent="openProjectChat">
                  <i class="fa-solid fa-comments"></i>
                  <span class="d-none d-sm-inline"> Chat</span>
                </button>

                <project-features :slug="project.slug" :members="project.members" :name="project.name">
                </project-features>
              </div>
            </div>
          </div>
          <div class="page-content">
            <div class="row">
              <div class="col-md-2">
                <Status
                  :project="{
                    id: project.id,
                    slug: project.slug,
                    name: project.name,
                    start: project.created_at,
                    stage: project.stage,
                    completed: project.completed,
                    status: project.health_status,
                    score: project.health_score,
                  }">
                </Status>
              </div>
              <div class="col-md-10">
                <div class="content">
                  <!-- Project name section -->
                  <p class="content-name">
                    <span v-if="nameEdit">
                      <input class="form-control sm-6" type="text" v-model="projectname" />
                    </span>

                    <span v-else>{{ project.name }}</span>

                    <span v-if="nameEdit">
                      <button type="button" class="btn btn-link btn-sm" @click="updateName()">Update</button>
                      <button type="button" class="btn btn-link btn-sm" @click="cancelUpdate()">Cancel</button>
                    </span>

                    <span v-else>
                      <button
                        v-if="permission.access"
                        type="button"
                        class="btn btn-link btn-sm"
                        @click="nameEdit = true">
                        Edit
                      </button>
                    </span>
                  </p>

                  <p class="content-info">
                    Created On
                    <span class="content-dot"></span>
                    {{ project.created_at }}
                  </p>
                  <p class="content-info">
                    Created By<span class="content-dot"></span>
                    <router-link :to="'/user/' + user.id + '/profile'" class="btn btn-link">{{
                      user.name
                    }}</router-link>
                  </p>
                </div>
                <div v-if="project.deleted_at">
                  <div class="alert alert-danger" role="alert">
                    This project is abandoned to access project features active this project, or it will be deleted
                    automatically after {{ project.days_limit }} days from the abandoned date.
                    <p>Abandoned on: <b v-text="project.deleted_at"></b></p>
                    <a class="btn btn-info" @click.prevent="restore()">Restore Project</a>
                  </div>
                </div>
              </div>
            </div>
            <hr />
            <p class="pro-info">Project Detail</p>
            <div class="row">
              <div class="col-md-6">
                <!-- About Section -->
                <p class="crm-info">
                  <b>About</b>:
                  <span v-if="aboutEdit">
                    <textarea rows="4" cols="30" v-model="projectabout" v-text="project.about" class="form-control">
                    </textarea>
                  </span>

                  <span v-else> {{ project.about }} </span>

                  <span v-if="aboutEdit">
                    <button type="button" class="btn btn-link btn-sm" @click="updateAbout()">Update</button>
                    <button type="button" class="btn btn-link btn-sm" @click="aboutCancel()">Cancel</button>
                  </span>

                  <span v-else>
                    <button v-if="permission.access" type="button" class="btn btn-link btn-sm" @click="editAbout()">
                      Edit
                    </button>
                  </span>
                </p>
                <p v-if="!project.postponed_reason" class="crm-info">
                  <b>Postponed reason</b>:
                  <span>
                    The project is currently active. Please try to avoid the project being postpone without any reason
                  </span>
                </p>
                <p v-else class="crm-info">
                  <b>Postponed reason</b>: <span> {{ project.postponed_reason }} </span>
                </p>
              </div>
              <div class="col-md-6">
                <p class="crm-info"><b>Tasks</b>: <span> Info </span></p>
                <p class="crm-info"><b>Appointments</b>: <span> Info </span></p>
                <p class="crm-info"><b>Other</b>: <span> Info </span></p>
              </div>
            </div>
            <br />
            <Stage
              :slug="project.slug"
              :postponed-reason="project.postponed_reason"
              :stage-updated="project.stage_updated_at"
              :get-stage="getStage"
              :access="permission.access">
            </Stage>
            <br />
            <hr />
            <h3>RECENT ACTIVITIES</h3>
            <div class="row">
              <RecentActivities :activities="project.activities" :slug="project.slug" :name="project.name">
              </RecentActivities>
              <div class="col-md-5">
                <div class="project-info">
                  <div class="project-info_socre">
                    <p class="project-info_score-heading">Status</p>
                    <p class="project-info_score-point" :class="'project-info_score-point_' + project.health_status">
                      <b>{{ Math.round(project.health_score) }}%</b>
                    </p>
                  </div>
                  <div class="project-info_rec">
                    <span>Staus</span>
                    <p>
                      <span :class="['badge', healthBadgeClass]">
                        {{ project.health_status?.toUpperCase() }}
                      </span>
                    </p>
                  </div>
                  <div class="project-info_rec">
                    <span>Score Updated</span>
                    <p v-text="project.health_score_calculated_at"></p>
                  </div>
                  <div class="project-info_rec">
                    <span>Last modified</span>
                    <p v-text="project.updated_at"></p>
                  </div>
                </div>
                <br />
                <hr />
                <Meeting
                  :project-slug="project.slug"
                  :project-meetings="project.meetings"
                  :not-authorize="project.ownerNotAuthorized"
                  :members="project.members"></Meeting>
              </div>
            </div>
          </div>
        </div>

        <div class="col-12 col-lg-4 side_panel">
          <div class="d-lg-none">
            <ul class="nav nav-pills mb-3">
              <li class="nav-item">
                <button
                  type="button"
                  class="nav-link"
                  :class="{ active: activePanelTab === 'none' }"
                  @click.prevent="activePanelTab = 'none'">
                  Panel
                </button>
              </li>
              <li class="nav-item">
                <button
                  type="button"
                  class="nav-link"
                  :class="{ active: activePanelTab === 'tasks' }"
                  @click.prevent="activePanelTab = 'tasks'">
                  Tasks
                </button>
              </li>
              <li class="nav-item">
                <button
                  type="button"
                  class="nav-link"
                  :class="{ active: activePanelTab === 'people' }"
                  @click.prevent="activePanelTab = 'people'">
                  Notes & People
                </button>
              </li>
            </ul>

            <div v-if="activePanelTab === 'none'" class="text-muted small mb-3">
              Select a section to view tasks, notes, and members.
            </div>

            <Task v-if="activePanelTab === 'tasks'" :slug="project.slug" :access="permission.access"></Task>

            <PanelFeatues
              v-if="activePanelTab === 'people'"
              :slug="project.slug"
              :notes="project.notes"
              :members="project.members"
              :owner="user"
              :access="permission.access"
              :owner-login="permission.owner"></PanelFeatues>
          </div>

          <div class="d-none d-lg-block">
            Project Side Panel
            <br />
            <Task :slug="project.slug" :access="permission.access"></Task>
            <hr />
            <PanelFeatues
              :slug="project.slug"
              :notes="project.notes"
              :members="project.members"
              :owner="user"
              :access="permission.access"
              :owner-login="permission.owner"></PanelFeatues>
          </div>
        </div>
      </div>
    </div>
  </div>
  <!--<div v-else class="text-center mt-5">
    <h3>Thank you for your patience. The page is loading, and we're almost there!</h3>
    <div class="d-flex mt-3 justify-content-center align-items-center">
      <ring-loader :color=this.color :size="100" />
    </div>
  </div>-->
</template>
<script>
import Status from './Status.vue';
import Meeting from './Meetings/Meeting.vue';
import Stage from './Stage.vue';
import Task from './Panel/Task.vue';
import PanelFeatues from './Panel/Features.vue';
import RecentActivities from './RecentActivities.vue';
import { permission } from '../../auth';
import { mapState, mapMutations, mapActions } from 'vuex';

export default {
  components: {
    Status,
    Stage,
    Task,
    PanelFeatues,
    RecentActivities,
    Meeting,
  },

  data() {
    return {
      color: '#301934',
      nameEdit: false,
      aboutEdit: false,
      projectname: '',
      projectabout: '',
      projectId: '',
      auth: this.$store.state.currentUser.user,
      conversations: [],
      activePanelTab: 'none',
      path: '',
      members: '',
      show: false,
      errors: [],
    };
  },

  computed: {
    ...mapState('project', ['project', 'user', 'getStage', 'tasks']),

    permission() {
      const { access, owner } = permission(this.auth.uuid, this.project.members, this.user.uuid, this.auth.isAdmin);

      return { access, owner };
    },

    status() {
      // Use backend-provided derived health status (hot/warm/cold) to avoid duplicating logic here
      return this.project.health_status || 'cold';
    },
    healthBadgeClass() {
      const map = {
        cold: 'badge-info',
        warm: 'badge-warm',
        hot: 'badge-success',
      };
      return map[this.project.health_status] || 'badge-secondary';
    },
  },

  created() {
    const slug = this.$route.params.slug;
    this.loadProject(slug)
      .then(() => {
        this.show = true;
        this.projectname = this.project.name;
        this.projectabout = this.project.about;
        this.projectId = this.project.id;
        this.members = this.project.members;
        this.meetings = this.project.meetings;
        this.listenForActivity();
        this.listenForProjectHealth();
        this.archiveTask();
      })
      .catch((error) => {
        this.handleErrorResponse(error);
      });
  },

  mounted() {
    this.path = this.getProjectSlug();
  },

  beforeDestroy() {
    if (this.projectId) {
      Echo.leave(`project.${this.projectId}.health`);
    }
  },

  methods: {
    ...mapActions('project', ['loadProject']),
    ...mapMutations('project', ['aboutUpdate']),

    openProjectChat() {
      if (!this.project || !this.project.slug) {
        return;
      }

      const panelHandle = this.$showPanel({
        component: 'project-chat',
        openOn: 'right',
        width: 440,
        disableBgClick: true,
        keepAlive: true,
        props: {
          slug: this.project.slug,
          members: this.project.members,
          owner: this.user,
          auth: this.auth,
        },
      });
      panelHandle.promise.then(() => {});
    },

    // Listen for backend health score updates in real-time and update store
    listenForProjectHealth() {
      if (!this.projectId) {
        return;
      }

      Echo.private(`project.${this.projectId}.health`).listen('ProjectHealthUpdated', (e) => {
        // Prefer concise alias `score`, fallback to `health_score`
        const newScore = e.health_score ?? null;
        const status = e.health_status ?? null;
        const calculatedAt = e.calculated_at ?? null;
        this.$store.commit('project/updateHealth', {
          score: newScore,
          status: status,
          calculated_at: calculatedAt,
        });
      });
    },

    updateName() {
      this.$Progress.start();
      axios
        .patch(`/projects/${this.project.slug}`, {
          name: this.projectname,
        })
        .then((response) => {
          const { name, slug } = response.data.project;
          this.$Progress.finish();
          this.updateNameState(name, slug, response.data.message);
          this.updateUrl(slug);
        })
        .catch((error) => {
          this.$Progress.fail();
          this.nameEdit = false;
          this.projectname = this.project.name;
          this.showError(error);
        });
    },
    updateUrl(slug) {
      if (this.$route.params.slug !== slug) {
        this.$router.replace({
          name: 'ProjectPage',
          params: { ...this.$route.params, slug },
        });
      }
    },

    updateNameState(name, slug, msg) {
      this.$store.commit('project/nameUpdate', { name, slug });
      this.projectname = name;
      this.nameEdit = false;
      this.$vToastify.success(msg);
    },

    cancelUpdate() {
      this.nameEdit = false;
      this.projectname = this.project.name;
    },

    updateAbout() {
      this.$Progress.start();
      axios
        .patch(`/projects/${this.project.slug}`, {
          about: this.projectabout,
        })
        .then((response) => {
          this.$Progress.finish();
          const data = response.data;
          this.aboutUpdate(data.project.about);
          this.projectabout = data.project.about;
          this.aboutEdit = false;
          this.$vToastify.success(data.message);
        })
        .catch((error) => {
          this.$Progress.fail();
          this.aboutEdit = false;
          this.projectabout = this.project.about;
          this.showError(error);
        });
    },

    editAbout() {
      this.aboutEdit = true;
      this.projectabout = this.project.about;
    },

    aboutCancel() {
      this.aboutEdit = false;
      this.projectabout = this.project.about;
    },

    restore() {
      this.performAction('Yes, Make live again!', axios.get(`/projects/${this.project.slug}/restore`));
    },

    //show error messages
    showError(err) {
      const {
        data: { errors, error },
      } = err.response;
      if (errors) {
        Object.keys(errors).forEach((field) => {
          this.$vToastify.warning(errors[field][0]);
        });
      } else if (error) {
        this.$vToastify.warning(error);
      }
    },

    listenForActivity() {
      Echo.channel('activities.project.' + this.projectId).listen('ActivityLogged', (e) => {
        this.project.activities.unshift(e);
      });
    },

    archiveTask() {
      this.$bus.on('archiveTask', (taskId) => {
        if (this.project.activities.subject_id !== null) {
          this.project.activities = this.project.activities.filter((activity) => activity.subject_id !== taskId);
        }
      });
    },
  },
};
</script>
