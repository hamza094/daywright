<template>
  <div>
    <div class="page-top mb-4">Welcome To Tasks Panel</div>
    <div class="container">
      <div class="row">
        <div class="col-12">
          <div class="card">
            <div class="card-header">
              <h3 class="card-title">Users</h3>
            </div>

            <div class="card-body border-bottom py-3">
              <div class="d-flex">
                Search:
                <div class="ms-2 d-inline-block">
                  <input
                    type="text"
                    class="form-control form-control-sm"
                    placeholder="By Name"
                    name="search"
                    autocomplete="off"
                    v-model="searchTerm"
                    @keydown="searchUsers()" />
                </div>

                <div class="ms-auto text-secondary"></div>
              </div>
            </div>
            <div class="table-responsive" style="max-height: 600px; overflow-y: auto">
              <table class="table card-table table-vcenter text-nowrap datatable">
                <thead>
                  <tr>
                    <th>Name</th>
                    <th>UserName</th>
                    <th>Avatar</th>
                    <th>Email</th>
                    <th>Timezone</th>
                    <th>Created At</th>
                    <th>IsSubscribed</th>
                    <th>Active Projects Count</th>
                    <th>Project Member</th>
                    <th>Admin Access</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="user in users.data" :key="user.uuid">
                    <td>
                      <router-link :to="{ name: 'Profile', params: { uuid: user.uuid } }" class="admin-panel-link">
                        <div>{{ user.name }}</div>
                      </router-link>
                    </td>
                    <td>{{ user.username }}</td>
                    <td>
                      <img
                        :src="$options.filters.safeUrl(user.avatar)"
                        :alt="user.name ? user.name + ' avatar' : 'User avatar'" />
                    </td>
                    <td>{{ user.email }}</td>
                    <td>{{ user.timezone }}</td>
                    <td>{{ user.created_at }}</td>
                    <td>{{ user.isSubscribed }}</td>
                    <td>{{ user.projects_count }}</td>
                    <td>{{ user.projects_member }}</td>
                    <td>
                      <span class="me-2">{{ user.isAdmin ? 'Yes' : 'No' }}</span>
                      <div class="small text-muted">
                        <div v-if="user.admin_granted_by && user.admin_granted_at">
                          Granted by {{ user.admin_granted_by }} on {{ user.admin_granted_at }}
                        </div>
                        <div v-if="user.admin_revoked_by && user.admin_revoked_at">
                          Revoked by {{ user.admin_revoked_by }} on {{ user.admin_revoked_at }}
                        </div>
                      </div>
                      <button
                        :disabled="adminActionUserId === user.uuid"
                        class="btn btn-sm"
                        :class="user.isAdmin ? 'btn-outline-danger' : 'btn-outline-success'"
                        @click="toggleAdminAccess(user)">
                        {{ user.isAdmin ? 'Revoke' : 'Grant' }}
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div class="card-footer d-flex">
              <p class="float-left">
                Showing <span>{{ from }}</span> to {{ to }}<span></span> of <span></span>{{ total }} entries
              </p>
              <pagination :data="users" @pagination-change-page="getResults"></pagination>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
<script>
import { debounce } from 'lodash';

export default {
  data() {
    return {
      users: [],
      from: 0,
      to: 0,
      total: 0,
      searchTerm: '',
      adminActionUserId: null,
    };
  },
  mounted() {
    this.getResults();
  },
  methods: {
    getCurrentUser() {
      return this.$store.state.currentUser.user || {};
    },

    canMutateAdmin() {
      const user = this.getCurrentUser();

      return !!user.isAdmin && !!user.twoFactorEnabled;
    },

    guardAdminMutation() {
      if (this.canMutateAdmin()) {
        return true;
      }

      this.$vToastify.error(
        'Two-factor authentication is required for admin changes. Enable it from your profile settings to continue.',
      );

      return false;
    },

    async getResults(page = 1) {
      const queryParameters = {
        page: page,
        search: this.searchTerm,
      };

      const filteredParameters = Object.fromEntries(
        Object.entries(queryParameters).filter(([, value]) => value !== undefined && value !== ''),
      );

      try {
        const response = await axios.get('/admin/users', {
          params: filteredParameters,
        });

        this.users = response.data || '';
        this.from = this.users.meta.from || '';
        this.to = this.users.meta.to || '';
        this.total = this.users.meta.total || '';
      } catch (error) {
        this.handleErrorResponse(error);
      }
    },

    handleUpdateUser(user) {
      const index = this.users.data.findIndex((existingUser) => existingUser.uuid === user.uuid);

      if (index !== -1) {
        this.users.data.splice(index, 1, user);
      }
    },

    isCurrentUser(updatedUser) {
      const currentUser = this.getCurrentUser();

      return !!updatedUser?.uuid && updatedUser.uuid === currentUser.uuid;
    },

    isAdminActionInProgress(user) {
      return this.adminActionUserId === user.uuid;
    },

    isAdminRevokeAction(user) {
      return Boolean(user.isAdmin);
    },

    getAdminActionLabel(isRevoking) {
      return isRevoking ? 'revoke admin access from' : 'grant admin access to';
    },

    confirmAdminAction(user, isRevoking) {
      const actionLabel = this.getAdminActionLabel(isRevoking);

      return window.confirm(`Are you sure you want to ${actionLabel} ${user.name}?`);
    },

    getAdminMutationEndpoint(user, isRevoking) {
      if (isRevoking) {
        return `/admin/users/${user.uuid}/revoke-admin`;
      }

      return `/admin/users/${user.uuid}/grant-admin`;
    },

    async refreshCurrentUserIfNeeded(updatedUser) {
      if (!this.isCurrentUser(updatedUser)) {
        return false;
      }

      await this.$store.dispatch('currentUser/bootstrapSession');

      return true;
    },

    shouldRedirectAfterSelfRevoke(isRevoking) {
      return isRevoking && !this.getCurrentUser()?.isAdmin;
    },

    getAdminSuccessMessage(responseMessage, user, isRevoking) {
      if (responseMessage) {
        return responseMessage;
      }

      if (isRevoking) {
        return `Admin access revoked for ${user.name}.`;
      }

      return `Admin access granted to ${user.name}.`;
    },

    async toggleAdminAccess(user) {
      if (!this.guardAdminMutation()) {
        return;
      }

      if (this.isAdminActionInProgress(user)) {
        return;
      }

      const isRevoking = this.isAdminRevokeAction(user);

      if (!this.confirmAdminAction(user, isRevoking)) {
        return;
      }

      this.adminActionUserId = user.uuid;

      try {
        const endpoint = this.getAdminMutationEndpoint(user, isRevoking);

        const response = await axios.post(endpoint);
        const updatedUser = response?.data?.user;

        if (updatedUser) {
          this.handleUpdateUser(updatedUser);

          const refreshedCurrentUser = await this.refreshCurrentUserIfNeeded(updatedUser);

          if (refreshedCurrentUser && this.shouldRedirectAfterSelfRevoke(isRevoking)) {
            this.$vToastify.success('Your admin access has been revoked. Redirecting to dashboard.');
            await this.$router.push({ name: 'Dashboard' });
            return;
          }
        }

        this.$vToastify.success(this.getAdminSuccessMessage(response?.data?.message, user, isRevoking));
      } catch (error) {
        this.handleErrorResponse(error);
      } finally {
        this.adminActionUserId = null;
      }
    },
    searchUsers: debounce(function () {
      this.getResults();
    }, 1000),
  },
};
</script>
