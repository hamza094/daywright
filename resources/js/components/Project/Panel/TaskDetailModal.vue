<template>
  <div class="edit-border-top p-3 task-modal">
    <TopPanel :task="task" :state="state" :slug="slug" :errors="errors"> </TopPanel>

    <div class="panel-form mt-2">
      <div class="row">
        <div class="col-md-8">
          <div class="task-feature">
            <p>
              <small><b>Label</b> </small>:
              <span class="task-option_labels-component" :style="{ backgroundColor: task.status.color }">{{
                task.status.label
              }}</span>

              <small class="ml-2"><b>Members:</b></small>

              <span v-for="member in task.members" class="task-member-container" :key="member.id">
                <router-link
                  :to="`/user/${member.uuid}/profile`"
                  class="task-member mr-1"
                  target="_blank"
                  rel="noopener noreferrer">
                  <!-- Avatar Image -->
                  <img
                    v-if="member.avatar"
                    :src="$options.filters.safeUrl(member.avatar)"
                    :alt="member.name"
                    class="task-member_avatar" />

                  <span v-else class="task-member_name">
                    {{ member.name.charAt(0) }}
                  </span>
                </router-link>

                <span class="task-member-username">
                  <!-- {{member.username}} -->

                  <span class="unassign-cross" @click="unassignMember(task.id, member.id)">&times;</span>
                </span>
              </span>
            </p>
            <span class="text-danger font-italic" v-if="errors?.member" v-text="errors?.member"></span>

            <p v-if="task.due_at">
              <small><b>Task due: </b> </small> {{ task.due_at }} {{ auth.timezone }}
            </p>

            <p v-if="task.notified">
              <small><b>Notified: </b> </small>{{ task.notified }}
            </p>

            <p v-if="task.due_at">
              <small><b>Days Left: </b>{{ remainingTime }} </small>
            </p>

            <p>
              <small><b>Task Created At:</b> {{ task.created_at }}</small>
            </p>

            <p v-if="task.updated_at">
              <small><b>Task Updated At:</b> {{ task.updated_at }}</small>
            </p>
          </div>

          <TaskDescription :task="task" :slug="slug" :errors="errors"> </TaskDescription>
        </div>

        <div class="col-md-4">
          <div class="task-option">
            <span class="text-center ml-4"><b>Options</b></span>
            <h5 class="text-center">Change Label</h5>

            <ul class="task-option_labels">
              <li v-for="status in statuses" :key="status.id">
                <p
                  class="task-option_labels-component"
                  @click="changeStatus(status.id, task.id)"
                  :style="{ backgroundColor: status.color }">
                  {{ status.label }}
                  <span v-if="task.status_id === status.id">
                    <i class="fa-solid fa-check-circle" style="color: #2a971c"></i>
                  </span>
                </p>
              </li>
            </ul>

            <ul class="task-option_features">
              <li>
                <button
                  class="btn btn-sm btn-outline-primary btn-block member-dropdown"
                  @click.prevent="toggleMemberPop">
                  <i class="fa-solid fa-user-alt pr-1"></i> <b>Members</b>
                </button>

                <TaskMembers :slug="slug" :task-id="task.id" v-show="memberPop"></TaskMembers>
              </li>

              <li>
                <button class="btn btn-sm btn btn-sm btn-outline-success btn-block" @click.prevent="datePop = !datePop">
                  <i class="fa-solid fa-clock pr-1"></i><b>Due Date</b>
                </button>
                <div class="member-dropdown_item" v-show="datePop">
                  <span
                    >Due Date:
                    <datetime
                      type="datetime"
                      v-model="form.due_at"
                      value-zone="local"
                      zone="local"
                      :min-datetime="modifiedDate"></datetime>
                  </span>

                  <select class="custom-select mr-sm-2" v-model="form.notified">
                    <option value="">Choose Option</option>
                    <option v-for="notify in due_notifies" :key="notify" :value="notify">{{ notify }}</option>
                  </select>

                  <span class="text-danger font-italic" v-if="errors?.notified" v-text="errors?.notified?.[0]"></span>

                  <div class="float-right mt-2">
                    <button class="btn btn-sm btn-secondary" @click.prevent="cancelDue()">Cancel</button>

                    <button class="btn btn-sm btn-primary ml-1" @click.prevent="taskDue(task.id)">Set</button>
                  </div>
                </div>
              </li>
              <li>
                <button
                  class="btn btn-sm btn btn-sm btn-outline-info btn-block"
                  @click.prevent="archive(task, task.id)"
                  v-if="state === 'active'">
                  <i class="fa-solid fa-ban pr-1"></i><b>Archive</b>
                </button>

                <button
                  v-else
                  class="btn btn-sm btn btn-sm btn-outline-secondary btn-block"
                  @click.prevent="unArchive(task, task.id)">
                  <i class="fa-solid fa-ban pr-1"></i><b>UnArchive</b>
                </button>
              </li>

              <li>
                <button
                  class="btn btn-sm btn btn-sm btn-outline-danger btn-block"
                  @click.prevent="trash(task.id)"
                  v-if="state === 'archived'">
                  <i class="fa-solid fa-trash-alt pr-1"></i><b> Delete</b>
                </button>
              </li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { calculateRemainingTime, url, ErrorHandling } from '../../../utils/TaskUtils';
import { mapMutations, mapActions, mapState } from 'vuex';
import TopPanel from './Modal/TopArea.vue';
import TaskDescription from './Modal/TaskDescription.vue';
import TaskMembers from './Modal/TaskMembers.vue';
import { modalClose } from '../../../mixins/modalClose';

export default {
  components: { TopPanel, TaskDescription, TaskMembers },

  props: {
    slug: {
      type: String,
      required: true,
    },
    state: {
      type: String,
      default: 'active',
    },
    projectMembers: {
      type: Array,
      default: () => [],
    },
  },
  data() {
    return {
      currentDate: new Date().toUTCString(),
      dateTime: new Date(),
      maxdateTime: null,
      memberPop: false,
      datePop: false,
      isEditable: false,
      due: '',
      model: {},
      auth: this.$store.state.currentUser.user,
    };
  },
  computed: {
    ...mapState('SingleTask', ['task', 'errors', 'form', 'statuses', 'due_notifies']),
    modifiedDate() {
      const modifiedDate = new Date(this.dateTime.getTime() + 30 * 60000);
      return modifiedDate.toISOString();
    },
    remainingTime() {
      return calculateRemainingTime(this.task, this.currentDate);
    },
  },
  created() {
    window.addEventListener('beforeunload', this.handleBeforeUnload);

    this.$bus.on('close-members-popup', () => {
      this.memberPop = false;
    });
  },

  beforeDestroy() {
    window.removeEventListener('beforeunload', this.handleBeforeUnload);
  },

  methods: {
    ...mapMutations('task', ['removeTaskFromState', 'pushArchivedTask', 'removeArchivedTask', 'updateTask']),

    ...mapMutations('SingleTask', ['setErrors', 'updateTaskStatus', 'updateTaskDue', 'unassignTaskMember', 'setForm']),

    ...mapActions({ fetchTasks: 'task/fetchTasks' }),

    changeStatus(statusId, id) {
      axios
        .put(url(this.slug, id), { status_id: statusId }, { useProgress: true })
        .then((response) => {
          this.$vToastify.success(response.data.message);
          this.setErrors([]);
          this.updateTaskStatus(response.data.task.status);
          this.updateTask(response.data.task);
        })
        .catch((error) => {
          ErrorHandling(this, error);
        });
    },

    taskDue(id) {
      axios
        .put(url(this.slug, id), { due_at: this.form.due_at, notified: this.form.notified }, { useProgress: true })
        .then((response) => {
          const taskData = response.data.task;
          this.$vToastify.success(response.data.message);
          this.setErrors([]);
          this.updateTaskDue({
            dueAt: taskData.due_at,
            notified: taskData.notified,
            dueAtUtc: taskData.due_at_utc,
          });
          this.cancelDue();
        })
        .catch((error) => {
          ErrorHandling(this, error);
        });
    },

    cancelDue() {
      this.datePop = false;
      this.form.notified = '';
      this.form.due_at = '';
      this.setErrors([]);
    },
    unassignMember(taskId, memberId) {
      axios
        .patch(
          url(this.slug, taskId) + '/unassign',
          {
            member: memberId,
          },
          { useProgress: true },
        )
        .then((response) => {
          this.unassignTaskMember(response.data.member.id);
          this.$vToastify.success(response.data.message);
          this.setErrors([]);
        })
        .catch((error) => {
          ErrorHandling(this, error);
        });
    },
    archive(task, taskId) {
      axios
        .delete(url(this.slug, taskId) + '/archive', { useProgress: true })
        .then((response) => {
          this.$vToastify.warning(response.data.message);
          this.removeTaskFromState(taskId);
          this.pushArchivedTask(task);
          this.$bus.emit('archiveTask', { taskId });
          modalClose(this);
        })
        .catch((error) => {
          ErrorHandling(this, error);
        });
    },
    unArchive(task, taskId) {
      axios
        .get(url(this.slug, taskId) + '/unarchive', { useProgress: true })
        .then((response) => {
          this.$vToastify.success(response.data.message);
          this.removeArchivedTask(taskId);
          this.fetchTasks({ slug: this.$route.params.slug, page: 1 });
          modalClose(this);
          this.$bus.emit('unarchiveTask', { task });
        })
        .catch((error) => {
          ErrorHandling(this, error);
        });
    },
    trash(taskId) {
      axios
        .delete(url(this.slug, taskId) + '/remove', { useProgress: true })
        .then(() => {
          this.$vToastify.success('Task deleted successfully');
          this.removeArchivedTask(taskId);
          modalClose(this);
        })
        .catch((error) => {
          ErrorHandling(this, error);
        });
    },
    toggleMemberPop() {
      this.memberPop = !this.memberPop;
      this.$bus.emit('toggleMember');
    },
    handleBeforeUnload() {
      modalClose(this);
    },
  },
};
</script>
