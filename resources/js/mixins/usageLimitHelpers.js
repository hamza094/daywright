export default {
  methods: {
    usageLimitRatio(limit) {
      if (!limit || limit.max === null || limit.max <= 0) {
        return 0;
      }

      return (limit.used / limit.max) * 100;
    },

    usageLimitWidth(limit) {
      if (!limit || limit.max === null || limit.max <= 0) {
        return '100%';
      }

      return `${Math.min(this.usageLimitRatio(limit), 100)}%`;
    },

    usageLimitToneClass(limit, classPrefix) {
      if (!limit || limit.max === null) {
        return `${classPrefix}--healthy`;
      }

      const ratio = this.usageLimitRatio(limit);

      if (ratio >= 90) {
        return `${classPrefix}--critical`;
      }

      if (ratio >= 70) {
        return `${classPrefix}--warning`;
      }

      return `${classPrefix}--healthy`;
    },

    usageLimitStatusLabel(limit, unlimitedLabel = 'Unlimited') {
      if (!limit || limit.max === null) {
        return unlimitedLabel;
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
