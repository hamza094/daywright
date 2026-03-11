<template>
  <div class="error-img d-flex flex-column align-items-center justify-content-center text-center p-3">
    <h1 class="fs-1 fw-bold mb-3">The page you're looking for can't be found</h1>
    <p class="lead mb-3">Check the URL or return to the dashboard.</p>
    <img class="img-fluid my-3" :src="'img/404.png'" alt="404" />
    <router-link to="/dashboard" class="btn btn-primary mt-3">Go to Dashboard</router-link>
  </div>
</template>

<script>
export default {
  data() {
    return {};
  },
  methods: {},
};
</script>

<style scoped>
.error-img {
  min-height: 60vh;
}

.error-img img {
  max-width: 640px;
}
</style>
