<template>
  <div class="task-description">
    <p class="task-description_container">
      <span class="task-description_heading">Description:</span>
      <span class="text-danger font-italic" v-if="errors?.description" v-text="errors?.description?.[0]"></span>
    </p>

    <div v-if="edit === task.id">
      <vue-editor name="description" v-model="form.description" :editor-toolbar="customToolbar"></vue-editor>

      <span class="btn btn-link btn-sm" @click="updateDescription(task.id)">Update</span>

      <span class="btn btn-link btn-sm" @click="closeDescriptionForm(task.id, task)">Cancel</span>
    </div>
    <div v-else>
      <div v-if="task.description" class="task-description_content-link" @click="openDescriptionForm(task.id, task)">
        <div v-safe-html="task.description"></div>
      </div>

      <div v-else>
        <p class="task-description_content">
          Sorry! currently no task description present.
          <a class="task-description_content-link" @click="openDescriptionForm(task.id, task)"
            >Click here to add description</a
          >
        </p>
      </div>
    </div>
  </div>
</template>

<script>
import { VueEditor } from 'vue2-editor';
import { mapMutations, mapState } from 'vuex';
import { url } from '../../../../utils/TaskUtils';
import { getObjectData, parseApiError } from '../../../../utils/apiResponse.js';

export default {
  components: { VueEditor },
  props: {
    task: {
      type: Object,
      required: true,
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
      edit: 0,
      customToolbar: [
        ['bold', 'italic', 'underline'],
        [{ list: 'ordered' }, { list: 'bullet' }],
        [{ header: [1, 2, 3, 4, 5, 6, false] }],
        ['blockquote'],
        [{ size: ['small', false, 'large', 'huge'] }],
        ['link', 'unlink'],
      ],
    };
  },
  computed: {
    ...mapState('SingleTask', ['form']),
  },
  methods: {
    ...mapMutations('SingleTask', ['setErrors', 'updateTaskDescription']),

    updateDescription(id) {
      if (this.form.description === this.task.description) {
        return this.$vToastify.warning('No changes made.');
      }
      axios
        .put(url(this.slug, id), { description: this.form.description }, { useProgress: true })
        .then((response) => {
          const taskData = getObjectData(response);

          this.$vToastify.success('Task description updated.');
          this.edit = false;
          this.setErrors({});
          this.updateTaskDescription(taskData.description);
        })
        .catch((error) => {
          this.handleErrorResponse(error);
          this.setErrors(parseApiError(error).errors);
        });
    },

    closeDescriptionForm(id, task) {
      this.edit = false;
      this.form.description = task.description;
    },

    openDescriptionForm(id, task) {
      this.edit = id;
      this.form.description = task.description;
    },
  },
};
</script>
