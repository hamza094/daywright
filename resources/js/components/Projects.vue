<template>
  <div class="projects-container">
    <!-- Page Header -->
    <div class="page-top margin-small">My Projects</div>

    <!-- Tab Navigation -->
    <div class="container-fluid mt-4 ml-2">
      <div class="row">
        <div class="col-12">
          <ul class="nav nav-tabs" id="projectTabs" role="tablist">
            <li v-for="tab in tabs" :key="tab.key" class="nav-item" role="presentation">
              <button
                class="nav-link"
                :class="{ active: currentTab === tab.key }"
                :id="`${tab.key}-tab`"
                type="button"
                role="tab"
                @click="switchTab(tab.key)">
                <i :class="`fa-solid ${tab.icon} me-2`"></i>
                {{ tab.label }}
                <span :class="`badge badge-count bg-${tab.badge} ms-2`">
                  {{ tabData[tab.key].count }}
                </span>
              </button>
            </li>
          </ul>
        </div>
      </div>
    </div>

    <!-- Tab Content -->
    <div class="tab-content mt-4" id="projectTabContent">
      <div class="tab-pane fade show active" role="tabpanel">
        <div class="container-fluid">
          <div class="row">
            <div class="col-12">
              <!-- Search and Filter Section -->
              <SearchFilterSection
                :current-tab-config="currentTabConfig"
                :search-query="searchQuery"
                :sort-by="sortBy"
                @search-changed="handleSearchChange"
                @sort-changed="handleSortChange" />

              <!-- Loading State -->
              <div v-if="loading" class="loading-state">
                <div class="text-center py-5">
                  <div :class="`spinner-border text-${currentTabConfig.color}`" role="status">
                    <span class="visually-hidden">Loading...</span>
                  </div>
                  <p class="mt-3 text-muted">Loading {{ currentTabConfig.label.toLowerCase() }}...</p>
                </div>
              </div>

              <!-- Projects Grid -->
              <ProjectsGrid
                v-else-if="currentList.length > 0"
                :projects="currentList"
                :current-tab="currentTab"
                :current-tab-config="currentTabConfig" />

              <!-- Empty State -->
              <div v-else class="empty-state">
                <div class="empty-state-content">
                  <i :class="`fa-solid ${currentTabConfig.emptyIcon} empty-icon`"></i>
                  <h4 class="empty-title">{{ currentTabConfig.emptyTitle }}</h4>
                  <p class="empty-text">{{ currentTabConfig.emptyText }}</p>
                  <div class="empty-actions" v-if="currentTab === 'active'">
                    <button @click.prevent="showPanel" class="btn btn-primary">
                      <i class="fa-solid fa-plus me-2"></i>
                      Create Your First Project
                    </button>
                  </div>
                </div>
              </div>

              <!-- Pagination -->
              <div v-if="currentPagination && currentList.length > 0" class="pagination-container">
                <pagination
                  :data="currentPagination"
                  @pagination-change-page="handlePagination"
                  class="justify-content-center" />
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import Pagination from 'laravel-vue-pagination';
import SearchFilterSection from './Projects/SearchFilterSection.vue';
import ProjectsGrid from './Projects/ProjectsGrid.vue';

/**
 * Tab configuration for different project types
 */
const TAB_CONFIG = [
  {
    key: 'active',
    label: 'Active Projects',
    icon: 'fa-play-circle',
    badge: 'primary',
    color: 'primary',
    emptyIcon: 'fa-folder-open',
    emptyTitle: 'No active projects found',
    emptyText: 'Start by creating your first project!',
    extraParam: null,
  },
  {
    key: 'invited',
    label: 'Invited Projects',
    icon: 'fa-user-plus',
    badge: 'success',
    color: 'success',
    emptyIcon: 'fa-user-plus',
    emptyTitle: 'No invited projects',
    emptyText: "You haven't been invited to any projects yet.",
    extraParam: { member: true },
  },
  {
    key: 'trashed',
    label: 'Trashed Projects',
    icon: 'fa-trash',
    badge: 'danger',
    color: 'danger',
    emptyIcon: 'fa-trash',
    emptyTitle: 'No trashed projects',
    emptyText: 'No projects have been moved to trash.',
    extraParam: { abandoned: true },
  },
];

/**
 * Initial tab data structure
 */
const INITIAL_TAB_DATA = {
  active: { list: [], count: 0, pagination: null },
  invited: { list: [], count: 0, pagination: null },
  trashed: { list: [], count: 0, pagination: null },
};

export default {
  name: 'Projects',

  components: {
    Pagination,
    SearchFilterSection,
    ProjectsGrid,
  },

  data() {
    return {
      currentTab: 'active',
      searchQuery: '',
      sortBy: 'latest',
      loading: false,
      searchTimeout: null,
      tabData: { ...INITIAL_TAB_DATA },
    };
  },

  computed: {
    /**
     * Available tabs configuration
     */
    tabs() {
      return TAB_CONFIG;
    },

    /**
     * Current tab configuration
     */
    currentTabConfig() {
      return this.tabs.find((tab) => tab.key === this.currentTab);
    },

    /**
     * Current tab's project list
     */
    currentList() {
      return this.tabData[this.currentTab].list;
    },

    /**
     * Current tab's project count
     */
    currentCount() {
      return this.tabData[this.currentTab].count;
    },

    /**
     * Current tab's pagination data
     */
    currentPagination() {
      return this.tabData[this.currentTab].pagination;
    },
  },

  mounted() {
    this.fetchProjects('active');
  },

  methods: {
    /**
     * Switch to a different tab
     * @param {string} tab - Tab key to switch to
     */
    switchTab(tab) {
      this.currentTab = tab;
      this.fetchProjects(tab);
    },

    /**
     * Fetch projects for a specific tab
     * @param {string} type - Tab type (active, invited, trashed)
     * @param {number} page - Page number for pagination
     */
    async fetchProjects(type = this.currentTab, page = 1) {
      this.loading = true;

      try {
        const params = this.buildRequestParams(type, page);
        const response = await this.makeApiRequest(params);
        this.handleApiSuccess(response, type);
      } catch (error) {
        this.handleApiError(error, type);
      } finally {
        this.loading = false;
      }
    },

    /**
     * Build request parameters for API call
     * @param {string} type - Tab type
     * @param {number} page - Page number
     * @returns {Object} Request parameters
     */
    buildRequestParams(type, page) {
      const params = {
        page,
        search: this.searchQuery,
        sort: this.sortBy,
      };

      const extra = this.tabs.find((t) => t.key === type).extraParam;
      if (extra) {
        Object.assign(params, extra);
      }

      return params;
    },

    /**
     * Make API request to fetch projects
     * @param {Object} params - Request parameters
     * @returns {Promise} API response
     */
    async makeApiRequest(params) {
      return axios.get('/user/projects', { params });
    },

    /**
     * Handle successful API response
     * @param {Object} response - API response
     * @param {string} type - Tab type
     */
    handleApiSuccess(response, type) {
      const data = response.data;
      this.tabData[type].list = data.projects.data;
      this.tabData[type].count = data.projectsCount;
      this.tabData[type].pagination = data.projects;
    },

    /**
     * Handle API error
     * @param {Error} error - API error
     * @param {string} type - Tab type
     */
    handleApiError(error, type) {
      this.$vToastify.error(`Failed to load ${type} projects`);
      console.error('API Error:', error);
    },

    /**
     * Handle search query changes with debouncing
     */
    handleSearchChange(query) {
      this.searchQuery = query;
      this.debounceSearch();
    },

    /**
     * Handle sort selection changes
     */
    handleSortChange(sortBy) {
      this.sortBy = sortBy;
      this.fetchProjects(this.currentTab);
    },

    /**
     * Debounce search to avoid excessive API calls
     */
    debounceSearch() {
      clearTimeout(this.searchTimeout);
      this.searchTimeout = setTimeout(() => {
        this.fetchProjects(this.currentTab);
      }, 500);
    },

    /**
     * Handle pagination changes
     * @param {number} page - Page number
     */
    handlePagination(page) {
      this.fetchProjects(this.currentTab, page);
    },

    showPanel() {
      const panel1Handle = this.$showPanel({
        component: 'project-form',
        openOn: 'right',
        width: 540,
        disableBgClick: true,
        keepAlive: true,
        props: {
          //any data you want passed to your component
        },
      });

      panel1Handle.promise.then(() => {});
    },
  },
};
</script>
