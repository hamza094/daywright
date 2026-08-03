<template>
  <div class="card mt-4">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span><i class="fa-solid fa-key"></i> API Tokens</span>
      <button class="btn btn-sm btn-success" @click="toggleCreateForm">
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
              <button type="button" class="btn btn-link text-danger" @click="resetCreateTokenForm">Cancel</button>
            </div>
          </div>
          <div class="form-group mt-3">
            <label>Permissions (Scopes) <span class="text-danger">*</span></label>
            <div v-if="scopesLoading" class="text-center my-3">
              <div class="spinner-border spinner-border-sm text-primary" role="status">
                <span class="sr-only">Loading scopes...</span>
              </div>
            </div>
            <div v-else class="row">
              <div v-for="scope in scopeOptions" :key="scope.value" class="col-md-6 col-lg-4 mb-2">
                <div class="custom-control custom-checkbox">
                  <input
                    type="checkbox"
                    class="custom-control-input"
                    :id="`scope-${scope.value}`"
                    :value="scope.value"
                    v-model="form.scopes" />
                  <label class="custom-control-label" :for="`scope-${scope.value}`">
                    <div class="font-weight-bold">{{ scope.label }}</div>
                    <small class="text-muted">{{ scope.description }}</small>
                  </label>
                </div>
              </div>
            </div>
            <small v-if="!scopesLoading && form.scopes.length === 0" class="text-danger"
              >At least one scope must be selected</small
            >
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
                <th>Scopes</th>
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
                <td>
                  <span v-for="scope in token.abilities" :key="scope" class="badge badge-info mr-1">
                    {{ formatScopeLabel(scope) }}
                  </span>
                </td>
                <td>{{ token.created_at | datetime }}</td>
                <td>{{ token.last_used_at ? $options.filters.msgTime(token.last_used_at) : 'Never' }}</td>
                <td>{{ token.expires_at ? $options.filters.datetime(token.expires_at) : 'Never' }}</td>
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
                <td colspan="7" class="text-center">No tokens found.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { createIdempotentRequest } from '../../services/IdempotencyRequestService';
import { getArrayData, getObjectData, getResponseMessage } from '../../utils/apiResponse.js';

export default {
  name: 'UserTokens',
  data() {
    return {
      tokens: [],
      loading: false,
      showCreate: false,
      form: {
        name: '',
        scopes: [], // Array of selected scopes
        expires_in: null, // in days
      },
      expiryOptions: [
        { label: '1 Day', value: 1 },
        { label: '7 Days', value: 7 },
        { label: '30 Days', value: 30 },
        { label: '90 Days', value: 90 },
        { label: '180 Days', value: 180 },
      ],
      scopeOptions: [], // Fetched from API
      scopesLoading: false,
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
    this.createTokenRequest = createIdempotentRequest();
    this.loadScopes();
    this.loadTokens();
  },

  beforeDestroy() {
    this.createTokenRequest?.reset();
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
    loadScopes() {
      this.scopesLoading = true;
      axios
        .get('/scopes')
        .then((res) => {
          this.scopeOptions = getArrayData(res);
        })
        .catch((error) => {
          this.handleErrorResponse(error);
        })
        .finally(() => {
          this.scopesLoading = false;
        });
    },
    loadTokens() {
      this.loading = true;
      axios
        .get('/api-tokens')
        .then((res) => {
          this.tokens = getArrayData(res);
        })
        .catch((error) => {
          this.handleErrorResponse(error);
        })
        .finally(() => {
          this.loading = false;
        });
    },
    toggleCreateForm() {
      if (this.showCreate) {
        this.resetCreateTokenForm();
        return;
      }

      this.showCreate = true;
    },
    resetCreateTokenForm(hideForm = true) {
      this.showCreate = !hideForm;
      this.form.name = '';
      this.form.scopes = [];
      this.form.expires_in = null;
      this.createTokenRequest.reset();

      if (hideForm) {
        this.newToken = '';
        this.newTokenId = null;
        this.showTokenMap = {};
      }
    },
    createToken() {
      if (!this.form.name) return;
      if (this.form.scopes.length === 0) {
        this.$vToastify.error('At least one scope must be selected');
        return;
      }
      this.$Progress.start();
      let payload = {
        name: this.form.name,
        scopes: this.form.scopes,
      };
      if (this.form.expires_in) {
        const expires = new Date();
        expires.setDate(expires.getDate() + Number(this.form.expires_in));
        payload.expires_at = expires.toISOString();
      }
      this.createTokenRequest
        .post('/api-tokens', payload)
        .then((res) => {
          const data = getObjectData(res);

          this.$vToastify.success('Token created.');
          this.newToken = data.token || '';
          this.newTokenId = data.token_resource?.id || null;
          this.showTokenMap = this.newTokenId ? { [this.newTokenId]: false } : {};
          this.resetCreateTokenForm(false);
          this.loadTokens();
        })
        .catch((err) => {
          this.handleErrorResponse(err);
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
              this.$vToastify.success(getResponseMessage(res) || 'Token deleted.');
              this.loadTokens();
            })
            .catch((err) => {
              this.handleErrorResponse(err);
            })
            .finally(() => {
              this.$Progress.finish();
            });
        }
      });
    },
    formatScopeLabel(scope) {
      const scopeMap = {
        'projects:read': 'Projects Read',
        'projects:write': 'Projects Write',
        'team:read': 'Team Read',
        'team:write': 'Team Write',
        'account:read': 'Account Read',
        'account:write': 'Account Write',
        'webhooks:write': 'Webhooks Write',
      };
      return scopeMap[scope] || scope;
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
