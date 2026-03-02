export default {
  methods: {
    handleErrorResponse(error) {
      const response = error?.response;
      const data = response?.data;

      // Log structured error details only in development to avoid leaking information in production.
      if (import.meta?.env?.DEV) {
        console.debug('API error response', {
          status: response?.status,
          message: data?.message,
          error: data?.error,
          errors: data?.errors,
        });
      }

      if (data?.errors) {
        if (this.errors !== undefined) {
          this.errors = data.errors;
        }

        const firstValidationMessage = Object.values(data.errors)
          .flat()
          .find((message) => typeof message === 'string' && message.trim() !== '');

        if (firstValidationMessage) {
          this.$vToastify.error(firstValidationMessage);
          return;
        }

        if (data?.message) {
          this.$vToastify.error(data.message);
          return;
        }

        return;
      }

      if (data?.error) {
        this.$vToastify.error(data.error);
        return;
      }

      if (data?.message) {
        this.$vToastify.error(data.message);
        return;
      }

      this.$vToastify.error('An unexpected error occurred.');
    },
  },
};
