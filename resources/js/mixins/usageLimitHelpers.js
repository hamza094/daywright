export default {
  methods: {
    usageLimitIsOver(limit) {
      if (!limit || limit.max === null || limit.max <= 0) {
        return false;
      }

      return limit.used > limit.max;
    },

    /**
     * Returns the percentage of limit used (0-100+).
     * Returns 0 for unlimited plans (max === null) since there's no cap to measure against.
     */
    usageLimitRatio(limit) {
      if (!limit || limit.max === null || limit.max <= 0) {
        return 0;
      }

      return (limit.used / limit.max) * 100;
    },

    /**
     * Returns CSS width for progress bar.
     * Returns '100%' for unlimited plans to display a full healthy bar.
     */
    usageLimitWidth(limit) {
      if (!limit || limit.max === null || limit.max <= 0) {
        return '100%';
      }

      return `${Math.min(this.usageLimitRatio(limit), 100)}%`;
    },

    usageLimitToneClass(limit, classPrefix) {
      if (!limit || limit.max === null) {
        return `${classPrefix}-healthy`;
      }

      if (this.usageLimitIsOver(limit)) {
        return `${classPrefix}-over-limit`;
      }

      const ratio = this.usageLimitRatio(limit);

      if (ratio >= 90) {
        return `${classPrefix}-critical`;
      }

      if (ratio >= 70) {
        return `${classPrefix}-warning`;
      }

      return `${classPrefix}-healthy`;
    },

    usageLimitStatusLabel(limit, unlimitedLabel = 'Unlimited') {
      if (!limit || limit.max === null) {
        return unlimitedLabel;
      }

      if (this.usageLimitIsOver(limit)) {
        return 'Over limit';
      }

      return `${Math.min(Math.round(this.usageLimitRatio(limit)), 100)}% used`;
    },

    formatUsageLimit(limit, options = {}) {
      const { emptyUsed = 0, emptyMax = 'Unlimited', unlimitedLabel = 'Unlimited' } = options;
      const used = typeof limit?.used === 'number' ? limit.used : emptyUsed;

      if (!limit) {
        return `${used} / ${emptyMax}`;
      }

      if (limit.max === null) {
        return `${used} / ${unlimitedLabel}`;
      }

      return `${used} / ${limit.max}`;
    },
  },
};
