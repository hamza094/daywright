<template>
  <div>
    <div class="project-note">
      <div id="wrapper">
        <!-- Project Notes Section -->
        <p class="mb-2"><b>Add Project Note:</b></p>

        <SubscriptionCheck>
          <form v-if="access" id="paper" method="post" @keyup.enter.prevent="ProjectNote">
            <textarea
              placeholder="Write Project Notes"
              id="project-note-text"
              ref="projectNote"
              name="notes"
              rows="4"
              v-model="form.notes"
              v-text="notes">
            </textarea>
          </form>

          <textarea
            v-if="!access"
            placeholder="Only project members and owners are allowed to write project notes."
            id="project-note-text-readonly"
            rows="4"
            v-model="form.notes"
            v-text="notes"
            readonly>
          </textarea>
        </SubscriptionCheck>
      </div>
    </div>
    <hr />

    <div class="invite" v-if="access">
      <p><b>Project Invitations:</b></p>

      <input
        type="text"
        placeholder="Search user for invitation"
        class="form-control"
        v-model="query"
        @input="searchUsers" />

      <div class="invite-list">
        <ul>
          <li v-if="isLoading" class="loading-spinner-container">
            <div class="loading-spinner"></div>
          </li>

          <!-- Show "no users found" message -->
          <li v-else-if="!isLoading && query && results.length === 0">No users found.</li>

          <!-- Show user results -->
          <li v-else-if="results.length > 0 && query" v-for="result in results.slice(0, 5)" :key="result.id">
            <div @click.prevent="inviteUser(result.email)">{{ result.name }} ({{ result.email }})</div>
          </li>
        </ul>
      </div>
    </div>
    <hr />

    <div class="project_members">
      <div class="task-top">
        <p>
          <b>Project Members</b>
          <button
            type="button"
            class="btn btn-link p-0"
            data-toggle="collapse"
            data-target="#memberProject"
            aria-expanded="false"
            aria-controls="memberProject">
            <i class="fa-solid fa-angle-down float-right"></i>
          </button>
        </p>
      </div>

      <div class="collapse" id="memberProject">
        <div class="row">
          <div v-for="member in members" :key="member.id" class="col-6">
            <div class="project_members-detail text-center">
              <router-link :to="'/user/' + member.uuid + '/profile'">
                <img v-if="member.avatar" :src="member.avatar" alt="" class="img-fluid rounded-circle" />
                <p>
                  <span v-if="member.uuid == owner.uuid" class="badge badge-success">project owner </span>
                  <br />
                  <span>{{ member.name }}</span>
                  <br />
                  <span>({{ member.username }})</span>
                </p>
                <p></p>
              </router-link>
              <button
                v-if="ownerLogin && member.uuid !== owner.uuid"
                type="button"
                @click.prevent="removeMember(member.uuid)"
                class="text-danger btn btn-link p-0">
                Remove
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="project_members">
      <div class="task-top">
        <p>
          <b>Pending Members</b>
          <button
            type="button"
            class="btn btn-link p-0"
            data-toggle="collapse"
            data-target="#pendingMembers"
            aria-expanded="false"
            aria-controls="pendingMembers">
            <i class="fa-solid fa-angle-down float-right"></i>
          </button>
        </p>
      </div>

      <div class="collapse" id="pendingMembers">
        <div class="row">
          <!-- Check if pendingMembers array is not empty -->
          <template v-if="pendingMembers.length">
            <div v-for="member in pendingMembers" :key="member.id" class="col-6">
              <div class="project_members-detail text-center">
                <router-link :to="'/user/' + member.uuid + '/profile'">
                  <img v-if="member.avatar" :src="member.avatar" class="img-fluid rounded-circle" alt="" />
                  <p>
                    <span>{{ member.name }}</span>
                    <br />
                    <span>({{ member.username }})</span>
                  </p>
                  <p></p>
                </router-link>
                <span>{{ member.invitation_sent_at }}</span>
                <br />
                <button
                  v-if="ownerLogin && member.uuid !== owner.uuid"
                  type="button"
                  @click.prevent="cancelRequest(member.uuid, member)"
                  class="text-danger btn btn-link p-0">
                  Cancel
                </button>
              </div>
            </div>
          </template>

          <!-- Show a message when no members are found -->
          <template v-else>
            <div class="col-12 text-center">
              <p>No pending or invited members found.</p>
            </div>
          </template>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { mapMutations } from 'vuex';
import SubscriptionCheck from '../../SubscriptionChecker.vue';
import { debounce } from 'lodash';

export default {
  components: { SubscriptionCheck },
  props: {
    slug: {
      type: String,
      required: true,
    },
    notes: {
      type: String,
      default: '',
    },
    members: {
      type: Array,
      default: () => [],
    },
    owner: {
      type: Object,
      required: true,
    },
    access: {
      type: Boolean,
      default: false,
    },
    ownerLogin: {
      type: Boolean,
      default: false,
    },
  },
  data() {
    return {
      form: {
        notes: '',
      },
      query: null,
      isLoading: false,
      results: [],
      errors: {},
      pendingMembers: [],
    };
  },
  watch: {
    query: debounce(function (newQuery) {
      this.searchUsers(newQuery);
    }, 500),
  },

  created() {
    this.loadPendingRequests();
  },

  methods: {
    ...mapMutations('project', ['updateNotes', 'noteScore', 'updateScore', 'detachMember']),

    ProjectNote() {
      this.$Progress.start();
      // Use Vue ref for DOM access instead of document.getElementById
      if (this.$refs.projectNote && this.$refs.projectNote.blur) {
        this.$refs.projectNote.blur();
      }

      axios
        .patch('/projects/' + this.slug, {
          notes: this.form.notes,
        })
        .then(({ data }) => {
          const { project, message } = data;
          this.$Progress.finish();
          this.updateNotes(project.notes);
          this.$vToastify.success(message);
          this.noteScore(project.score);
        })
        .catch((error) => {
          this.$Progress.fail();
          const dataErrors = error.response.data.errors;

          if (dataErrors && dataErrors.notes[0]) {
            this.$vToastify.warning(dataErrors.notes[0]);
          }
          if (error.response.data.error) {
            this.$vToastify.warning(error.response.data.error);
          }
          this.form.notes = this.notes;
        })
        .finally(() => {
          document.getElementById('focus-target').focus();
        });
    },

    searchUsers(query) {
      if (!query) {
        this.results = [];
        return;
      }
      this.isLoading = true;

      axios
        .get('/users/search', { params: { query } })
        .then((response) => {
          this.results = response.data;
        })
        .catch((error) => {
          this.$vToastify.warning(error.response.data.error);
        })
        .finally(() => {
          this.isLoading = false;
        });
    },

    inviteUser(userEmail) {
      axios
        .post(
          '/projects/' + this.slug + '/invitations',
          {
            email: userEmail,
          },
          { useProgress: true },
        )
        .then((response) => {
          this.query = '';
          this.results = '';
          this.$vToastify.success(response.data.message);
        })
        .catch((error) => {
          this.query = '';
          this.results = '';

          const errors = error.response?.data?.errors;

          if (error.response?.status === 422 && errors) {
            Object.values(errors)
              .flat()
              .forEach((message) => {
                this.$vToastify.warning(message);
              });
          } else {
            this.$vToastify.warning(error.response?.data?.error || 'An unexpected error occurred.');
          }
        });
    },

    removeMember(id) {
      this.sweetAlert('Yes, Remove Member').then((result) => {
        if (result.isConfirmed) {
          axios
            .get('/projects/' + this.slug + '/remove/member/' + id, { useProgress: true })
            .then((response) => {
              this.detachMember(response.data.user.uuid);
              this.$vToastify.info(response.data.message);
            })
            .catch((error) => {
              this.$vToastify.warning(error.response?.data?.error || 'Failed to remove the member. Try again.');
            });
        }
      });
    },

    cancelRequest(userId) {
      this.sweetAlert('Yes, cancel request').then((result) => {
        if (!result.isConfirmed) return;

        axios
          .get(`/projects/${this.slug}/cancel/invitation/users/${userId}`, { useProgress: true })
          .then((response) => {
            this.pendingMembers = this.pendingMembers.filter((pendingMember) => pendingMember.uuid !== userId);
            this.$vToastify.info(response.data.message);
          })
          .catch((error) => {
            this.$vToastify.warning(error.response?.data?.message || 'Failed to cancel the request. Try again.');
          });
      });
    },

    loadPendingRequests() {
      axios
        .get(`/projects/${this.slug}/pending/invitations`)
        .then((response) => {
          this.pendingMembers = response.data.pending_invitations;
        })
        .catch((error) => {
          this.$vToastify.warning(error.response?.data?.message || 'Failed to load pending invitations.');
        });
    },
  },
};
</script>
