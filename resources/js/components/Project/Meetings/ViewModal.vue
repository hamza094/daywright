<template>
  <div>
    <modal name="ViewMeeting" height="auto" :scrollable="true" width="40%" class="modal-design" :click-to-close="false">
      <div class="edit-border-top p-3">
        <div v-if="meeting && meeting.status === 'Started'" class="glowing-dot"></div>

        <div class="edit-border-bottom">
          <div class="panel-top_content">
            <span class="meeting_heading">{{ meeting.topic }}</span>

            <div v-if="isEditing" class="form-group row">
              <div class="col-md-9">
                <label for="edit-meeting-topic" class="sr-only">Meeting Topic</label>
                <input
                  id="edit-meeting-topic"
                  class="form-control"
                  v-model="form.topic"
                  placeholder="Edit meeting title"
                  aria-label="Meeting topic" />
                <span class="text-danger font-italic" v-if="errors.topic" v-text="errors.topic[0]"></span>
              </div>
            </div>

            <button
              v-if="!isEditing"
              type="button"
              class="panel-exit float-right"
              aria-label="Close"
              @click.prevent="meetingModalClose">
              x
            </button>
          </div>
        </div>
        <div v-if="meeting && Object.keys(meeting).length > 0" class="meeting">
          <ul class="meeting_list">
            <meeting-detail v-if="!isEditing" label="Meeting ID" :value="meeting.meeting_id" />

            <meeting-detail label="Agenda" :is-editing="isEditing">
              <template v-if="!isEditing">
                <span>{{ meeting.agenda }}</span>
              </template>

              <template v-else>
                <label for="edit-meeting-agenda" class="sr-only">Meeting Agenda</label>
                <textarea
                  id="edit-meeting-agenda"
                  class="form-control"
                  v-model="form.agenda"
                  aria-label="Meeting agenda"></textarea>
                <span class="text-danger font-italic" v-if="errors.agenda" v-text="errors.agenda[0]"></span>
              </template>
            </meeting-detail>

            <meeting-detail label="Start Time" :is-editing="isEditing">
              <template v-if="!isEditing">
                <span>{{ meeting.start_time | datetime }}</span>
              </template>

              <template v-else>
                <label for="edit-meeting-start-time" class="sr-only">Meeting Start Time</label>
                <datetime
                  id="edit-meeting-start-time"
                  type="datetime"
                  :value="meeting.start_time"
                  v-model="form.start_time"
                  value-zone="UTC"
                  :zone="displayTimezone"
                  format="YYYY-MM-DD HH:mm:ss"
                  aria-label="Meeting start time" />
                <span class="text-danger font-italic" v-if="errors.start_time" v-text="errors.start_time[0]"></span>
              </template>
            </meeting-detail>

            <meeting-detail label="Meeting Duration" :is-editing="isEditing">
              <template v-if="!isEditing">
                <span>{{ meeting.duration }} Minutes</span>
              </template>

              <template v-else>
                <label for="edit-meeting-duration" class="sr-only">Meeting Duration</label>
                <select
                  id="edit-meeting-duration"
                  class="form-control"
                  v-model="form.duration"
                  aria-label="Meeting duration">
                  <option value="" disabled selected>Select Meeting Duration</option>
                  <option value="15">15 minutes</option>
                  <option value="30">30 minutes</option>
                  <option value="45">45 minutes</option>
                </select>
                <span class="text-danger font-italic" v-if="errors.duration" v-text="errors.duration[0]"></span>
              </template>
            </meeting-detail>

            <meeting-detail v-if="!isEditing" label="Status" :value="meeting.status" />

            <meeting-detail v-if="!isEditing && meeting.sync_status" label="Sync Status">
              <span :class="['sync-status-badge', getSyncStatusBadge(meeting.sync_status).color]">
                {{ getSyncStatusBadge(meeting.sync_status).label }}
              </span>
            </meeting-detail>

            <meeting-detail v-if="!isEditing && meeting.owner" label="Created By" :value="meeting.owner.name" />

            <meeting-detail
              v-if="!isEditing"
              label="Created At"
              :value="$options.filters.datetime(meeting.created_at)" />

            <meeting-detail label="Timezone" :is-editing="isEditing">
              <template v-if="!isEditing">
                <span>{{ meeting.timezone }}</span>
              </template>

              <template v-else>
                <label for="edit-meeting-timezone" class="sr-only">Meeting Timezone</label>
                <input
                  id="edit-meeting-timezone"
                  class="form-control"
                  v-model="form.timezone"
                  aria-label="Meeting timezone" />
                <span class="text-danger font-italic" v-if="errors.timezone" v-text="errors.timezone[0]"></span>
              </template>
            </meeting-detail>

            <meeting-detail label="Password" :is-editing="isEditing">
              <template v-if="!isEditing">
                <span>{{ meeting.password }}</span>
              </template>

              <template v-else>
                <label for="edit-meeting-password" class="sr-only">Meeting Password</label>
                <input
                  id="edit-meeting-password"
                  type="password"
                  class="form-control"
                  v-model="form.password"
                  aria-label="Meeting password" />
                <span class="text-danger font-italic" v-if="errors.password" v-text="errors.password[0]"></span>
              </template>
            </meeting-detail>

            <meeting-detail
              v-if="!isEditing"
              label="Updated At"
              :value="$options.filters.datetime(meeting.updated_at)" />

            <meeting-detail label="Join Before Host" :is-editing="isEditing">
              <template v-if="!isEditing">
                <span>{{ meeting.join_before_host }}</span>
              </template>

              <template v-else>
                <div class="form-check form-check-inline">
                  <input
                    class="form-check-input"
                    type="radio"
                    id="joinBefore"
                    name="joinBeforeHost"
                    :value="true"
                    v-model="form.join_before_host" />
                  <label class="form-check-label" for="joinBefore">Yes</label>
                </div>

                <div class="form-check form-check-inline">
                  <input
                    class="form-check-input"
                    type="radio"
                    id="joinAfter"
                    name="joinBeforeHost"
                    :value="false"
                    v-model="form.join_before_host" />
                  <label class="form-check-label" for="joinAfter">No</label>
                </div>
                <br />
                <span
                  class="text-danger font-italic"
                  v-if="errors.join_before_host"
                  v-text="errors.join_before_host[0]"></span>
                <br />
              </template>
            </meeting-detail>

            <li v-if="!isEditing">
              <button
                v-if="canStartMeeting(meeting, auth, notAuthorize)"
                class="btn btn-secondary btn-sm"
                @click.prevent="emitInitializeMeeting('start', meeting)">
                Start Meeting As Owner
              </button>

              <button
                v-else-if="canJoinMeeting(meeting, auth, members)"
                class="btn btn-secondary btn-sm"
                @click.prevent="emitInitializeMeeting('join', meeting)">
                Join Meeting
              </button>
            </li>
          </ul>

          <div class="mt-3">
            <div v-if="!isEditing">
              <button
                class="btn btn-sm btn-danger float-right mb-3"
                @click.prevent="deleteMeeting(meeting.id)"
                :disabled="loader">
                {{ loader ? 'Deleting...' : 'Delete' }}
              </button>
              <button class="btn btn-sm btn-primary float-left mb-3" @click.prevent="meetingEdit()">Edit</button>
            </div>

            <div v-else>
              <button class="btn btn-sm btn-info float-right mb-3" @click.prevent="meetingEditClose()">Close</button>
              <button
                class="btn btn-sm btn-primary float-left mb-3"
                @click.prevent="updateMeeting(meeting.id)"
                :disabled="loading">
                {{ loading ? 'Saving...' : 'Save' }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </modal>
  </div>
</template>

<script>
import { mapMutations } from 'vuex';
import MeetingDetail from './MeetingDetail.vue';
import { createIdempotentRequest } from '../../../services/IdempotencyRequestService';
import { shouldShowStartButton, shouldShowJoinButton, getSyncStatusBadge } from '../../../utils/meetingUtils';
import { getDisplayTimezone, toUtcIsoString } from '../../../utils/dateTime';
import { getObjectData, getResponseMessage } from '../../../utils/apiResponse.js';

export default {
  name: 'ViewMeetingModal',
  components: {
    MeetingDetail,
  },
  props: {
    projectSlug: { type: String, required: true },
    members: { type: Array, default: () => [] },
    notAuthorize: { type: Boolean, default: false },
  },
  data() {
    return {
      meeting: {},
      isEditing: false,
      errors: {},
      loader: false,
      loading: false,
      loaderId: null,
      auth: this.$store.state.currentUser.user,
      form: {
        topic: '',
        agenda: '',
        start_time: '',
        duration: '',
        join_before_host: '',
        timezone: '',
        password: '',
      },
    };
  },

  computed: {
    displayTimezone() {
      return getDisplayTimezone();
    },
  },

  created() {
    this.updateMeetingRequest = createIdempotentRequest();
    this.$bus.$on('view-meeting-modal', this.getMeeting);
  },

  beforeDestroy() {
    this.updateMeetingRequest?.reset();
    this.$bus.$off('view-meeting-modal', this.getMeeting);
  },

  methods: {
    ...mapMutations('meeting', {
      updateMeetingInState: 'meetingUpdate',
      removeMeetingFromState: 'removeMeetingFromState',
    }),

    emitInitializeMeeting(action, meeting) {
      this.$bus.$emit('initialize-meeting', action, meeting);
      this.meetingModalClose();
    },

    canStartMeeting(meeting, auth, notAuthorize) {
      return shouldShowStartButton(meeting, auth, notAuthorize);
    },

    canJoinMeeting(meeting, auth, members) {
      return shouldShowJoinButton(meeting, auth, members);
    },

    getSyncStatusBadge(syncStatus) {
      return getSyncStatusBadge(syncStatus);
    },

    updateMeeting(id) {
      this.initializeUpdateMeeting();
      const filteredForm = this.filterForm();
      this.updateMeetingRequest
        .patch(`/projects/${this.projectSlug}/meetings/${id}`, filteredForm)
        .then((response) => {
          this.meeting = getObjectData(response);
          this.updateMeetingInState(this.meeting);
          this.$vToastify.success(
            this.meeting.topic
              ? `Meeting ${this.meeting.topic} updated successfully.`
              : 'Meeting updated successfully.',
          );
          this.meetingEditClose();
        })
        .catch((error) => {
          this.handleErrorResponse(error);
        })
        .finally(() => {
          this.setLoading('', 'stop');
          this.loading = false;
        });
    },

    deleteMeeting(meeting) {
      this.setLoading('Deleting meeting, please wait...', 'start');
      this.loader = true;
      axios
        .delete(`/projects/${this.projectSlug}/meetings/${meeting}`)
        .then((response) => {
          this.meetingModalClose();
          this.removeMeetingFromState(meeting);
          this.$vToastify.success(getResponseMessage(response) || 'Meeting deleted successfully.');
        })
        .catch((error) => {
          this.handleErrorResponse(error);
        })
        .finally(() => {
          this.setLoading('', 'stop');
          this.loader = false;
        });
    },

    getMeeting(meetingId) {
      this.$Progress.start();
      axios
        .get(`/projects/${this.projectSlug}/meetings/${meetingId}`)
        .then((response) => {
          this.meeting = getObjectData(response);
          this.$Progress.finish();
          this.$modal.show('ViewMeeting');
        })
        .catch((error) => {
          this.$Progress.finish();
          this.handleErrorResponse(error);
        });
    },

    meetingEdit() {
      this.form = {
        topic: this.meeting.topic,
        agenda: this.meeting.agenda,
        start_time: this.meeting.start_time,
        duration: this.meeting.duration,
        join_before_host: this.meeting.join_before_host,
        timezone: this.meeting.timezone,
        password: this.meeting.password,
      };
      this.isEditing = true;
    },

    meetingEditClose() {
      this.isEditing = false;
      this.updateMeetingRequest.reset();
      this.form = {};
      this.errors = {};
    },

    meetingModalClose() {
      this.$modal.hide('ViewMeeting');
      this.updateMeetingRequest.reset();
      this.meeting = {};
    },

    filterForm() {
      // Only filter out null/undefined, not false/0
      // we don't need the key name here so skip it in destructuring to avoid unused-var lint errors
      return Object.fromEntries(
        Object.entries(this.form).filter(([, value]) => value !== null && value !== undefined && value !== ''),
      );
    },

    initializeUpdateMeeting() {
      this.form.start_time = toUtcIsoString(this.form.start_time) || this.form.start_time;
      this.errors = {};
      this.loading = true;
      this.setLoading('Updating meeting, please wait...', 'start');
    },

    setLoading(message, action) {
      if (action === 'start') {
        this.loaderId = this.$vToastify.loader(message);
      } else {
        this.$vToastify.stopLoader(this.loaderId);
        this.loaderId = null;
      }
    },
  },
};
</script>

<style scoped>
.sync-status-badge {
  display: inline-block;
  padding: 2px 8px;
  border-radius: 4px;
  font-size: 11px;
  font-weight: 600;
  color: white;
  text-transform: uppercase;
}
</style>
