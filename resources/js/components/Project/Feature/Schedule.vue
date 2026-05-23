<template>
  <div>
    <a class="btn btn-link" @click.prevent="modalShow()"><i class="fa-regular fa-clock"></i></a>
    <modal
      name="view-schedules"
      height="auto"
      :scrollable="true"
      class="modal-design"
      :click-to-close="false"
      width="75%">
      <div class="edit-border-top p-3">
        <div class="edit-border-bottom">
          <div class="panel-top_content">
            <span class="panel-heading">Scheduled messages</span>
            <span class="panel-exit float-right" role="button" @click.prevent="modalClose">x</span>
          </div>
        </div>

        <div class="panel-top_content">
          <h2 v-if="messages.length === 0">Sorry no scheduled messages found</h2>

          <table v-else class="table table-bordered">
            <thead>
              <tr>
                <th scope="col">Type</th>
                <th scope="col">Message</th>
                <th scope="col">To</th>
                <th scope="col">Scheduled At</th>
                <th scope="col">Created At</th>
                <th scope="col">Delete</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="message in messages" :key="message.id">
                <td>{{ message.type }}</td>
                <td>{{ message.message }}</td>
                <td>
                  <span v-for="user in message.users" :key="user.id || (user.pivot && user.pivot.id) || user.name">
                    <router-link
                      :to="{ name: 'Profile', params: { uuid: user.uuid || user.id || (user.pivot && user.pivot.id) } }"
                      class="btn btn-link">
                      {{ user.name }} </router-link
                    ><br />
                  </span>
                </td>
                <td>{{ message.delivered_at | datetime }}</td>
                <td>{{ message.created_at | datetime }}</td>
                <td>
                  <a class="btn btn-danger" @click="remove(message.id)"><i class="fa-solid fa-minus-circle"></i></a>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="panel-bottom">
          <div class="panel-top_content float-right">
            <button class="btn panel-btn_close" @click.prevent="modalClose">Cancel</button>
          </div>
        </div>
      </div>
    </modal>
  </div>
</template>

<script>
import { getPaginatedData } from '../../../utils/apiResponse.js';

export default {
  props: {
    slug: { type: String, required: true },
  },
  data() {
    return {
      messages: [],
      form: {},

      errors: {},
    };
  },
  created() {
    this.scheduledMessages();
  },
  methods: {
    modalShow() {
      this.$modal.show('view-schedules');
    },
    scheduledMessages() {
      axios
        .get('/projects/' + this.slug + '/messages/scheduled')
        .then((response) => {
          this.messages = getPaginatedData(response).data;
        })
        .catch((error) => {
          this.handleErrorResponse(error);
        });
    },
    remove(id) {
      axios
        .delete('/projects/' + this.slug + '/messages/' + id)
        .then(() => {
          this.scheduledMessages();
        })
        .catch((error) => {
          this.handleErrorResponse(error);
        });
    },
    modalClose() {
      this.$modal.hide('view-schedules');
    },
  },
};
</script>
