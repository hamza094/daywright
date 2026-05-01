<template>
  <div class="col-md-7 mb-5">
    <div class="activity">
      <ul>
        <li v-for="activity in activities" :key="activity.id">
          <span class="activity-icon" :class="activityColor(activity.description)">
            <i :class="activityIcon(activity.description)"></i
          ></span>
          {{ activity.description }}
          <p class="activity-info">
            <span v-text="activity.user.name"></span><span class="activity-info_dot"></span>
            <span>{{ activity.time | msgTime }}</span>
          </p>
        </li>

        <li>
          <span class="activity-more">
            <router-link :to="{ name: 'Activities', params: { name: name, slug: slug } }" class="dashboard-link"
              >View More</router-link
            >
          </span>
        </li>
      </ul>
    </div>
  </div>
</template>

<script>
import activityMixins from '../../mixins/activityMixins';

export default {
  mixins: [activityMixins],
  props: {
    activities: {
      type: Array,
      default: () => [],
    },
    slug: {
      type: String,
      required: true,
    },
    name: {
      type: String,
      required: true,
    },
  },
  data() {
    return {
      icon: '',
    };
  },
  methods: {
    // Fetch activities design from mixin
    activityIcon(description) {
      return this.getIcon(description);
    },

    activityColor(description) {
      return this.getColor(description);
    },
  },
};
</script>
