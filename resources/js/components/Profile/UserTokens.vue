<template>
  <div class="card mt-4">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span><i class="fa-solid fa-key"></i> API Tokens</span>
      <button class="btn btn-sm btn-success" @click="showCreate = !showCreate">
        <i class="fa-solid fa-plus"></i> New Token
      </button>
    </div>
    <div class="card-body">
      <!-- Create Token Form -->
      <div v-if="showCreate" class="mb-4">
        <form @submit.prevent="createToken">
          <div class="form-row align-items-end">
            <div class="form-group col-md-4">
              <label for="tokenName">Token Name</label>
              <input
                type="text"
                class="form-control"
                id="tokenName"
                v-model="form.name"
                required
                placeholder="Token name" />
            </div>
            <div class="form-group col-md-4">
              <label for="expiresAt">Expires In</label>
              <select class="form-control" id="expiresAt" v-model="form.expires_in">
                <option :value="null">Never</option>
                <option v-for="option in expiryOptions" :key="option.value" :value="option.value">
                  {{ option.label }}
                </option>
              </select>
            </div>
            <div class="form-group col-md-4 d-flex align-items-end">
              <button type="submit" class="btn btn-primary mr-2">Create</button>
              <button type="button" class="btn btn-link text-danger" @click="showCreate = false">Cancel</button>
            </div>
          </div>
        </form>
        <div v-if="newToken" class="alert alert-success mt-3 d-flex align-items-center">
          <span class="mr-2 font-weight-bold">New Token:</span>
          <input
            :type="showTokenMap[newTokenId] ? 'text' : 'password'"
            class="form-control form-control-sm w-auto d-inline-block mr-2"
            :value="newToken"
            readonly
            style="max-width: 300px" />
          <button class="btn btn-sm btn-outline-secondary mr-2" @click="toggleShowToken(newTokenId)">
            <i :class="showTokenMap[newTokenId] ? 'fa-solid fa-eye-slash' : 'fa-solid fa-eye'"></i>
          </button>
          <button class="btn btn-sm btn-outline-primary" @click="copyToken(newToken)">
            <i class="fa-solid fa-copy"></i>
          </button>
        </div>
      </div>

      <!-- Token List -->
      <div v-if="loading" class="text-center my-4">
        <div class="spinner-border text-primary" role="status">
          <span class="sr-only">Loading...</span>
        </div>
      </div>
      <div v-else>
        <div class="table-responsive table-responsive-md">
          <table class="table table-bordered table-hover">
            <thead class="thead-light">
              <tr>
                <th>Name</th>
                <th>Created</th>
                <th>Last Used</th>
                <th>Expires</th>
                <th>Token Value</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="token in tokens" :key="token.id">
                <td>{{ token.name }}</td>
                <td>{{ token.created_at }}</td>
                <td>{{ token.last_used_at ? token.last_used_at : 'Never' }}</td>
                <td>{{ token.expires_at ? token.expires_at : 'Never' }}</td>
                <td>
                  <input
                    :type="showTokenMap[token.id] ? 'text' : 'password'"
                    class="form-control form-control-sm w-auto d-inline-block mr-2"
                    :value="token.id === newTokenId ? newToken : 'Token value not available'"
                    readonly
                    style="max-width: 300px" />
                  <button class="btn btn-sm btn-outline-secondary mr-2" @click="toggleShowToken(token.id)">
                    <i :class="showTokenMap[token.id] ? 'fa-solid fa-eye-slash' : 'fa-solid fa-eye'"></i>
                  </button>
                  <button
                    class="btn btn-sm btn-outline-primary"
                    :disabled="token.id !== newTokenId"
                    @click="copyToken(token.id === newTokenId ? newToken : '')">
                    <i class="fa-solid fa-copy"></i>
                  </button>
                </td>
                <td>
                  <button class="btn btn-sm btn-danger" @click="deleteToken(token.id)">
                    <i class="fa-solid fa-trash"></i> Delete
                  </button>
                </td>
              </tr>
              <tr v-if="tokens.length === 0">
                <td colspan="6" class="text-center">No tokens found.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'UserTokens',
  data() {
    return {
      tokens: [],
      loading: false,
      showCreate: false,
      form: {
        name: '',
        expires_in: null, // in days
      },
      expiryOptions: [
        { label: '1 Day', value: 1 },
        { label: '7 Days', value: 7 },
        { label: '30 Days', value: 30 },
        { label: '90 Days', value: 90 },
        { label: '180 Days', value: 180 },
      ],
      newToken: '',
      newTokenId: null,
      showTokenMap: {},
    };
  },
  computed: {
    auth() {
      return this.$store.state.currentUser.user;
    },
  },
  mounted() {
    this.loadTokens();
  },
  methods: {
    toggleShowToken(tokenId) {
      this.$set(this.showTokenMap, tokenId, !this.showTokenMap[tokenId]);
    },
    copyToken(tokenValue) {
      if (!tokenValue) return;
      navigator.clipboard.writeText(tokenValue).then(() => {
        this.$vToastify.success('Token copied to clipboard!');
      });
    },
    loadTokens() {
      this.loading = true;
      axios
        .get('/api-tokens')
        .then((res) => {
          this.tokens = res.data.tokens;
        })
        .catch(() => {
          this.$vToastify.error('Failed to load tokens.');
        })
        .finally(() => {
          this.loading = false;
        });
    },
    createToken() {
      if (!this.form.name) return;
      this.$Progress.start();
      let payload = { name: this.form.name };
      if (this.form.expires_in) {
        // Set expires_at as ISO string (now + days)
        const expires = new Date();
        expires.setDate(expires.getDate() + Number(this.form.expires_in));
        payload.expires_at = expires.toISOString().slice(0, 19).replace('T', ' ');
      }
      axios
        .post('/api-tokens', payload)
        .then((res) => {
          this.$vToastify.success(res.data.message || 'Token created.');
          this.newToken = res.data.token;
          this.newTokenId = res.data.token_resource.id;
          this.showTokenMap = { [this.newTokenId]: false };
          this.form.name = '';
          this.form.expires_in = null;
          this.loadTokens();
        })
        .catch((err) => {
          this.$vToastify.error(err.response?.data?.message || 'Failed to create token.');
        })
        .finally(() => {
          this.$Progress.finish();
        });
    },
    deleteToken(id) {
      this.sweetAlert('Yes, delete this token!').then((result) => {
        if (result.value) {
          this.$Progress.start();
          axios
            .delete(`/api-tokens/${id}`)
            .then((res) => {
              this.$vToastify.success(res.data.message || 'Token deleted.');
              this.loadTokens();
            })
            .catch((err) => {
              this.$vToastify.error(err.response?.data?.message || 'Failed to delete token.');
            })
            .finally(() => {
              this.$Progress.finish();
            });
        }
      });
    },
    // formatDate removed: date formatting is now handled by backend
  },
};
</script>

<style scoped>
.card {
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}
.table th,
.table td {
  vertical-align: middle;
}
</style>
