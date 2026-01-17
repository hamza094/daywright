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
                    <th>Roles</th>
                    <th>Active Projects Count</th>
                    <th>Project Member</th>
                    <th>Last Active</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(user, index) in users.data" :key="user.id">
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
                    <td>
                      <div class="dropdown">
                        <a
                          class="dropdown-toggle text-secondary"
                          href="#"
                          data-bs-toggle="dropdown"
                          aria-haspopup="true"
                          aria-expanded="false">
                          <span v-if="!user.roles || user.roles.length === 0"> Not Defined </span>
                          <span v-else>
                            <span v-for="role in user.roles">
                              {{ role.name }}
                            </span>
                          </span>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end" style="">
                          <div v-for="role in roles" :key="role.id">
                            <a
                              :class="{
                                'dropdown-item': true,
                                active: user && user.roles && hasRole(user.roles, role),
                              }"
                              @click="assignUserRole(role.id, user.id)"
                              >{{ role.name }}</a
                            >
                          </div>
                        </div>
                      </div>
                    </td>
                    <td>{{ user.projects_count }}</td>
                    <td>{{ user.projects_member }}</td>
                    <td>{{ user.last_active }}</td>
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
      <RolesAndPermissions></RolesAndPermissions>
    </div>
  </div>
</template>
<script>
import { debounce } from 'lodash';
import RolesAndPermissions from './RolesAndPermissions.vue';
import { mapState } from 'vuex';

export default {
  components: { RolesAndPermissions },
  data() {
    return {
      users: [],
      from: 0,
      to: 0,
      total: 0,
      searchTerm: '',
    };
  },
  computed: {
    ...mapState('roles', ['roles']),
  },
  methods: {
    getResults(page = 1) {
      const queryParameters = {
        page: page,
        search: this.searchTerm,
      };

      const filteredParameters = Object.fromEntries(
        Object.entries(queryParameters).filter(([_, value]) => value !== undefined && value !== ''),
      );

      axios
        .get('/admin/users', {
          params: filteredParameters,
        })
        .then((response) => {
          this.users = response.data || '';
          this.from = this.users.meta.from || '';
          this.to = this.users.meta.to || '';
          this.total = this.users.meta.total || '';
        })
        .catch((error) => {});
    },
    assignUserRole(roleId, userId) {
      axios
        .get('/admin/assign/users/' + userId + '/roles/' + roleId)
        .then((response) => {
          this.$vToastify.success(response.data.message);
          this.handleUpdateUser(response.data.user);
        })
        .catch((error) => {
          if (error.response.status === 422) {
            this.$vToastify.warning(error.response.data.errors.role[0]);
          } else {
            this.$vToastify.warning('Error! Contact Admin support');
          }
        });
    },
    handleUpdateUser(user) {
      const index = this.users.data.findIndex((existingUser) => existingUser.id === user.id);

      if (index !== -1) {
        this.users.data.splice(index, 1, user);
      }
    },
    hasRole(userRoles, loopRole) {
      return userRoles && userRoles.some((userRole) => userRole.id === loopRole.id);
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

<style>
.role-container {
  align-items: center;
  justify-content: space-between;
  margin-bottom: 10px; /* Adjust as needed */
}

.role-content {
  display: flex;
  align-items: center;
}

.button-group {
  display: flex;
  align-items: center;
}

.role-dropdown {
  /* Your existing styles for dropdown */
}

.btn-danger {
  margin-left: 10px; /* Adjust margin as needed */
}
</style>
