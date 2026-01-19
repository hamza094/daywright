<template>
  <div class="panel">
    <div class="panel-top">
      <div class="panel-top_content">
        <span class="panel-heading">{{ message }}</span>
        <span class="panel-exit float-right" role="button" @click.prevent="closePanel">x</span>
      </div>
    </div>
    <div class="panel-top_content">
      <div v-if="archivedTasks && archivedTasks.length > 0">
        <div v-for="task in archivedTasks" :key="task.id">
          <div class="card task-card_style" @click="openModal(task)">
            <div
              v-if="task.status"
              class="task-card_border"
              :style="{
                borderColor: task.status.color,
              }"></div>
            <div class="card-body task-card_body">
              <span>{{ task.title }}</span>
            </div>
          </div>
        </div>
      </div>
      <div v-else>
        <p>Sorry, no tasks found.</p>
      </div>
      <modal
        name="archive-task-modal"
        height="auto"
        :scrollable="true"
        width="65%"
        class="model-desin archive-modal"
        :click-to-close="false"
        @modal-closed="closeModal">
        <TaskDetailModal :slug="slug" :state="state" />
      </modal>
    </div>
  </div>
</template>

<script>
import TaskDetailModal from './TaskDetailModal.vue';
import { mapState, mapMutations, mapActions } from 'vuex';

export default {
  components: { TaskDetailModal },
  props: {
    slug: {
      type: String,
      required: true,
    },
  },
  data() {
    return {
      state: 'archived',
    };
  },
  computed: {
    ...mapState('task', ['archivedTasks', 'message']),
  },
  created() {
    const slug = this.$route.params.slug;
    this.loadArchiveTasks(slug);
  },
  methods: {
    ...mapActions('task', ['loadArchiveTasks']),

    ...mapMutations('SingleTask', ['setTask']),

    closePanel() {
      this.$emit('closePanel', {});
    },
    openModal(task) {
      this.setTask(task);
      this.$modal.show('archive-task-modal');
    },
    closeModal() {
      this.setTask([]);
    },
  },
};
</script>

<style scoped>
/* Your component-specific styles here */
</style>
