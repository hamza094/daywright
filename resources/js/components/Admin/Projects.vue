<template>
  <div>
    <div class="page-top mb-4">Welcome To Your Project Panel</div>
    <div class="container">
      <div class="row">
        <div class="col-12">
          <div class="card">
            <div class="card-header">
              <h3 class="card-title">Projects</h3>
              <span class="ml-2" v-for="(filter, index) in appliedFilters" :key="index">
                <span>> {{ filter }} </span>
              </span>
            </div>

            <div class="card-body border-bottom py-3">
              <div class="d-flex">
                <div class="filter-dropdown">
                  <span class="filter" @click="isPop = !isPop">Filters <i class="fa-solid fa-filter"></i></span>
                  <div class="filter-dropdown_item" v-show="isPop">
                    <div class="container">
                      <div class="filter-content">
                        <div class="row">
                          <h4>Filter By</h4>
                          <div class="col-md-4">
                            <input
                              class="form-check-input"
                              type="radio"
                              v-model="form.projects"
                              id="RadioProjects"
                              value="active"
                              @click="toggleRadio('active')" />
                            Active Projects
                          </div>
                          <div class="col-md-4">
                            <input
                              class="form-check-input"
                              type="radio"
                              v-model="form.projects"
                              id="RadioProjects"
                              value="trashed"
                              @click="toggleRadio('trashed')" />
                            Trashed Projects
                          </div>
                          <div class="col-md-4">
                            <div class="form-check">
                              <input
                                class="form-check-input"
                                type="checkbox"
                                id="CheckTasks"
                                v-model="form.activeTasks" />
                              <label class="form-check-label" for="CheckTasks"> Active Tasks </label>
                            </div>
                          </div>
                        </div>

                        <div class="filter-content_border"></div>
                        <div class="row">
                          <div class="col-md-12">
                            <h4>By Stage</h4>
                            <ul class="filter-list">
                              <li class="filter-list_item" v-for="stage in stages" :key="stage.id || stage.name">
                                <input
                                  class="form-check-input form-radio"
                                  type="radio"
                                  name="stageRadio"
                                  v-model="form.stage"
                                  :value="stage.id"
                                  @click.prevent="toggleStage(stage.id)" />
                                <label class="form-check-label">
                                  {{ stage.name }}
                                </label>
                              </li>
                              <li class="filter-list_item">
                                <input
                                  class="form-check-input form-radio"
                                  type="radio"
                                  name="stageRadio"
                                  v-model="form.stage"
                                  :value="0"
                                  @click.prevent="toggleStage(0)" />
                                <label class="form-check-label"> Clo/Pos </label>
                              </li>
                            </ul>
                          </div>
                        </div>

                        <div class="filter-content_border"></div>

                        <div class="row mb-3">
                          <h4>Search By fields</h4>
                          <div class="col-md-4">
                            <input
                              class="form-check-input"
                              v-model="form.status"
                              type="radio"
                              id="statusCheck"
                              value="hot"
                              @click="toggleStatus('hot')" />
                            Hot Projects <span class="status-dot bg-red"></span>
                          </div>
                          <div class="col-md-4">
                            <input
                              class="form-check-input"
                              v-model="form.status"
                              type="radio"
                              id="statusCheck"
                              value="cold"
                              @click="toggleStatus('cold')" />
                            Cold Projects <span class="status-dot bg-blue"></span>
                          </div>
                          <div class="col-md-4">
                            <input
                              class="form-check-input"
                              type="checkbox"
                              v-model="form.hasMembers"
                              id="flexCheckDefault" />
                            <label class="form-check-label" for="flexCheckDefault"> Has Members </label>
                          </div>
                        </div>

                        <div class="filter-content_border"></div>

                        <div class="row">
                          <h4>Search By Date</h4>

                          <div class="col-md-6">
                            <h6 style="display: inline-block">From:</h6>
                            <span
                              style="display: inline-block"
                              class="form-date_close"
                              @click.prevent="form.startdate = ''"
                              v-if="form.startdate !== ''"
                              >x</span
                            >
                            <datetime type="date" value-zone="local" zone="local" v-model="form.startdate"> </datetime>
                          </div>
                          <div class="col-md-6">
                            <h6 style="display: inline-block">To:</h6>
                            <span
                              style="display: inline-block"
                              class="form-date_close"
                              @click.prevent="form.enddate = ''"
                              v-if="form.enddate !== ''"
                              >x</span
                            >
                            <datetime type="date" value-zone="local" zone="local" v-model="form.enddate"> </datetime>
                          </div>
                        </div>
                        <button class="float-right mt-4 mb-2 btn btn-primary btn-pill w-50" @click.prevent="filterBy()">
                          Filter
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="ms-auto text-secondary">
                  <button class="btn btn-sm btn-danger" @click.prevent="bulkDelete">Bulk Delete</button>
                </div>

                <div class="ms-auto text-secondary">
                  Search:
                  <div class="ms-2 d-inline-block">
                    <input
                      type="text"
                      class="form-control form-control-sm"
                      placeholder="By Project And User"
                      name="search"
                      autocomplete="off"
                      v-model="searchTerm"
                      @keydown="searchProjects()" />
                  </div>
                </div>
              </div>
            </div>
            <div class="table-responsive">
              <div v-if="message" class="mt-3 text-center">
                <h4>{{ message }}</h4>
              </div>
              <table v-else class="table card-table table-vcenter text-nowrap datatable">
                <thead>
                  <tr>
                    <th class="w-1">
                      <input
                        class="form-check-input m-0 align-middle"
                        type="checkbox"
                        aria-label="Select all invoices"
                        v-model="selectAll"
                        @change="selectAllProjects" />
                    </th>
                    <th class="w-1">
                      No.
                      <i
                        v-if="currentSort === 'asc' || currentSort === ''"
                        class="fa-solid fa-angle-up angle-pointer"
                        @click="toggleSort('asc')"></i>

                      <i
                        v-if="currentSort === 'desc'"
                        class="fa-solid fa-angle-down angle-pointer"
                        @click="toggleSort('desc')"></i>
                    </th>
                    <th>Name</th>
                    <th>Desc</th>
                    <th>Score</th>
                    <th>Stage</th>
                    <th>State</th>
                    <th>Owner</th>
                    <th>Created_at</th>
                    <th>Tasks_count</th>
                    <th>Members_count</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="project in projects.data" :key="project.id">
                    <td>
                      <input
                        class="form-check-input m-0 align-middle"
                        type="checkbox"
                        aria-label="Select invoice"
                        v-model="selectedProjects"
                        :value="project.id" />
                    </td>
                    <td>{{ project.id }}</td>
                    <td>
                      <router-link
                        :to="{ name: 'ProjectPage', params: { slug: project.slug } }"
                        class="admin-panel-link">
                        <span>{{ project.name }}</span>
                      </router-link>
                    </td>
                    <td>{{ project.about }}</td>
                    <td>
                      {{ project.score }} /
                      <span v-if="project.status == 'cold'" class="badge bg-info">{{ project.status }}</span>
                      <span v-else class="badge bg-danger">{{ project.status }}</span>
                    </td>
                    <td v-if="!project.stage">Clo/Pos</td>
                    <td v-else>
                      {{ project.stage.name }}
                    </td>
                    <td>
                      <span v-if="project.state == 'active'" class="bg badge bg-success me-1">{{ project.state }}</span>
                      <span v-else class="bg badge bg-warning me-1">{{ project.state }}</span>
                    </td>
                    <td>
                      <router-link
                        :to="{ name: 'Profile', params: { uuid: project.owner.id } }"
                        class="admin-panel-link">
                        <div>{{ project.owner.name }}</div>
                        <div>({{ project.owner.username }})</div>
                      </router-link>
                    </td>
                    <td><span class="badge bg-success me-1"></span> {{ project.created_at }}</td>
                    <td>{{ project.tasks_count }}</td>
                    <td>{{ project.members_count }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div class="card-footer d-flex">
              <p class="float-left">
                Showing <span>{{ from }}</span> to {{ to }}<span></span> of <span></span>{{ total }} entries
              </p>
              <pagination :data="projects" @pagination-change-page="getResults"></pagination>
            </div>
          </div>
        </div>
      </div>

      <div class="card mt-5">
        <div class="card-header">
          <h3 class="card-title">Chats</h3>
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
      projects: [],
      stages: [],
      selectedProjects: [],
      selectAll: false,
      appliedFilters: [],
      totalProjects: 0,
      currentSort: 'asc',
      from: 0,
      to: 0,
      total: 0,
      searchTerm: '',
      isPop: false,
      message: '',
      form: {
        projects: '',
        activeTasks: '',
        hasMembers: '',
        status: '',
        startdate: '',
        enddate: '',
        stage: '',
      },
    };
  },
  watch: {
    isPop(isPop) {
      if (isPop) {
        document.addEventListener('click', (event) =>
          this.$options.methods.handleClickOutside.call(this, event, '.filter-dropdown', this.isPop),
        );
      }
    },
  },
  mounted() {
    this.getResults();
    this.loadStages();
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
    loadStages() {
      axios
        .get('/stages')
        .then((response) => {
          this.stages = response.data;
        })
        .catch((error) => {
          this.handleErrorResponse(error);
        });
    },

    toggle(fieldName, selectedValue) {
      if (this.form[fieldName] === selectedValue) {
        this.form[fieldName] = null;
      } else {
        this.form[fieldName] = selectedValue;
      }
    },

    toggleRadio(selectedValue) {
      this.toggle('projects', selectedValue);
    },

    toggleStatus(selectedValue) {
      this.toggle('status', selectedValue);
    },

    toggleStage(selectedValue) {
      this.toggle('stage', selectedValue);
    },
    selectAllProjects() {
      if (this.selectAll) {
        this.selectedProjects = this.projects.data.map((project) => project.id);
      } else {
        this.selectedProjects = [];
      }
    },

    getResults(page = 1) {
      const queryParameters = {
        page: page,
        search: this.searchTerm,
        sort: this.currentSort,
        filter: this.form.projects,
        members: this.form.hasMembers ? 'true' : undefined,
        status: this.form.status,
        tasks: this.form.activeTasks ? 'true' : undefined,
        stage: this.form.stage,
        from: this.form.startdate,
        to: this.form.enddate,
      };

      const filteredParameters = Object.fromEntries(
        Object.entries(queryParameters).filter(([, value]) => value !== undefined && value !== ''),
      );

      axios
        .get(`/admin/projects`, {
          params: filteredParameters,
        })
        .then((response) => {
          this.projects = response.data.projects;
          this.from = this.projects.meta.from || '';
          this.to = this.projects.meta.to || '';
          this.total = this.projects.meta.total || '';

          this.appliedFilters = response.data.appliedFilters;
          this.message = response.data.projects ? '' : response.data.message;
        })
        .catch((error) => {
          this.handleErrorResponse(error);
        });
    },

    toggleSort(order) {
      this.currentSort = this.currentSort === order ? (order === 'asc' ? 'desc' : 'asc') : order;
      this.getResults();
    },

    filterBy() {
      this.getResults();
      this.isPop = false;
    },
    bulkDelete() {
      if (!this.guardAdminMutation()) {
        return;
      }

      if (this.selectedProjects.length === 0) {
        return;
      }
      this.sweetAlert('Yes, Delete Selected ' + this.selectedProjects.length + ' Projects!').then((result) => {
        if (result.value) {
          axios
            .delete('/admin/projects/bulk-delete', {
              data: { project_ids: this.selectedProjects },
            })
            .then((response) => {
              this.$vToastify.success(response.data.message);
              this.getResults();
            })
            .catch((error) => {
              this.handleErrorResponse(error);
            });
        }
        this.selectedProjects = [];
        this.selectAll = false;
      });
    },
    searchProjects: debounce(function () {
      this.getResults();
    }, 1000),
  },
};
</script>
