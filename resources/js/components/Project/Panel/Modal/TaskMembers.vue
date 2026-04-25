<template>
  <div class="member-dropdown_item">
    <p class="text-center m-1">
      <small><b>Assign Task To Member</b></small>
    </p>

    <input
      type="text"
      placeholder="Search members by name or username"
      class="form-control"
      v-model="form.search"
      name="member"
      autocomplete="off" />

    <div v-if="hasError('members')">
      <span class="text-danger font-italic" v-for="error in getErrors('members')" :key="error">*{{ error }}</span>
    </div>

    <div v-if="hasError('members.0')">
      <span class="text-danger font-italic" v-for="error in getErrors('members.0')" :key="error">*{{ error }}</span>
    </div>

    <div class="member-list" v-if="searchResults.length > 0 && form.search">
      <div v-for="member in searchResults" :key="member.id" class="member-list_items">
        <div @click.prevent="addMember(member)">{{ member.name }} ({{ member.username }})</div>
      </div>
    </div>

    <button class="mt-2 btn btn-sm btn-primary float-right" @click="assignMembers(task.id)">Assign</button>

    <div v-if="taskMembers.length > 0" class="mt-3" style="height: 70px; width: 150px; overflow-y: scroll">
      <div v-for="member in taskMembers" :key="member.id || member.username">
        <span
          >{{ member.username }}
          <span @click.prevent="removeMember(member)"><i class="fa-solid fa-minus-circle"></i></span>
        </span>
      </div>
    </div>
  </div>
</template>

<script type="text/javascript">
import { mapMutations, mapState } from 'vuex';
import { url } from '../../../../utils/TaskUtils';
import { debounce } from 'lodash';

export default {
  props: {
    slug: {
      type: String,
      required: true,
    },
    taskId: {
      type: [String, Number],
      required: true,
    },
  },
  data() {
    return {
      searchResults: [],
      taskMembers: [],
    };
  },
  computed: {
    ...mapState('SingleTask', ['form', 'errors', 'task']),
  },
  watch: {
    'form.search': debounce(function (newSearch) {
      this.performSearch(newSearch);
    }, 500),
  },
  created() {
    this.$bus.on('toggleMember', () => {
      this.taskMembers = [];
      this.setErrors([]);
      this.form.search = '';
    });
  },
  methods: {
    ...mapMutations('SingleTask', ['setErrors', 'updateTaskMembers']),

    performSearch(searchTerm) {
      const normalizedSearch = searchTerm.trim().replace(/\s+/g, ' ');

      if (normalizedSearch.length < 2) {
        this.searchResults = [];
        this.setErrors([]);
        return;
      }

      axios
        .get(`/projects/${this.slug}/tasks/${this.taskId}/member/search`, {
          params: { query: normalizedSearch },
        })
        .then((response) => {
          this.searchResults = response.data;
        })
        .catch((error) => {
          this.handleErrorResponse(error);
        });
    },
    addMember(member) {
      const memberExists = this.taskMembers.some((m) => m.id === member.id);

      if (memberExists) {
        return this.$vToastify.warning('Member already listed');
      }

      this.taskMembers.push(member);
      this.searchResults = [];
      this.form.search = '';
    },

    removeMember(member) {
      this.taskMembers = this.taskMembers.filter((m) => m !== member);
    },

    assignMembers(taskId) {
      if (!this.taskMembers.length) {
        return this.$vToastify.info('no member is selected to assign task');
      }

      const memberIds = this.taskMembers.map((member) => member.id);

      axios
        .patch(
          url(this.slug, taskId) + '/assign',
          {
            members: memberIds,
          },
          { useProgress: true },
        )
        .then((response) => {
          this.assignSuccessfull(response);
        })
        .catch((error) => {
          this.handleErrorResponse(error);
          this.setErrors(error?.response?.data?.errors || {});
        });
    },

    assignSuccessfull(response) {
      this.taskMembers = [];
      this.setErrors([]);
      this.updateTaskMembers(response.data.taskMembers);
      this.$bus.emit('close-members-popup');
      this.$vToastify.success(response.data.message);
    },

    getErrors(key) {
      if (this.hasError(key)) {
        return this.errors[key];
      }
      return [];
    },
    hasError(key) {
      return !!(this.errors && typeof this.errors === 'object' && Object.hasOwn(this.errors, key));
    },
  },
};
</script>
