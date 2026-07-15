<template>
  <div>
    <p v-if="showIntro">
      <b><i>Start Group chat with project Members</i></b>
    </p>

    <div class="card chat-card mb-5">
      <div class="card-header d-flex align-items-center justify-content-between" id="accordion">
        <div class="d-flex align-items-center gap-2">
          <i class="fa-solid chat-logo fa-comment-alt mr-2"></i>
          <div class="chat-header_text">
            <div class="chat-header_sub">Stay in sync with your team</div>
          </div>
        </div>

        <div class="d-flex align-items-center gap-2 chat-header_meta">
          <span class="badge badge-light">{{ conversationCount }} messages</span>
          <span class="badge badge-secondary">{{ participantCount }} members</span>
        </div>
      </div>

      <div class="chat-wrapper">
        <div class="card-body chat-panel">
          <ul class="chat">
            <div ref="scrollSentinel"></div>
            <div v-if="loadingMore" class="text-center py-2">
              <small>Loading older messages...</small>
            </div>
            <ChatMessageItem
              v-for="conversation in conversations.data"
              :key="conversation.id || conversation.created_at"
              :conversation="conversation"
              :auth="auth"
              :is-open="openMenuId === (conversation.id || conversation.created_at)"
              @toggle-menu="toggleMenu"
              @delete="handleDelete" />
            <div v-if="typing" class="chat-typing">
              <span class="chat-typing_icon">💬</span>
              <span class="chat-typing_text">@{{ (user && user.name) || 'Someone' }} is typing...</span>
            </div>
            <div v-else class="chat-typing chat-typing_idle">
              <span class="chat-typing_icon">💬</span>
              <span class="chat-typing_text">Waiting for new messages</span>
            </div>
          </ul>
        </div>

        <div class="card-footer gioj">
          <Mentionable :keys="['@']" :items="items" offset="6" insert-space @open="handleOpen" @apply="handleApply">
            <div class="chat-input position-relative mb-2">
              <textarea
                class="form-control"
                placeholder="Type your message here..."
                v-model="message"
                autofocus
                @keypress.enter.exact.prevent="send()"
                @keydown="isTyping"
                row="1">
              </textarea>
            </div>

            <div class="chat-actions d-flex align-items-center flex-wrap">
              <div class="d-flex align-items-center flex-wrap chat-actions_left">
                <button type="button" @click="openFilePicker" class="btn btn-light btn-icon chat-action">
                  <i class="fa-solid fa-paperclip"></i>
                </button>

                <div class="chat-emoji">
                  <button type="button" class="btn btn-light btn-icon chat-action" @click="toggleEmojiModal">
                    <i class="fa-regular fa-face-smile"></i>
                  </button>
                  <transition name="emoji-slide" mode="out-in">
                    <Picker
                      v-if="emojiModal"
                      :data="emojiIndex"
                      set="twitter"
                      @select="showEmoji"
                      title="Pick your emoji…"
                      class="emoji-modal"
                      :show-preview="false" />
                  </transition>
                </div>

                <div v-if="file" class="chat-file-chip ml-2 d-flex align-items-center">
                  <i class="fa-solid fa-file-alt mr-1"></i>
                  <span class="file-name mr-2">{{ fileName }}</span>
                  <button
                    type="button"
                    @click="removeFile"
                    class="btn btn-link p-0 file-close-btn"
                    aria-label="Remove file">
                    ✖
                  </button>
                </div>
              </div>

              <button
                class="btn btn-primary btn-sm ml-auto"
                id="btn-chat"
                :disabled="isSendDisabled"
                @click.prevent="send()">
                Send
              </button>

              <input
                type="file"
                ref="fileInput"
                class="d-none"
                accept=".jpg,.jpeg,.png,.pdf,.docx"
                @change="fileUpload" />
            </div>

            <template #no-result>
              <div class="dim">No result</div>
            </template>

            <template #[`item-@`]="{ item }">
              <div class="user">
                <img :src="item.avatar" alt="User Avatar" class="mention-user" />
                <span class="dim">{{ item.name }}</span>
                <span class="dim">({{ item.username }})</span>
              </div>
            </template>
          </Mentionable>
          <p class="d-none"></p>
        </div>
      </div>
    </div>
    <vue-progress-bar></vue-progress-bar>
  </div>
</template>
<script>
import { ref } from 'vue';
import { Mentionable } from 'vue-mention';
import { Picker } from 'emoji-mart-vue-fast';
import ChatMessageItem from './ChatMessageItem.vue';
import { useChatMessages } from '../../../composables/useChatMessages';
import { useFileUpload } from '../../../composables/useFileUpload';
import { useTypingIndicator } from '../../../composables/useTypingIndicator';
import { useEmojiPicker } from '../../../composables/useEmojiPicker';
import { useRealtimeChat } from '../../../composables/useRealtimeChat';

export default {
  components: { Picker, Mentionable, ChatMessageItem },
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
    startOpen: {
      type: Boolean,
      default: false,
    },
    showIntro: {
      type: Boolean,
      default: true,
    },
  },
  setup(props) {
    const {
      conversations,
      loadingMore,
      loadConversations,
      loadMoreConversations,
      setupScrollObserver,
      disconnectScrollObserver,
    } = useChatMessages(props.slug);
    const { file, fileName, fileUpload: composableFileUpload, removeFile } = useFileUpload(null);
    const { typing, user, isTyping, listenToWhisperEvent } = useTypingIndicator(props.slug, props.auth);
    const {
      emojiIndex,
      emojiModal,
      toggleEmojiModal: composableToggleEmojiModal,
      showEmoji: composableShowEmoji,
    } = useEmojiPicker();
    const { listenForNewMessage, listenToDeleteConversation } = useRealtimeChat(props.slug, conversations);

    const message = ref('');

    return {
      conversations,
      loadingMore,
      loadConversations,
      loadMoreConversations,
      setupScrollObserver,
      disconnectScrollObserver,
      file,
      fileName,
      composableFileUpload,
      removeFile,
      typing,
      user,
      isTyping,
      listenToWhisperEvent,
      emojiIndex,
      emojiModal,
      composableToggleEmojiModal,
      composableShowEmoji,
      listenForNewMessage,
      listenToDeleteConversation,
      message,
    };
  },

  data() {
    return {
      isSending: false,
      items: [],
      errors: [],
      users: [...this.members, this.owner],
      openMenuId: null,
    };
  },

  computed: {
    isSendDisabled() {
      return (this.message.trim().length === 0 && !this.file) || this.isSending;
    },
    conversationCount() {
      if (this.conversations && Array.isArray(this.conversations.data)) {
        return this.conversations.data.length;
      }
      return 0;
    },
    participantCount() {
      const memberCount = Array.isArray(this.members) ? this.members.length : 0;
      return (this.owner ? 1 : 0) + memberCount;
    },
  },

  created() {
    this.loadConversations();

    this.listenToWhisperEvent();

    this.listenForNewMessage();

    this.listenToDeleteConversation();
  },

  mounted() {
    this.setupScrollObserver(this.$refs.scrollSentinel, this.$el.querySelector('.chat-panel'));
  },

  beforeDestroy() {
    this.disconnectScrollObserver();
  },

  methods: {
    async handleOpen(key) {
      this.items = key === '@' ? this.users : [];
    },

    async handleApply(item) {
      this.message = `${this.message}@${item.username}`;
      this.message = this.message.replace('@undefined', '');
    },

    showEmoji(emoji) {
      this.composableShowEmoji(emoji, this.message);
    },

    openFilePicker() {
      this.$refs.fileInput.click();
    },

    fileUpload(event) {
      this.composableFileUpload(event, this.$refs.fileInput);
      if (this.file) {
        this.$vToastify.success('File attached');
      }
    },

    send() {
      if (this.isSendDisabled) {
        if (this.isSending) {
          return;
        }
        this.$vToastify.warning('Please enter a message or upload a file.');
        return;
      }

      this.isSending = true;
      let formData = new FormData();
      if (this.message) {
        formData.append('message', this.message);
      }

      if (this.file) {
        formData.append('file', this.file);
      }

      axios
        .post('/projects/' + this.slug + '/conversations', formData, { useProgress: true })
        .then(() => {
          this.message = '';
          this.removeFile();
        })
        .catch((error) => {
          this.handleErrorResponse(error);
        })
        .finally(() => {
          this.isSending = false;
        });
    },

    toggleMenu(id, val) {
      this.openMenuId = val ? id : null;
    },

    handleDelete(id) {
      this.toggleMenu(id, false);
      this.deleteConversation(id);
    },

    deleteConversation(id) {
      axios
        .delete('/projects/' + this.slug + '/conversations/' + id, { useProgress: true })
        .then(() => {
          this.$vToastify.info('Conversation deleted sucessfully');
        })
        .catch((error) => {
          this.handleErrorResponse(error);
        });
    },

    toggleEmojiModal() {
      this.composableToggleEmojiModal();
    },
  },
};
</script>
<style>
.mention-item {
  padding: 4px 10px;
  border-radius: 4px;
}

.mention-selected {
  background: rgb(192, 250, 153);
}
</style>
