<template>
  <div>
    <p class="pro-info">Project Invitations</p>
    <div v-if="loading" class="text-center my-4">
      <div class="d-inline-flex align-items-center">
        <output class="spinner-border spinner-border-sm text-secondary mr-2" aria-hidden="true"></output>
        <span>Loading invitations...</span>
      </div>
    </div>
    <div v-else>
      <div class="row" v-if="invitations.length">
        <div v-for="project in invitations" class="col-md-5 mb-4" :key="project.id">
          <div class="card invitation border-0 shadow-sm h-100">
            <div class="card-header bg-transparent border-0 text-center pt-3 pb-0">
              <div class="small text-muted">
                <i class="fa-regular fa-folder-open text-secondary mr-1"></i>
                Project Invitation
              </div>
            </div>

            <div class="card-body text-center pt-2">
              <div class="mb-3">
                <div class="small text-muted mb-1">
                  <i class="fa-regular fa-folder-open text-secondary mr-1"></i>
                  Project
                </div>
                <router-link
                  class="h6 font-weight-bold text-decoration-none text-secondary"
                  :to="{ name: 'ProjectPage', params: { slug: project.slug } }">
                  {{ project.name }}
                </router-link>
              </div>

              <div class="mb-3">
                <div class="small text-muted mb-2">
                  <i class="fa-regular fa-user text-secondary mr-1"></i>
                  Owner
                </div>
                <div class="d-flex align-items-center justify-content-center">
                  <img
                    :src="$options.filters.safeUrl(ownerAvatar(project.owner))"
                    :alt="(project.owner && project.owner.name ? project.owner.name : 'Owner') + ' avatar'"
                    class="rounded-circle mr-2"
                    width="32"
                    height="32" />

                  <router-link
                    class="text-decoration-none text-secondary"
                    :to="{ name: 'Profile', params: { uuid: project.owner.uuid } }"
                    target="_blank"
                    rel="noopener noreferrer">
                    {{ project.owner.name }}
                  </router-link>
                </div>
              </div>

              <div class="d-flex justify-content-center">
                <button class="btn btn-app-secondary btn-sm mr-2" @click.prevent="becomeMember(project.slug)">
                  <i class="fa-regular fa-check-circle mr-1"></i>
                  Become Member
                </button>
                <button class="btn btn-outline-danger btn-sm" @click.prevent="rejectInvitation(project.slug)">
                  <i class="fa-regular fa-times-circle mr-1"></i>
                  Ignore Invitation
                </button>
              </div>
            </div>

            <div class="card-footer bg-transparent border-0 text-center pb-3">
              <small class="text-muted">
                <i class="fa-regular fa-clock text-secondary mr-1"></i>
                Invitation received on <span class="font-weight-bold">{{ project.invitation_sent_at }}</span>
              </small>
            </div>
          </div>
        </div>
      </div>
      <div v-else>
        <h3>{{ serverMessage }}</h3>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  data() {
    return {
      invitations: [],
      loading: false,
      serverMessage: '',
    };
  },
  created() {
    this.fetchInvitations();
  },
  methods: {
    ownerAvatar(owner) {
      const avatar = owner && owner.avatar ? owner.avatar : '';
      if (avatar) return avatar;

      const name = owner && owner.name ? owner.name : 'User';
      return 'https://ui-avatars.com/api/?name=' + encodeURIComponent(name);
    },
    async fetchInvitations() {
      this.loading = true;
      try {
        const { data } = await axios.get('/me/invitations');
        this.invitations = data.invitations || [];
        if (data.message) {
          this.serverMessage = data.message;
        }
      } catch (error) {
        this.handleApiError(error, 'Failed to load invitations.');
      } finally {
        this.loading = false;
      }
    },
    async becomeMember(slug) {
      this.$Progress.start();
      try {
        const { data } = await axios.get(`/projects/${slug}/accept-invitation`);
        this.$Progress.finish();
        this.$vToastify.success(data.message);

        this.invitations = this.invitations.filter((p) => p.id !== data.project.id);
      } catch (error) {
        this.$Progress.fail();
        this.notifyError(error, 'Failed to accept the invitation. Try again.');
      }
    },
    async rejectInvitation(slug) {
      this.$Progress.start();
      try {
        const { data } = await axios.get(`/projects/${slug}/reject/invitation`);
        this.$Progress.finish();
        this.$vToastify.info(data.message);

        this.invitations = this.invitations.filter((p) => p.id !== data.project.id);
      } catch (error) {
        this.$Progress.fail();
        this.notifyError(error, 'Failed to reject the request. Try again.');
      }
    },
    notifyError(error, fallbackMsg) {
      const resp = error.response?.data || {};
      const msg =
        resp.message || resp.error || (resp.errors ? Object.values(resp.errors).flat().join(' ') : '') || fallbackMsg;
      this.$vToastify.warning(msg);
    },
  },
};
</script>
