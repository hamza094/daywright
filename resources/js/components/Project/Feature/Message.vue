<template>
  <div>
    <!-- Message Modal-->
    <modal
      name="project-message"
      height="auto"
      :scrollable="true"
      width="45%"
      class="modal-design"
      :click-to-close="false">
      <div class="edit-border-top p-3">
        <div class="edit-border-bottom">
          <div class="panel-top_content">
            <span class="panel-heading">Send message to members</span>
            <span class="panel-exit float-right" role="button" @click.prevent="modalClose">x</span>
          </div>
        </div>
        <SubscriptionCheck>
          <div class="panel-form">
            <form class="" @submit.prevent="sendMessage()">
              <div class="panel-top_content">
                <div class="form-group">
                  <label for="message" class="label-name">Subject:</label>
                  <input
                    type="text"
                    id="subject"
                    class="form-control"
                    name="subject"
                    v-model="form.subject"
                    :readonly="!form.mail" />
                  <p class="text-danger" v-if="errors.subject">*{{ errors.subject[0] }}</p>
                </div>

                <div class="form-group">
                  <label for="subject" class="label-name">Message:</label>
                  <textarea name="message" class="form-control" rows="5" v-model="form.message"></textarea>
                  <p class="text-danger" v-if="errors.message">*{{ errors.message[0] }}</p>
                </div>

                <div class="form-group">
                  <div class="form-check form-check-inline">
                    <input class="form-check-input" type="checkbox" id="mailCheckbox" name="mail" v-model="form.mail" />
                    <label class="form-check-label" for="mailCheckbox">Send Mail</label>
                  </div>

                  <div class="form-check form-check-inline">
                    <input class="form-check-input" type="checkbox" id="smsCheckbox" name="sms" v-model="form.sms" />
                    <label class="form-check-label" for="smsCheckbox">Send Sms</label>
                  </div>
                  <p class="text-danger" v-if="errors.option">*{{ errors.option[0] }}</p>
                </div>

                <div class="form-group">
                  <label for="to" class="label-name mt-2">To: Select Project Member</label>
                  <div class="check_members">
                    <div class="form-check" v-for="user in members" :key="user.id">
                      <input
                        v-if="user.id !== auth.id"
                        class="form-check-input"
                        type="checkbox"
                        v-model="form.users"
                        :value="user"
                        :id="'checkUser-' + user.id" />
                      <label v-if="user.id !== auth.id" class="form-check-label" :for="'checkUser-' + user.id">
                        {{ user.name }} ({{ user.email }})
                      </label>
                    </div>
                  </div>
                  <p class="text-danger" v-if="errors.users">*{{ errors.users[0] }}</p>
                </div>

                <span class="text-muted" v-if="messageButton() === 'Schedule'"
                  ><i class="fa-regular fa-calendar-alt"></i> Message will send on {{ form.scheduled_at }}
                </span>
              </div>

              <div class="panel-bottom">
                <div class="panel-top_content float-left">
                  <ScheduleMessages :slug="slug"></ScheduleMessages>
                </div>
                <div class="panel-top_content float-right">
                  <a class="btn btn-link" @click="$modal.show('schedule-message')"
                    ><i class="fa-regular fa-calendar-alt"></i
                  ></a>
                  <button class="btn panel-btn_close" @click.prevent="modalClose">Cancel</button>
                  <button class="btn panel-btn_save" type="submit">{{ messageButton() }}</button>
                </div>
              </div>
            </form>
          </div>
        </SubscriptionCheck>
      </div>
    </modal>

    <!-- Schedule Modal-->
    <div>
      <modal
        name="schedule-message"
        height="auto"
        :scrollable="true"
        width="45%"
        class="modal-design"
        :click-to-close="false">
        <div class="edit-border-top p-3">
          <div class="edit-border-bottom">
            <div class="panel-top_content">
              <span class="panel-heading">Schedule message</span>
              <span class="panel-exit float-right" role="button" @click.prevent="modalFalse">x</span>
            </div>
          </div>
          <div class="panel-form">
            <form class="">
              <div class="panel-top_content">
                <span class="form-inline"
                  ><h6>Date:</h6>
                  <span> </span>
                  <datetime
                    v-model="model.date"
                    type="date"
                    :zone="displayTimezone"
                    :value-zone="displayTimezone"
                    format="yyyy-MM-dd"></datetime>
                  <span>: Message Schedule To </span>
                </span>

                <span class="form-inline">
                  <h6>Time:</h6>
                  <datetime
                    type="time"
                    v-model="model.time"
                    :value-zone="displayTimezone"
                    :zone="displayTimezone"></datetime>
                </span>
              </div>

              <div class="panel-bottom">
                <div class="panel-top_content float-right">
                  <button class="btn panel-btn_close" @click.prevent="modalFalse">Cancel</button>
                  <button class="btn panel-btn_save" role="button" @click.prevent="scheduled()">Confirm</button>
                </div>
              </div>
            </form>
          </div>
        </div>
      </modal>
    </div>
  </div>
</template>

<script>
import ScheduleMessages from './Schedule.vue';
import SubscriptionCheck from '../../SubscriptionChecker.vue';
import { createIdempotentRequest } from '../../../services/IdempotencyRequestService';
import {
  combineDateAndTimeToUtcIso,
  formatInUserTimezone,
  getDisplayTimezone,
  isFutureDateTime,
} from '../../../utils/dateTime';

export default {
  components: { ScheduleMessages, SubscriptionCheck },

  props: {
    slug: { type: String, required: true },
    members: { type: Array, default: () => [] },
  },
  data() {
    return {
      auth: this.$store.state.currentUser.user,
      buttonMessage: 'Send',
      form: {
        delivered_at: '',
        message: '',
        subject: '',
        mail: '',
        sms: '',
        users: [],
        scheduled_at: '',
      },
      model: {
        date: '',
        time: '',
      },
      errors: {},
    };
  },
  computed: {
    displayTimezone() {
      return getDisplayTimezone();
    },
  },
  created() {
    this.sendMessageRequest = createIdempotentRequest();
  },

  beforeDestroy() {
    this.sendMessageRequest?.reset();
  },

  methods: {
    sendMessage() {
      const payload = {
        mail: this.form.mail,
        sms: this.form.sms,
        subject: this.form.subject,
        message: this.form.message,
        users: JSON.stringify(this.form.users),
        delivered_at: this.form.delivered_at,
      };

      this.sendMessageRequest
        .post('/projects/' + this.slug + '/message', payload)
        .then(() => {
          this.$vToastify.success(
            this.form.delivered_at ? 'Message Scheduled Successfully' : 'Message Sent Successfully',
          );
          this.modalClose();
        })
        .catch((error) => {
          this.errors = error?.response?.data?.errors || {};
          this.handleErrorResponse(error);
        });
    },

    scheduled() {
      const deliveredAt = this.validateScheduled();

      if (!deliveredAt) {
        return;
      }

      this.form.delivered_at = deliveredAt;
      this.form.scheduled_at = this.scheduledTime(deliveredAt);
      this.$modal.hide('schedule-message');
    },

    validateScheduled() {
      if (!this.model.date || !this.model.time) {
        this.$vToastify.warning('Please select date and time');
        return '';
      }

      const deliveredAt = combineDateAndTimeToUtcIso(this.model.date, this.model.time, this.displayTimezone);

      if (!deliveredAt) {
        this.$vToastify.warning('Please select a valid schedule');
        return '';
      }

      if (!isFutureDateTime(deliveredAt)) {
        this.$vToastify.warning('Scheduled time must be in the future');
        return '';
      }

      return deliveredAt;
    },

    scheduledTime(deliveredAt = this.form.delivered_at) {
      return formatInUserTimezone(deliveredAt, 'MMM Do YY [at] h:mm:ss a');
    },

    messageButton() {
      if (this.form.delivered_at) {
        return 'Schedule';
      }
      return 'Send';
    },

    modalClose() {
      this.$modal.hide('project-message');
      this.sendMessageRequest.reset();
      this.errors = '';
      this.form = {
        delivered_at: '',
        message: '',
        subject: '',
        mail: '',
        sms: '',
        users: [],
        scheduled_at: '',
      };
      this.model = {
        date: '',
        time: '',
      };
    },
    modalFalse() {
      this.$modal.hide('schedule-message');
      this.form.delivered_at = '';
      this.form.scheduled_at = '';
      this.model.date = '';
      this.model.time = '';
    },
  },
};
</script>
