<template>
  <section class="task">
    <header class="task-top">
      <span>
        <i class="fa-solid fa-tasks"></i> <b>Tasks</b>
        <button
          type="button"
          class="btn btn-link p-0"
          data-toggle="collapse"
          data-target="#taskProject"
          aria-expanded="false"
          aria-controls="taskProject">
          <i class="fa-solid fa-angle-down"></i>
        </button>
      </span>
    </header>

    <!--Task Section-->
    <section class="collapse" id="taskProject">
      <SubscriptionCheck>
        <article class="card card-body">
          <div v-if="!access">Only the project owner and members are allowed to access this feature.</div>
          <div v-if="access">
            <!-- Add new Task -->
            <div class="task-add">
              <form class="" @submit.prevent="add">
                <div class="form-group">
                  <label for="body"><i>Create a New Task</i></label>
                  <input type="text" class="form-control" name="title" v-model="form.title" />
                </div>
              </form>
            </div>

            <section v-if="tasks" class="task-list" aria-label="Task list">
              <div class="task-list_header">
                <p class="task-list_heading mb-0">Project Tasks</p>
                <button
                  type="button"
                  class="task-archive-btn"
                  @click.prevent="archiveTasks"
                  title="View archived tasks">
                  <i class="fa-solid fa-box-archive" aria-hidden="true"></i>
                  <span class="task-archive-label d-none d-sm-inline ml-1">Archived</span>
                </button>
              </div>

              <!-- Tasks Lists -->
              <ul class="task-list_items" role="list">
                <li v-for="task in tasks.data" :key="task.id" class="task-list_item">
                  <article class="card task-card_style" @click="openModal(task)">
                    <div
                      v-if="task.status"
                      class="task-card_border"
                      :style="{
                        borderColor: task.status.color,
                      }"></div>
                    <div class="card-body task-card_body">
                      <span>{{ task.title }}</span>
                      <span class="float-right mt-4"
                        ><small><i class="fa-regular fa-clock"> </i> {{ task.created_at | shortDate }}</small></span
                      >
                    </div>
                  </article>
                </li>
              </ul>

              <!-- Task Modal -->
              <modal
                name="task-modal"
                height="auto"
                :scrollable="true"
                width="65%"
                class="model-desin"
                :click-to-close="false"
                @modal-closed="closeModal">
                <TaskDetailModal :slug="slug" :state="state"></TaskDetailModal>
              </modal>

              <pagination :data="tasks" @pagination-change-page="getResults"></pagination>
            </section>
          </div>
        </article>
      </SubscriptionCheck>
    </section>
  </section>
</template>
<script>
import TaskDetailModal from './TaskDetailModal.vue';
import { mapMutations, mapActions, mapState } from 'vuex';
import SubscriptionCheck from '../../SubscriptionChecker.vue';
export default {
  components: { TaskDetailModal, SubscriptionCheck },
  props: {
    slug: {
      type: String,
      required: true,
    },
    access: {
      type: Boolean,
      default: false,
    },
  },
  data() {
    return {
      task_score: 2,
      state: 'active',
      form: {
        title: '',
      },
      errors: {},
    };
  },
  computed: {
    ...mapState('task', ['tasks', 'message']),
  },
  created() {
    this.getResults(1);
    this.loadStatuses();
  },
  methods: {
    ...mapActions({
      fetchTasks: 'task/fetchTasks',
      loadStatuses: 'SingleTask/loadStatuses',
    }),

    ...mapMutations('project', ['addScore', 'reduceScore']),
    ...mapMutations('SingleTask', ['setTask']),

    getResults(page) {
      const slug = this.$route.params.slug;
      this.fetchTasks({ slug, page });
    },

    archiveTasks() {
      const panel1Handle = this.$showPanel({
        component: 'archive-tasks',
        openOn: 'right',
        width: 440,
        disableBgClick: true,
        keepAlive: true,
        props: {
          slug: this.slug,
        },
      });
      panel1Handle.promise.then(() => {});
    },

    openModal(task) {
      //this.$Progress.start();
      axios
        .get('/projects/' + this.slug + '/tasks/' + task.id, { useProgress: true })
        .then((response) => {
          //this.$Progress.finish();
          this.setTask(response.data);
          this.$modal.show('task-modal');
        })
        .catch(() => {
          //this.$Progress.fail();
        });
    },

    add() {
      axios
        .post('/projects/' + this.slug + '/tasks', this.form, { useProgress: true })
        .then(() => {
          this.$vToastify.success('Project Task added');
          this.form.title = '';
          this.getResults(1);
          this.addScore(this.task_score);
        })
        .catch((error) => {
          this.form.title = '';
          this.$vToastify.warning(error.response.data.message);
        });
    },

    closeModal() {
      this.setTask([]);
    },
  },
};
</script>
