<template>
  <div>
    <div v-if="!isSubscribed" class="alert alert-dark mt-2" role="alert">
      <b>Only subscribed users can access this feature.</b>
    </div>
    <slot v-else></slot>
  </div>
</template>

<script>
import { mapGetters } from 'vuex';

export default {
  name: 'SubscriptionChecker',
  computed: {
    ...mapGetters('subscribeUser', ['isPro']),

    isSubscribed() {
      return this.isPro;
    },
  },
};
</script>
