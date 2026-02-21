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
                  <tr v-for="user in users.data" :key="user.id">
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
                      <button
                        :disabled="adminActionUserId === user.id"
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
  methods: {
    canMutateAdmin() {
      const user = this.$store.state.currentUser.user || {};

      return !!user.isAdmin && !!user.twoFactorEnabled;
    },
    guardAdminMutation() {
      if (this.canMutateAdmin()) {
        return true;
      }

      this.$vToastify.error('Please enable two-factor authentication to perform admin changes.');
      this.$router.push({ name: 'Profile', params: { uuid: this.$store.state.currentUser.user?.uuid } });

      return false;
    },
    async getResults(page = 1) {
      const queryParameters = {
        page: page,
        search: this.searchTerm,
      };

      const filteredParameters = Object.fromEntries(
        Object.entries(queryParameters).filter(([_, value]) => value !== undefined && value !== ''),
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
      const index = this.users.data.findIndex((existingUser) => existingUser.id === user.id);

      if (index !== -1) {
        this.users.data.splice(index, 1, user);
      }
    },
    async toggleAdminAccess(user) {
      if (!this.guardAdminMutation()) {
        return;
      }

      if (this.adminActionUserId === user.id) {
        return;
      }

      const actionLabel = user.isAdmin ? 'revoke admin access from' : 'grant admin access to';

      if (!window.confirm(`Are you sure you want to ${actionLabel} ${user.name}?`)) {
        return;
      }

      this.adminActionUserId = user.id;

      try {
        const endpoint = user.isAdmin
          ? `/admin/users/${user.uuid}/revoke-admin`
          : `/admin/users/${user.uuid}/grant-admin`;

        const response = await axios.post(endpoint);

        if (response?.data?.user) {
          this.handleUpdateUser(response.data.user);
        }

        if (response?.data?.message) {
          this.$vToastify.success(response.data.message);
        }
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
  mounted() {
    this.getResults();
  },
};
</script>
