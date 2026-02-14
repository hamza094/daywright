<template>
  <div>
    <div class="edit-border-bottom">
      <div class="task-modal_content">
        <span v-if="editing === task.id">
          <input class="title-form form-control" name="title" v-model="form.title" v-text="task.title" />

          <span class="btn btn-link btn-sm" @click="updateTitle(task.id)">Update</span>

          <span class="btn btn-link btn-sm" @click="closeTitleForm(task.id, task)">Cancel</span>
        </span>

        <span v-else class="task-modal_title" @click="openTitleForm(task.id, task)">{{ task.title }}</span>

        <span class="task-modal_close float-right" role="button" @click.prevent="modalClose">x</span>
      </div>

      <span class="text-danger font-italic" v-if="errors?.title" v-text="errors?.title?.[0]"></span>
    </div>

    <div v-if="state === 'archived'" class="alert alert-warning" role="alert">
      Please note that this task is currently archived. Currently, you can only delete or unarchive this task.
    </div>
  </div>
</template>

<script>
import { mapMutations, mapState } from 'vuex';
import { url } from '../../../../utils/TaskUtils';
import { modalClose } from '../../../../mixins/modalClose';

export default {
  props: {
    task: {
      type: Object,
      required: true,
    },
    state: {
      type: String,
      default: '',
    },
    slug: {
      type: String,
      required: true,
    },
    errors: {
      type: Object,
      default: () => ({}),
    },
  },

  data() {
    return {
      editing: 0,
    };
  },
  computed: {
    ...mapState('SingleTask', ['form']),
  },
  created() {},
  methods: {
    ...mapMutations('SingleTask', ['setErrors', 'updateTaskTitle', 'setForm']),

    ...mapMutations('task', ['updateTask']),

    updateTitle(id) {
      if (this.form.title === this.task.title) {
        return this.$vToastify.warning('No changes made.');
      }

      axios
        .put(url(this.slug, id), { title: this.form.title }, { useProgress: true })
        .then((response) => {
          this.$vToastify.success(response.data.message);
          this.editing = false;
          this.setErrors([]);
          this.updateTaskTitle(response.data.task.title);
          this.updateTask(response.data.task);
        })
        .catch((error) => {
          this.handleErrorResponse(error);
          this.setErrors(error?.response?.data?.errors || {});
        });
    },

    closeTitleForm(id, task) {
      this.editing = false;
      this.form.title = task.title;
      this.setErrors('');
    },

    openTitleForm(id, task) {
      this.editing = id;
      this.form.title = task.title;
    },

    modalClose() {
      modalClose(this);
    },
  },
};
</script>
