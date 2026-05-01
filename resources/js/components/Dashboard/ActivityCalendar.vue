<template>
  <div class="m-3">
    <div class="calendar-wrapper" style="position: relative">
      <vc-calendar is-expanded :attributes="attributes" v-model="currentMonth" @update:from-page="onPageChange">
        <template slot="day-popover" slot-scope="{ dayTitle, attributes: attrs }">
          <div class="p-2 user-popover-container">
            <div class="text-muted small mb-1">{{ dayTitle }}</div>

            <!-- Loop over each activity attribute -->
            <ul class="user-popover-list">
              <li
                v-for="attr in attrs"
                :key="attr.key || (attr.customData && attr.customData.id) || Math.random()"
                class="d-flex align-items-center mb-1">
                <!-- Smaller, lighter dot -->
                <span class="user-activity-dot mr-2" :style="{ backgroundColor: attr.customData.color }"></span>

                <!-- Styled project link -->
                <router-link
                  v-if="attr.customData?.project?.slug"
                  :to="`/projects/${attr.customData.project.slug}`"
                  class="user-activity-link">
                  {{ attr.customData?.description || 'Activity' }}
                </router-link>

                <div class="user-popover-date small pl-2 text-muted">
                  {{ attr.customData?.created_at | time }}
                </div>
              </li>
            </ul>
          </div>
        </template>
      </vc-calendar>
      <!-- Spinner Overlay -->
      <div v-if="isLoading" class="calendar-loader" role="status" aria-live="polite">
        <div class="loader-backdrop"></div>
        <div class="loader-content">
          <i class="fa-solid fa-spinner fa-pulse loader-icon" aria-hidden="true"></i>
          <div class="loader-text">Loading activities...</div>
        </div>
      </div>

      <!-- Error state -->
      <div v-if="errorMessage" class="calendar-error alert alert-danger" role="alert">
        {{ errorMessage }}
      </div>
    </div>
  </div>
</template>

<script>
import VCalendar from 'v-calendar';
import moment from 'moment';
import { toDateInUserTimezone } from '../../utils/dateTime';

export default {
  name: 'ActivityCalendar',
  components: {
    'vc-calendar': VCalendar.Calendar,
  },
  data() {
    return {
      activities: [],
      currentMonth: moment().toDate(),
      cancelTokenSource: null,
      isLoading: false,
      errorMessage: null,
    };
  },
  computed: {
    attributes() {
      return this.activities
        .map((activity) => ({
          dates: activity.created_at ? toDateInUserTimezone(activity.created_at) : null,
          bar: { color: activity.color },
          popover: true,
          customData: activity,
        }))
        .filter((attr) => attr.dates !== null);
    },
  },
  mounted() {
    this.calendarData();
  },
  beforeDestroy() {
    if (this.cancelTokenSource) {
      try {
        this.cancelTokenSource.cancel('Component unmounted');
      } catch (err) {
        console.error('Cancel token error', err);
      }
    }
  },
  methods: {
    onPageChange(page) {
      // Accepts: {month, year}, [from,to], Date or ISO string
      let ref;
      if (page && page.month && page.year) {
        ref = moment(`${page.year}-${page.month}-01`);
      } else if (Array.isArray(page) && page.length) {
        ref = moment(page[0]);
      } else {
        ref = moment(page);
      }

      if (!ref || !ref.isValid()) return;

      const start_date = ref.clone().startOf('month').format('YYYY-MM-DD');
      const end_date = ref.clone().endOf('month').format('YYYY-MM-DD');

      this.calendarData({ start_date, end_date });
    },

    calendarData({ start_date, end_date } = {}) {
      if (this.cancelTokenSource) {
        this.cancelTokenSource.cancel('New request triggered, cancelling old one.');
      }
      this.cancelTokenSource = axios.CancelToken.source();
      this.isLoading = true;

      if (!start_date || !end_date) {
        const ref = moment(this.currentMonth);
        start_date = ref.clone().startOf('month').format('YYYY-MM-DD');
        end_date = ref.clone().endOf('month').format('YYYY-MM-DD');
      }

      axios
        .get('/dashboard/activities', {
          params: { start_date, end_date },
          cancelToken: this.cancelTokenSource.token,
        })
        .then((response) => {
          this.activities = response.data && response.data.data ? response.data.data : response.data;
          this.errorMessage = null;
          this.$emit('loaded', this.activities);
        })
        .catch((error) => {
          if (axios.isCancel(error)) {
            // canceled
          } else {
            this.handleErrorResponse(error);
            this.activities = [];
            this.errorMessage = 'Unable to load activities. Please try again later.';
          }
        })
        .finally(() => {
          this.isLoading = false;
        });
    },
  },
};
</script>
