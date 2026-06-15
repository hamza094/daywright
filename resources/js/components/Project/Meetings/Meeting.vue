<template>
  <div>
    <div id="meetingSDKElement"></div>
    <section class="project-info meeting-panel" aria-labelledby="meetings-title">
      <header class="meeting-panel_header">
        <h2 id="meetings-title" class="meeting-panel_title">Meetings</h2>
        <div>
          <button v-if="notAuthorize" type="button" class="btn btn-sm btn-secondary" @click.prevent="authorize">
            Authorize With Zoom
          </button>
          <button v-else type="button" class="btn btn-sm btn-primary" @click.prevent="openMeetingModal()">
            Create Meeting
          </button>
        </div>
      </header>
      <hr />

      <nav class="meeting-panel_tabs" aria-label="Meetings filter">
        <button
          type="button"
          class="btn btn-link btn-sm meeting_button"
          :class="{ active: !showPrevious }"
          :aria-pressed="(!showPrevious).toString()"
          @click="showCurrentMeetings">
          Current Meetings
        </button>
        <button
          type="button"
          class="btn btn-link btn-sm meeting_button"
          :class="{ active: showPrevious }"
          :aria-pressed="showPrevious.toString()"
          @click="showPreviousMeetings">
          Previous Meetings
        </button>
      </nav>

      <div class="meeting-show">
        <div v-if="message" class="alert alert-info mb-2">
          {{ message }}
        </div>

        <ul class="list-unstyled mb-0">
          <li v-for="meeting in filteredMeetings.data" :key="meeting.id">
            <article
              class="card mt-3 card-hover"
              :class="{ 'meeting-ended': meeting.status.toLowerCase() === 'ended' }"
              @click.prevent="getMeeting(meeting.id)">
              <div :class="['ribbon', ribbonColor(meeting.status)]">{{ meeting.status }}</div>
              <div
                v-if="meeting.sync_status && meeting.sync_status !== 'active'"
                :class="['sync-status-badge', getSyncStatusBadge(meeting.sync_status).color]">
                {{ getSyncStatusBadge(meeting.sync_status).label }}
              </div>
              <div class="card-stamp">
                <div class="card-stamp-icon bg-yellow">
                  <!-- Download SVG icon from http://tabler-icons.io/i/bell -->
                  <svg
                    xmlns="http://www.w3.org/2000/svg"
                    class="icon"
                    width="24"
                    height="24"
                    viewBox="0 0 24 24"
                    stroke-width="2"
                    stroke="currentColor"
                    fill="none"
                    stroke-linecap="round"
                    stroke-linejoin="round">
                    <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
                    <path
                      d="M10 5a2 2 0 1 1 4 0a7 7 0 0 1 4 6v3a4 4 0 0 0 2 3h-16a4 4 0 0 0 2 -3v-3a7 7 0 0 1 4 -6"></path>
                    <path d="M9 17v1a3 3 0 0 0 6 0v-1"></path>
                  </svg>
                </div>
              </div>
              <div v-if="meeting.status.toLowerCase() === 'started'" class="glowing-dot"></div>
              <div class="card-body">
                <h3 class="card-title">{{ meeting.topic }}</h3>
                <p class="text-secondary">{{ meeting.agenda }}</p>
                <p><b>Start Time:</b> {{ meeting.start_time | datetime }}</p>
                <p><b>Timezone:</b> {{ meeting.timezone }}</p>
                <p><b>Created At:</b> {{ meeting.created_at | datetime }}</p>
              </div>
              <footer class="card-footer">
                <button
                  v-if="shouldShowStartButton(meeting, auth, notAuthorize)"
                  class="btn btn-sm btn-primary"
                  @click.prevent="initializeMeeting('start', meeting)">
                  Start Meeting
                </button>
                <button
                  v-else-if="shouldShowJoinButton(meeting, auth, members)"
                  class="btn btn-sm btn-warning text-white"
                  @click.prevent="initializeMeeting('join', meeting)">
                  Join Meeting
                </button>
              </footer>
            </article>
          </li>
        </ul>
      </div>
    </section>
    <pagination :data="filteredMeetings" @pagination-change-page="getResults"></pagination>
    <MeetingModal :project-slug="projectSlug"></MeetingModal>
    <ViewModal :project-slug="projectSlug" :members="members" :not-authorize="notAuthorize"></ViewModal>
  </div>
</template>

<script>
import MeetingModal from './MeetingModal.vue';
import ViewModal from './ViewModal.vue';
import { mapState, mapActions } from 'vuex';
import { fetchTokens, setupAndJoinMeeting } from '../../../utils/zoomUtils';
import {
  shouldShowStartButton,
  shouldShowJoinButton,
  getSyncStatusBadge,
  canViewMeeting,
} from '../../../utils/meetingUtils';
import { getObjectData } from '../../../utils/apiResponse.js';

export default {
  components: {
    MeetingModal,
    ViewModal,
  },
  // Props for project and meeting data
  props: {
    projectSlug: { type: String, required: true },
    projectMeetings: { type: Object, default: () => ({}) },
    notAuthorize: { type: Boolean, default: false },
    members: { type: Array, default: () => [] },
    projectOwner: { type: Object, default: null },
  },
  data() {
    return {
      showPrevious: false,
      client: null,
      auth: this.$store.state.currentUser.user,
      loadingId: null,
      shouldCleanUpZoom: false,
      activeMeetingId: null,
    };
  },
  computed: {
    ...mapState('meeting', ['meetings', 'message']),
    // Filter meetings based on user role and sync_status
    filteredMeetings() {
      if (!this.meetings.data || !Array.isArray(this.meetings.data)) {
        return { ...this.meetings, data: [] };
      }

      const isOwner = this.auth && this.projectOwner && this.auth.id === this.projectOwner.id;

      const filteredData = this.meetings.data.filter((meeting) => canViewMeeting(meeting, isOwner));

      return { ...this.meetings, data: filteredData };
    },
    // Listen for meeting status updates via Echo
    meetingStatusListener() {
      if (this.activeMeetingId) {
        const id = this.activeMeetingId;
        Echo.private(`meetingStatus.${id}`).listen('MeetingStatusUpdate', (e) => {
          this.updateMeetingStatus({ id: e.id, status: e.status });
        });
        return () => {
          Echo.leave(`meetingStatus.${id}`);
        };
      }
      return null;
    },
  },
  watch: {
    // Clean up Echo listener when activeMeetingId changes
    meetingStatusListener(newListener, oldListener) {
      if (oldListener) oldListener();
    },
  },
  created() {
    this.showCurrentMeetings();
    this.$bus.$on('initialize-meeting', this.initializeMeeting);
  },

  beforeDestroy() {
    if (this.meetingStatusListener) {
      this.meetingStatusListener();
    }
  },

  mounted() {
    this.$bus.on('get-results', () => {
      this.showCurrentMeetings();
    });
  },

  destroyed() {
    this.$bus.$off('get-results');
    this.$bus.$off('initialize-meeting', this.initializeMeeting);
  },

  methods: {
    ...mapActions({
      fetchMeetings: 'meeting/fetchMeetings',
      updateMeetingStatus: 'meeting/updateMeetingStatus',
    }),

    shouldShowStartButton(meeting, auth, notAuthorize) {
      return shouldShowStartButton(meeting, auth, notAuthorize);
    },

    shouldShowJoinButton(meeting, auth, members) {
      return shouldShowJoinButton(meeting, auth, members);
    },

    getSyncStatusBadge(syncStatus) {
      return getSyncStatusBadge(syncStatus);
    },

    // Open the meeting details modal
    getMeeting(meetingId) {
      this.$bus.$emit('view-meeting-modal', meetingId);
    },

    // Fetch meetings for the current or previous tab
    getResults(page) {
      const slug = this.$route.params.slug;
      this.fetchMeetings({ slug, page, isPrevious: this.showPrevious });
    },

    showCurrentMeetings() {
      this.showPrevious = false;
      this.getResults();
    },

    showPreviousMeetings() {
      this.showPrevious = true;
      this.getResults();
    },

    // Open the meeting creation modal
    openMeetingModal() {
      this.$bus.emit('open-meeting-modal');
    },

    // Authorize the user with Zoom
    authorize() {
      axios
        .get(`/oauth/zoom/redirect`)
        .then((response) => {
          const payload = getObjectData(response);

          globalThis.location.href = payload.redirect_url;
        })
        .catch((error) => {
          this.handleErrorResponse(error);
        });
    },

    // Start or join a meeting
    async initializeMeeting(action, meeting) {
      this.loadingId = this.$vToastify.loader('Initializing meeting. Please hold on...');
      if (action === 'start') {
        this.activeMeetingId = meeting.id;
      }
      try {
        const tokenResponse = await fetchTokens(this.projectSlug, meeting.id, action, this.$vToastify);

        const jwt_token = tokenResponse.jwt_token;
        const zak_token = tokenResponse.zak_token;

        await setupAndJoinMeeting(action, meeting, jwt_token, zak_token, this.auth);

        this.$vToastify.success('Meeting initiated successfully!');
      } catch (error) {
        this.handleErrorResponse(error);
      } finally {
        this.$vToastify.stopLoader(this.loadingId);
        this.loadingId = null;
      }
    },

    // Get the color for the meeting status ribbon
    ribbonColor(status) {
      switch (status.toLowerCase()) {
        case 'waiting':
          return 'bg-yellow';
        case 'started':
          return 'bg-green';
        default:
          return 'bg-red';
      }
    },
  },
};
</script>

<style scoped>
.sync-status-badge {
  position: absolute;
  top: 10px;
  right: 10px;
  padding: 2px 8px;
  border-radius: 4px;
  font-size: 11px;
  font-weight: 600;
  color: white;
  text-transform: uppercase;
  z-index: 1;
}

.meeting-ended {
  opacity: 0.6;
  pointer-events: none;
}

.meeting-ended .card-body {
  color: #6c757d;
}
</style>
