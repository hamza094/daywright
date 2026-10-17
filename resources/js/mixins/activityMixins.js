export default {
  data() {
    return {
      activityTypes: [
        { status: 'all', label: 'All Activities', icon: 'fa-solid fa-layer-group', color: 'main', filter: null },
        { status: 'my', label: 'My Activities', icon: 'fa-solid fa-user', color: 'purple', filter: { type: 'mine' } },
        {
          status: 'project',
          label: 'Project Activities',
          icon: 'fa-regular fa-star',
          color: 'green',
          filter: { type: 'specifics' },
        },
        {
          status: 'task',
          label: 'Task Activities',
          icon: 'fa-solid fa-tasks',
          color: 'primary',
          filter: { type: 'tasks' },
        },
        {
          status: 'member',
          label: 'Member Activities',
          icon: 'fa-solid fa-users',
          color: 'danger',
          filter: { type: 'members' },
        },
      ],
      activityData: {
        Task: {
          icon: 'fa-solid fa-tasks',
          color: 'activity-icon_primary',
        },
        'Project invitation': {
          icon: 'fa-solid fa-user',
          color: 'activity-icon_green',
        },
        'Project member': {
          icon: 'fa-solid fa-user',
          color: 'activity-icon_green',
        },
      },
    };
  },
  methods: {
    getIcon(description) {
      const prefix = Object.keys(this.activityData).find((prefix) => description.startsWith(prefix)) || 'default';

      return this.activityData[prefix]?.icon || 'fa-brands fa-pagelines';
    },

    getColor(description) {
      const prefix = Object.keys(this.activityData).find((prefix) => description.startsWith(prefix)) || 'default';

      return this.activityData[prefix]?.color || 'activity-icon_purple';
    },
  },
};
