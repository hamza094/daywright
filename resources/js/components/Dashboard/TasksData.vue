<template>
  <div class="card shadow-sm" style="height: 32rem">
    <div class="card-header bg-white border-bottom">
      <div class="d-flex justify-content-between align-items-center">
        <div>
          <h5 class="mb-0 text-primary">Your Tasks</h5>
          <small class="text-muted" v-if="userTasks.length > 0">{{ userTasks.length }} tasks loaded</small>
          <div class="mt-1" v-if="appliedFilters.length > 0">
            <span v-for="filter in appliedFilters" :key="filter" class="badge badge-info mr-1">
              {{ filter }}
            </span>
          </div>
        </div>
        <div class="d-flex align-items-center">
          <div class="form-check form-check-inline mr-3">
            <input
              class="form-check-input"
              type="checkbox"
              id="assignedTasks"
              v-model="form.assigned"
              @change="loadTasks" />
            <label class="form-check-label" for="assignedTasks">
              <i class="fa-solid fa-user-check text-success"></i> Assigned
            </label>
          </div>
          <div class="form-check form-check-inline">
            <input
              class="form-check-input"
              type="checkbox"
              id="createdTasks"
              v-model="form.created"
              @change="loadTasks" />
            <label class="form-check-label" for="createdTasks">
              <i class="fa-solid fa-user-edit text-primary"></i> Created
            </label>
          </div>
        </div>
      </div>
    </div>
    <div class="card-body p-0">
      <!-- Filter Pills -->
      <div class="px-3 py-2 bg-light border-bottom">
        <div class="btn-group btn-group-sm" role="group">
          <button
            type="button"
            class="btn"
            :class="activeFilter === 'all' ? 'btn-primary' : 'btn-outline-primary'"
            @click="setFilter('all')">
            <i class="fa-solid fa-list"></i> All Tasks
          </button>
          <button
            type="button"
            class="btn"
            :class="activeFilter === 'overdue' ? 'btn-danger' : 'btn-outline-danger'"
            @click="setFilter('overdue')">
            <i class="fa-solid fa-exclamation-triangle"></i> Overdue
          </button>
          <button
            type="button"
            class="btn"
            :class="activeFilter === 'remaining' ? 'btn-warning' : 'btn-outline-warning'"
            @click="setFilter('remaining')">
            <i class="fa-solid fa-clock"></i> Remaining
          </button>
          <button
            type="button"
            class="btn"
            :class="activeFilter === 'completed' ? 'btn-success' : 'btn-outline-success'"
            @click="setFilter('completed')">
            <i class="fa-solid fa-check-circle"></i> Completed
          </button>
        </div>
      </div>

      <!-- Tasks List -->
      <div class="task-list" style="height: 22rem; overflow-y: auto">
        <div v-if="loading" class="text-center py-4">
          <div class="spinner-border text-primary" role="status">
            <span class="sr-only">Loading...</span>
          </div>
        </div>

        <div v-else-if="userTasks.length === 0" class="text-center py-4 text-muted">
          <i class="fa-solid fa-tasks fa-2x mb-2"></i>
          <p>No tasks found</p>
        </div>

        <div v-else>
          <div v-for="task in userTasks" :key="task.id" class="task-item border-bottom px-3 py-3 hover-bg-light">
            <div class="d-flex align-items-start">
              <!-- Task State Icon -->
              <div class="mr-3 mt-1">
                <i
                  :class="
                    task.state === 'created'
                      ? 'fa-solid fa-user-edit text-primary'
                      : 'fa-solid fa-user-check text-success'
                  "
                  :title="task.state === 'created' ? 'Created by you' : 'Assigned to you'"></i>
              </div>

              <!-- Task Content -->
              <div class="flex-grow-1">
                <!-- Task Title and Status -->
                <div class="d-flex align-items-center mb-2">
                  <h6 class="mb-0 mr-2 text-dark">{{ task.title }}</h6>
                  <span
                    v-if="task.status"
                    :style="'background-color: ' + task.status.color + '; color: white;'"
                    class="badge badge-pill px-2 py-1 text-xs">
                    {{ task.status.label }}
                  </span>
                </div>

                <!-- Task Meta Information -->
                <div class="d-flex flex-wrap align-items-center text-muted small mb-2">
                  <!-- Due Date -->
                  <span
                    v-if="task.due_at"
                    class="mr-3 due-date-label"
                    :class="isOverdue(task) ? 'text-danger font-weight-bold' : 'text-danger font-weight-semibold'">
                    <i class="fa-solid fa-calendar-alt text-danger"></i>
                    <strong>Due:</strong> {{ task.due_at | datetime }}
                    <span v-if="isOverdue(task)" class="badge badge-danger ml-1 px-2 py-1" style="font-size: 0.65rem">
                      OVERDUE
                    </span>
                  </span>

                  <!-- Project -->
                  <span class="mr-3">
                    <i class="fa-solid fa-project-diagram"></i>
                    <router-link
                      :to="'/projects/' + task.project.slug"
                      class="text-decoration-none"
                      :class="{ 'text-muted': task.project.state === 'trashed' }">
                      {{ task.project.name }}
                    </router-link>
                  </span>

                  <!-- Created At -->
                  <span> <i class="fa-regular fa-clock"></i> {{ task.created_at | datetime }} </span>
                </div>

                <!-- Assignees -->
                <div v-if="task.assignee && task.assignee.length > 0" class="d-flex align-items-center">
                  <small class="text-muted mr-2">Assigned to:</small>
                  <div class="d-flex">
                    <span v-for="user in task.assignee" :key="user.id" class="mr-2">
                      <router-link
                        :to="'/user/' + user.uuid + '/profile'"
                        class="text-decoration-none text-primary small"
                        target="_blank"
                        rel="noopener noreferrer">
                        <i class="fa-solid fa-user"></i> {{ user.name }}
                      </router-link>
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Load More Button -->
      <div v-if="hasMoreTasks" class="px-3 py-2 bg-light border-top">
        <button @click="loadMoreTasks" :disabled="loadingMore" class="btn btn-outline-primary btn-sm btn-block">
          {{ loadingMore ? 'Loading...' : 'Load More' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script>
import { buildDashboardTaskParams, readDashboardTasks } from '../../utils/dashboardResponse.js';

export default {
  data() {
    return {
      form: {
        assigned: true,
        created: true,
      },
      userTasks: [],
      appliedFilters: [],
      meta: {
        next_cursor: null,
        prev_cursor: null,
        per_page: 15,
      },
      links: {
        next: null,
        prev: null,
      },
      activeFilter: 'all',
      loading: false,
      loadingMore: false,
      loadMoreRequestId: 0,
    };
  },
  computed: {
    hasMoreTasks() {
      return this.meta.next_cursor !== null;
    },
  },
  mounted() {
    this.loadTasks();
  },
  methods: {
    loadTasks() {
      this.loading = true;
      const params = buildDashboardTaskParams({
        assigned: this.form.assigned,
        created: this.form.created,
        activeFilter: this.activeFilter,
      });

      axios
        .get('/dashboard/tasks', { params })
        .then((response) => {
          const { tasks, appliedFilters, meta, links } = readDashboardTasks(response);

          this.userTasks = tasks;
          this.appliedFilters = appliedFilters;
          this.meta = meta;
          this.links = links;
        })
        .catch((error) => {
          this.handleErrorResponse(error);
          this.userTasks = [];
          this.appliedFilters = [];
          this.meta = {
            next_cursor: null,
            prev_cursor: null,
            per_page: 15,
          };
          this.links = {
            next: null,
            prev: null,
          };
        })
        .finally(() => {
          this.loading = false;
        });
    },

    loadMoreTasks() {
      if (!this.meta.next_cursor) {
        return;
      }

      this.loadingMore = true;
      const currentRequestId = ++this.loadMoreRequestId;
      const params = buildDashboardTaskParams({
        assigned: this.form.assigned,
        created: this.form.created,
        activeFilter: this.activeFilter,
      });

      params.cursor = this.meta.next_cursor;

      axios
        .get('/dashboard/tasks', { params })
        .then((response) => {
          if (currentRequestId !== this.loadMoreRequestId) {
            return;
          }
          const { tasks, meta, links } = readDashboardTasks(response);

          this.userTasks = [...this.userTasks, ...tasks];
          this.meta = meta;
          this.links = links;
        })
        .catch((error) => {
          if (currentRequestId !== this.loadMoreRequestId) {
            return;
          }
          this.handleErrorResponse(error);
        })
        .finally(() => {
          if (currentRequestId === this.loadMoreRequestId) {
            this.loadingMore = false;
          }
        });
    },

    setFilter(filterType) {
      this.activeFilter = filterType;
      this.loadTasks();
    },

    isOverdue(task) {
      if (!task.due_at) return false;
      const dueDate = new Date(task.due_at);
      const now = new Date();
      return dueDate < now && task.status?.label !== 'Completed';
    },
  },
};
</script>

<style scoped>
.hover-bg-light:hover {
  background-color: #f8f9fa !important;
  cursor: pointer;
}

.task-item {
  transition: background-color 0.2s ease;
}

.task-item:last-child {
  border-bottom: none !important;
}

.text-xs {
  font-size: 0.75rem;
}

.btn-group .btn {
  border-radius: 0.25rem;
  margin-right: 0.25rem;
}

.btn-group .btn:last-child {
  margin-right: 0;
}

.task-list::-webkit-scrollbar {
  width: 6px;
}

.task-list::-webkit-scrollbar-track {
  background: #f1f1f1;
}

.task-list::-webkit-scrollbar-thumb {
  background: #c1c1c1;
  border-radius: 3px;
}

.task-list::-webkit-scrollbar-thumb:hover {
  background: #a8a8a8;
}

.badge {
  font-size: 0.7rem;
  font-weight: 500;
}

.form-check-label {
  font-size: 0.9rem;
  font-weight: 500;
}

.due-date-label {
  color: #dc3545 !important;
}

.due-date-label strong {
  color: #dc3545;
}

.card {
  border: 1px solid #e3e6f0;
}

.card-header {
  border-bottom: 1px solid #e3e6f0;
}
</style>
