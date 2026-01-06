<template>
  <div class="search-filter-container">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
      <h5 class="mb-0 ml-4">
        <i :class="`fa-solid ${currentTabConfig.icon} text-${currentTabConfig.color} me-2`"></i>
        {{ currentTabConfig.label }}
      </h5>
      <div class="margin-small d-flex flex-column flex-sm-row gap-2 w-md-auto">
        <div class="position-relative flex-grow-1 flex-sm-grow-0">
          <input
            type="text"
            class="form-control"
            placeholder="Search projects..."
            :value="searchQuery"
            @input="handleSearchInput" />
        </div>
        <select class="form-select form-select-sm" :value="sortBy" @change="handleSortChange">
          <option value="latest">Latest</option>
          <option value="oldest">Oldest</option>
          <option value="name">Name A-Z</option>
        </select>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'SearchFilterSection',

  props: {
    currentTabConfig: {
      type: Object,
      required: true,
    },
    searchQuery: {
      type: String,
      default: '',
    },
    sortBy: {
      type: String,
      default: 'latest',
    },
  },

  methods: {
    /**
     * Handle search input changes
     * @param {Event} event - Input event
     */
    handleSearchInput(event) {
      this.$emit('search-changed', event.target.value);
    },

    /**
     * Handle sort selection changes
     * @param {Event} event - Change event
     */
    handleSortChange(event) {
      this.$emit('sort-changed', event.target.value);
    },
  },
};
</script>
