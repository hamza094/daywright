<template>
  <div>
    <button class="btn btn-outline-primary w-100 btn-sm" @click.prevent="modalStage()">View</button>

    <modal name="stage-modal" height="auto" :scrollable="true" width="40%" :click-to-close="false">
      <div class="container m-2">
        <h3>Stage Panel</h3>
        <div class="mb-2 mt-3">
          <form>
            <div class="form-group row">
              <label for="colFormLabel" class="col-sm-3 col-form-label">Add Stage</label>
              <div class="col-sm-6">
                <input
                  type="text"
                  class="form-control"
                  id="colFormLabel"
                  placeholder="Stage Name"
                  v-model="form.name"
                  @keypress.enter.prevent="addStage" />
              </div>
            </div>
          </form>
        </div>
        <div class="card">
          <div class="list-group list-group-flush">
            <a class="list-group-item list-group-item-action mb-2" v-for="stage in stages" :key="stage.id">
              <span v-if="edit && editStageId === stage.id">
                <input type="text" class="form-control mb-2" v-model="form.updateName" />
                <button class="btn btn-success btn-sm" @click="updateStage(stage)">Update</button>
                <button class="btn btn-secondary btn-sm" @click="cancelEdit">Cancel</button>
              </span>
              <span v-else
                >{{ stage.name }}
                <span class="float-right">
                  <button class="btn btn-link btn-sm" @click.prevent="editStage(stage)">Edit</button>
                  <button class="btn btn-sm btn-danger" @click.prevent="deleteStage(stage.id)">x</button>
                </span>
              </span>
            </a>
          </div>
        </div>
        <button class="btn btn-primary float-right mb-2 mt-3" @click="modalClose()">Modal Close</button>
      </div>
    </modal>
  </div>
</template>

<script>
import { mapState, mapMutations, mapActions } from 'vuex';

export default {
  data() {
    return {
      edit: false,
      editStageId: null,
      form: {
        name: '',
        updateName: '',
      },
    };
  },
  computed: {
    ...mapState('stage', ['stages']),
  },
  mounted() {
    this.loadStages();
  },
  methods: {
    ...mapActions('stage', ['loadStages', 'addNewStage']),
    ...mapMutations('stage', ['stageUpdate', 'stageDelete']),
    canMutateAdmin() {
      const user = this.$store.state.currentUser.user || {};

      return !!user.isAdmin && !!user.twoFactorEnabled;
    },
    guardAdminMutation() {
      if (this.canMutateAdmin()) {
        return true;
      }

      this.$vToastify.error(
        'Two-factor authentication is required for admin changes. Enable it from your profile settings to continue.',
      );

      return false;
    },
    modalStage() {
      this.$modal.show('stage-modal');
    },
    modalClose() {
      this.$modal.hide('stage-modal');
    },
    editStage(stage) {
      this.edit = true;
      this.editStageId = stage.id;
      this.form.updateName = stage.name;
    },
    cancelEdit() {
      this.edit = false;
      this.editStageId = null;
    },

    async addStage() {
      if (!this.guardAdminMutation()) {
        return;
      }

      try {
        await this.addNewStage({ name: this.form.name });
        this.form.name = '';
        this.$vToastify.success('Stage added successfully');
      } catch (error) {
        this.handleErrorResponse(error);
        this.form.name = '';
      }
    },

    updateStage(stage) {
      if (!this.guardAdminMutation()) {
        return;
      }

      axios
        .put('/admin/stages/' + stage.id, {
          name: this.form.updateName,
        })
        .then((response) => {
          this.stageUpdate(response.data.stage);
          this.edit = false;
          this.editStageId = null;
          this.$vToastify.success(response.data.message);
        })
        .catch((error) => {
          this.handleErrorResponse(error);
        });
    },
    deleteStage(stageId) {
      if (!this.guardAdminMutation()) {
        return;
      }

      axios
        .delete('/admin/stages/' + stageId, {
          name: this.form.name,
        })
        .then((response) => {
          this.stageDelete(stageId);
          this.$vToastify.success(response.data.success);
        })
        .catch((error) => {
          this.handleErrorResponse(error);
        });
    },
  },
};
</script>
