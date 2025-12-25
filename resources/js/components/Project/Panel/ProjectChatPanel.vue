<template>
  <div class="p-3 project-chat-panel">
    <div class="d-flex align-items-center justify-content-between mb-3 panel-header">
      <h5 class="mb-0">Project Chat</h5>
      <button type="button" class="btn btn-light btn-sm" aria-label="Close" @click.prevent="close">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>

    <div class="mb-3">
      <p class="mb-2"><b>Online Users</b></p>
      <div v-if="onlineUsers.length" class="small">
        <div v-for="u in onlineUsers" :key="u.id">
          {{ u.name }} <span class="chat-circle"></span>
        </div>
      </div>
      <div v-else class="text-muted small">No one is currently online.</div>
    </div>

    <Chat :slug="slug" :members="members" :owner="owner" :auth="auth" :start-open="true" :collapsible="false" :show-intro="false" />
  </div>
</template>

<script>
import Chat from './Chat.vue';

export default {
  name: 'ProjectChatPanel',
  components: {
    Chat,
  },
  props: {
    slug: {
      type: String,
      required: true,
    },
    members: {
      type: Array,
      default: () => [],
    },
    owner: {
      type: Object,
      required: true,
    },
    auth: {
      type: Object,
      required: true,
    },
  },
  data() {
    return {
      onlineUsers: [],
    };
  },
  mounted() {
    this.joinPresence();
  },
  watch: {
    // If user navigates away (e.g. clicking a username in chat), close the slideout.
    $route() {
      this.close();
    },
  },
  beforeDestroy() {
    this.leavePresence();
  },
  methods: {
    close() {
      this.$emit('closePanel', {});
    },

    joinPresence() {
      if (!this.slug) return;

      Echo.join(`chatroom.${this.slug}`)
        .here((members) => {
          this.onlineUsers = members || [];
        })
        .joining((user) => {
          if (!this.onlineUsers.some((u) => u.id === user.id)) {
            this.onlineUsers = [...this.onlineUsers, user];
          }
        })
        .leaving((user) => {
          this.onlineUsers = this.onlineUsers.filter((u) => u.id !== user.id);
        });
    },

    leavePresence() {
      if (!this.slug) return;
      Echo.leave(`chatroom.${this.slug}`);
    },
  },
};
</script>
