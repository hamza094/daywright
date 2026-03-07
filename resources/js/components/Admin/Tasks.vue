<template>
  <div>
    <div class="page-top mb-4">Welcome To Tasks Panel</div>
    <div class="container">
      <div class="row">
        <div class="col-12">
          <div class="card mb-5">
            <div class="card-header">
              <h3 class="card-title">Tasks &gt; {{ appliedFilter }}</h3>
            </div>

            <div class="card-body border-bottom py-3">
              <div class="d-flex align-items-center w-100">
                <div>Search:</div>
                <div class="ms-2 d-inline-block">
                  <input
                    type="text"
                    class="form-control form-control-sm"
                    placeholder="By Project"
                    name="search"
                    autocomplete="off"
                    v-model="searchTerm"
                    @keydown="searchTasks()" />
                </div>

                <div class="ms-auto text-secondary">
                  <span>
                    <button class="btn btn-sm btn-primary" @click="getResults(1, 'all')">All Tasks</button>
                    <button class="btn btn-sm btn-success" @click="getResults(1, 'active')">Active Tasks</button>
                    <button class="btn btn-sm btn-warning" @click="getResults(1, 'trashed')">Trashed Tasks</button>
                  </span>
                </div>

                <div class="ms-3">
                  <button class="btn btn-sm btn-danger" @click.prevent="bulkDelete()">Bulk Delete</button>
                </div>
              </div>
            </div>

            <div class="table-responsive" style="max-height: 600px; overflow-y: auto">
              <div v-if="isLoading" class="mt-3 text-center py-5">
                <div class="spinner-border text-primary" role="status">
                  <span class="visually-hidden">Loading...</span>
                </div>
                <p class="text-secondary mt-2">Loading tasks...</p>
              </div>
              <div v-else-if="errorMessage" class="mt-3 text-center py-4">
                <h4 class="text-danger">{{ errorMessage }}</h4>
                <button class="btn btn-sm btn-outline-primary mt-2" @click="getResults()">Retry</button>
              </div>
              <div v-else-if="message" class="mt-3 text-center">
                <h4>{{ message }}</h4>
              </div>
              <table v-else class="table card-table table-vcenter text-nowrap datatable">
                <thead>
                  <tr>
                    <th class="w-1">
                      <div class="form-check mb-1 align-middle">
                        <input
                          class="form-check-input"
                          type="checkbox"
                          aria-label="Select all tasks"
                          v-model="selectAll"
                          @change="selectAllTasks" />
                      </div>
                    </th>
                    <th>ID</th>
                    <th>Title</th>
                    <th>Desc</th>
                    <th>Status</th>
                    <th>Owner</th>
                    <th>Belongs To</th>
                    <th>State</th>
                    <th>Members</th>
                    <th>Due_At</th>
                    <th>Created_At</th>
                    <th>Updated_At</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="task in tasks.data" :key="task.id">
                    <td>
                      <div class="form-check">
                        <input
                          class="form-check-input"
                          type="checkbox"
                          aria-label="Select task"
                          v-model="selectedTasks"
                          :value="task.id" />
                      </div>
                    </td>
                    <td>{{ task.id }}</td>
                    <td>{{ task.title }}</td>
                    <td>{{ task.description }}</td>
                    <td>
                      <span class="task-option_labels-component" :style="{ backgroundColor: task.status.color }">{{
                        task.status.label
                      }}</span>
                    </td>
                    <td>
                      <router-link
                        :to="{ name: 'Profile', params: { uuid: task.owner.uuid } }"
                        class="admin-panel-link">
                        <div>{{ task.owner.name }}</div>
                      </router-link>
                    </td>
                    <td>
                      <router-link
                        :to="{ name: 'ProjectPage', params: { slug: task.project.slug } }"
                        class="admin-panel-link">
                        <div>{{ task.project.name }}</div>
                      </router-link>
                    </td>
                    <td>
                      <span v-if="task.state == 'active'" class="text-white bg badge bg-success me-1">{{
                        task.state
                      }}</span>
                      <span v-else class="text-white bg badge bg-warning me-1">{{ task.state }}</span>
                    </td>
                    <td style="max-height: 100px; overflow-y: auto">
                      <div v-for="member in task.members" :key="member.id || member.name">{{ member.name }}</div>
                    </td>
                    <td>
                      <span v-if="task.due_at">{{ task.due_at }}</span>
                      <span v-else>Not Defined</span>
                    </td>
                    <td>{{ task.created_at }}</td>
                    <td>{{ task.updated_at }}</td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div class="card-footer d-flex flex-wrap justify-content-between align-items-start gap-2 task-card-footer">
              <p class="mb-0">
                Showing <span>{{ from }}</span> to {{ to }} of {{ total }} entries
              </p>
              <div class="task-pagination">
                <pagination :data="tasks" @pagination-change-page="paginate($event, filter)"></pagination>
              </div>
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
      tasks: [],
      from: 0,
      to: 0,
      total: 0,
      selectedTasks: [],
      selectAll: false,
      message: '',
      isLoading: false,
      errorMessage: '',
      appliedFilter: '',
      filter: '',
      searchTerm: '',
    };
  },
  mounted() {
    this.getResults();
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
    getResults(page = 1, filter = null) {
      const activeFilter = (filter ?? this.filter) || 'all';
      const trimmedSearchTerm = this.searchTerm.trim();
      const queryParameters = {
        page: page,
      };

      if (activeFilter !== 'all') {
        queryParameters.filter = activeFilter;
      }

      if (trimmedSearchTerm !== '') {
        queryParameters.search = trimmedSearchTerm;
      }

      if (activeFilter === 'all') {
        this.appliedFilter = 'All Tasks';
        this.filter = 'all';
      }
      if (activeFilter === 'trashed') {
        this.appliedFilter = 'Trashed Tasks';
        this.filter = 'trashed';
      }
      if (activeFilter === 'active') {
        this.appliedFilter = 'Active Tasks';
        this.filter = 'active';
      }

      this.isLoading = true;
      this.errorMessage = '';

      axios
        .get('/admin/tasks', {
          params: queryParameters,
        })
        .then((response) => {
          this.tasks = response.data || '';
          this.from = this.tasks.meta.from || '';
          this.to = this.tasks.meta.to || '';
          this.total = this.tasks.meta.total || '';
          this.message = this.tasks.data?.length ? '' : response.data.message || '';
        })
        .catch((error) => {
          this.errorMessage = 'Failed to load tasks. Please try again.';
          this.handleErrorResponse(error);
        })
        .finally(() => {
          this.isLoading = false;
        });
    },
    searchTasks: debounce(function () {
      this.getResults();
    }, 1000),

    paginate(page, filter) {
      this.getResults(page, filter);
    },

    bulkDelete() {
      if (!this.guardAdminMutation()) {
        return;
      }

      if (this.selectedTasks.length === 0) {
        return;
      }
      this.sweetAlert('Yes, Delete Selected ' + this.selectedTasks.length + ' Tasks!').then((result) => {
        if (result.value) {
          axios
            .delete('/admin/tasks/bulk-delete', {
              data: { task_ids: this.selectedTasks },
            })
            .then((response) => {
              this.$vToastify.success(response.data.message);
              this.getResults();
            })
            .catch((error) => {
              this.handleErrorResponse(error);
            });
        }
        this.selectedTasks = [];
        this.selectAll = false;
      });
    },

    selectAllTasks() {
      if (this.selectAll) {
        this.selectedTasks = this.tasks.data.map((task) => task.id);
      } else {
        this.selectedTasks = [];
      }
    },
  },
};
</script>
<style>
.task-card-footer {
  row-gap: 0.75rem;
}

.task-pagination {
  margin-left: auto;
  max-width: 100%;
}

.task-pagination .pagination {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 0.25rem;
  margin-bottom: 0;
}

.task-pagination .page-item {
  margin-bottom: 0.25rem;
}

@media (max-width: 767.98px) {
  .task-card-footer {
    flex-direction: column;
    align-items: flex-start !important;
  }

  .task-pagination {
    margin-left: 0;
    width: 100%;
  }

  .task-pagination .pagination {
    justify-content: flex-start;
  }
}
</style>
