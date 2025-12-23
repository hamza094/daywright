<template>
  <div>
    <p v-if="showIntro">
      <b><i>Start Group chat with project Members</i></b>
    </p>

    <SubscriptionCheck>
      <div class="card chat-card mb-5">
        <div class="card-header d-flex align-items-center justify-content-between bg-primary text-white" id="accordion">
          <div class="d-flex align-items-center">
            <i class="fa-solid fa-comment-alt mr-2"></i>
            <span>Group Chat</span>
            <span v-if="conversationCount" class="badge badge-light ml-2">{{ conversationCount }}</span>
            <span class="ml-1">messages</span>
          </div>

          <a
            v-if="collapsible"
            type="button"
            class="btn btn-default btn-xs float-right"
            data-toggle="collapse"
            :href="'#collapseOne-' + slug">
            <i class="fa-solid fa-angle-down"></i>
          </a>
        </div>

        <div v-if="collapsible" class="collapse" :class="{ show: startOpen }" :id="'collapseOne-' + slug">
          <div class="card-body chat-panel">
            <!-- Chat Message -->

            <ul class="chat">
              <li v-for="conversation in conversations.data" :key="conversation.id || conversation.created_at">
                <div class="chat-body clearfix">
                  <div class="header">
                    <router-link :to="'/user/' + conversation.user.name + '/profile'">
                      <img
                        v-if="conversation.user.avatar"
                        :src="$options.filters.safeUrl(conversation.user.avatar)"
                        alt="User Avatar"
                        class="chat-user_image" />
                    </router-link>

                    <strong class="primary-font"> {{ conversation.user.name }}</strong>
                  </div>
                  <p v-if="conversation.message" class="mt-2">
                    <span class="chat-message" v-text="conversation.message"></span>
                  </p>

                  <p v-if="conversation.file" class="mt-2">
                    <span v-if="isImage(conversation.file)"
                      ><img :src="$options.filters.safeUrl(conversation.file)" class="chat-image" alt=""
                    /></span>

                    <span v-else>
                      <a
                        :href="$options.filters.safeUrl(conversation.file)"
                        target="_blank"
                        rel="noopener noreferrer"
                        >{{ conversation.file }}</a
                      >
                    </span>
                  </p>

                  <br />
                  <span class="float-right chat-time">
                    <i>{{ conversation.created_at }}</i>
                  </span>

                  <button
                    v-if="auth.uuid === conversation.user.uuid"
                    class="btn btn-link btn-sm"
                    @click.prevent="deleteConversation(conversation.id)">
                    Delete
                  </button>

                  <button v-else class="btn btn-link btn-sm disabled">Delete</button>
                </div>
              </li>
              <span v-if="typing" class="help-block" style="font-style: italic">
                💬 @{{ (user && user.name) || 'Someone' }} is typing...
              </span>
              <span v-else class="help-block" style="font-style: italic"> 💬 </span>
            </ul>

            <!-- Chat Footer -->
          </div>

          <div class="card-footer gioj">
            <div class="chat-floating">
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

            <!-- Chat with mentionable user functionality -->

            <Mentionable :keys="['@']" :items="items" offset="6" insert-space @open="handleOpen" @apply="handleApply">
              <div class="position-relative w-100">
                <textarea
                  class="form-control mb-2"
                  placeholder="Type your message here..."
                  v-model="message"
                  autofocus
                  @keypress.enter.exact.prevent="send()"
                  @keydown="isTyping"
                  row="1">
                </textarea>

                <i class="fa-regular fa-grin chat-emotion position-absolute" @click="toggleEmojiModal"> </i>
              </div>

              <div class="d-flex align-items-center">
                <!-- Attach File Button -->
                <button @click="openFilePicker" class="btn btn-light">
                  <i class="fa-solid fa-paperclip"></i> Attach File
                </button>

                <!-- Show Selected File Name with Delete Option -->
                <div v-if="file" class="ml-2 d-flex align-items-center">
                  <i class="fa-solid fa-file-alt mr-1"></i>
                  <span class="file-name">{{ fileName }}</span>
                  <button @click="removeFile" class="btn btn-sm text-danger p-0 ml-2 file-close-btn">✖</button>
                </div>

                <!-- Hidden File Input -->
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
            <p>
              <button
                class="btn btn-primary btn-sm float-right mb-2"
                id="btn-chat"
                :disabled="isSendDisabled"
                @click.prevent="send()">
                Send
              </button>
            </p>
          </div>
        </div>

        <template v-else>
          <div class="card-body chat-panel">
            <ul class="chat">
              <li v-for="conversation in conversations.data" :key="conversation.id || conversation.created_at">
                <div class="chat-body clearfix">
                  <div class="header">
                    <router-link :to="'/user/' + conversation.user.name + '/profile'">
                      <img
                        v-if="conversation.user.avatar"
                        :src="$options.filters.safeUrl(conversation.user.avatar)"
                        alt="User Avatar"
                        class="chat-user_image" />
                    </router-link>

                    <strong class="primary-font"> {{ conversation.user.name }}</strong>
                  </div>
                  <p v-if="conversation.message" class="mt-2">
                    <span class="chat-message" v-text="conversation.message"></span>
                  </p>

                  <p v-if="conversation.file" class="mt-2">
                    <span v-if="isImage(conversation.file)"
                      ><img :src="$options.filters.safeUrl(conversation.file)" class="chat-image" alt=""
                    /></span>

                    <span v-else>
                      <a
                        :href="$options.filters.safeUrl(conversation.file)"
                        target="_blank"
                        rel="noopener noreferrer"
                        >{{ conversation.file }}</a
                      >
                    </span>
                  </p>

                  <br />
                  <span class="float-right chat-time">
                    <i>{{ conversation.created_at }}</i>
                  </span>

                  <button
                    v-if="auth.uuid === conversation.user.uuid"
                    class="btn btn-link btn-sm"
                    @click.prevent="deleteConversation(conversation.id)">
                    Delete
                  </button>

                  <button v-else class="btn btn-link btn-sm disabled">Delete</button>
                </div>
              </li>
              <span v-if="typing" class="help-block" style="font-style: italic">
                💬 @{{ (user && user.name) || 'Someone' }} is typing...
              </span>
              <span v-else class="help-block" style="font-style: italic"> 💬 </span>
            </ul>
          </div>

          <div class="card-footer gioj">
            <div class="chat-floating">
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

            <Mentionable
              :keys="['@']"
              :items="items"
              offset="6"
              insert-space
              @open="handleOpen"
              @apply="handleApply">
              <div class="position-relative w-100">
                <textarea
                  class="form-control mb-2"
                  placeholder="Type your message here..."
                  v-model="message"
                  autofocus
                  @keypress.enter.exact.prevent="send()"
                  @keydown="isTyping"
                  row="1">
                </textarea>

                <i class="fa-regular fa-grin chat-emotion position-absolute" @click="toggleEmojiModal"> </i>
              </div>

              <div class="d-flex align-items-center">
                <button @click="openFilePicker" class="btn btn-light">
                  <i class="fa-solid fa-paperclip"></i> Attach File
                </button>

                <div v-if="file" class="ml-2 d-flex align-items-center">
                  <i class="fa-solid fa-file-alt mr-1"></i>
                  <span class="file-name">{{ fileName }}</span>
                  <button @click="removeFile" class="btn btn-sm text-danger p-0 ml-2 file-close-btn">✖</button>
                </div>

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
            <p>
              <button
                class="btn btn-primary btn-sm float-right mb-2"
                id="btn-chat"
                :disabled="isSendDisabled"
                @click.prevent="send()">
                Send
              </button>
            </p>
          </div>
        </template>
      </div>
    </SubscriptionCheck>
    <vue-progress-bar></vue-progress-bar>
  </div>
</template>
<script>
import data from 'emoji-mart-vue-fast/data/all.json';
import { Mentionable } from 'vue-mention';
import { Picker, EmojiIndex } from 'emoji-mart-vue-fast';
import SubscriptionCheck from '../../SubscriptionChecker.vue';
import { debounce } from 'lodash';

export default {
  components: { Picker, Mentionable, SubscriptionCheck },
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
    collapsible: {
      type: Boolean,
      default: true,
    },
    showIntro: {
      type: Boolean,
      default: true,
    },
  },
  data() {
    return {
      emojiIndex: new EmojiIndex(data),
      message: '',
      typing: false,
      emojiModal: false,
      user: null,
      fileName: '',
      file: '',
      isSending: false,
      maxFileBytes: 700 * 1024,
      allowedFileTypes: [
        'image/jpeg',
        'image/png',
        'image/jpg',
        'application/pdf',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
      ],
      items: [],
      conversations: { data: [] },
      errors: [],
      users: [...this.members, this.owner],
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
  },

  created() {
    this.loadConversations();

    this.listenToWhisperEvent();

    this.listenForNewMessage();

    this.listenToDeleteConversation();
  },

  methods: {
    isImage(file) {
      return /\.(png|jpg|jpeg)$/i.test(file);
    },

    async handleOpen(key) {
      this.items = key === '@' ? this.users : [];
    },

    async handleApply(item) {
      this.message = `${this.message}@${item.username}`;
      this.message = this.message.replace('@undefined', '');
    },

    showEmoji(emoji) {
      if (!emoji) return;
      this.message += emoji.native;
    },

    openFilePicker() {
      this.$refs.fileInput.click(); // Open file picker when button is clicked
    },

    fileUpload(event) {
      const [file] = event.target.files;

      if (!file) {
        return;
      }

      if (!this.allowedFileTypes.includes(file.type)) {
        this.$vToastify.warning('Allowed files: JPG, PNG, PDF, DOCX');
        this.removeFile();
        return;
      }

      if (file.size > this.maxFileBytes) {
        this.$vToastify.warning('Attachments must be 700KB or smaller');
        this.removeFile();
        return;
      }

      this.file = file;
      this.fileName = file.name;
    },

    removeFile() {
      this.file = null;
      this.fileName = '';
      if (this.$refs.fileInput) {
        this.$refs.fileInput.value = ''; // Clear input field
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
          if (error.response && error.response.data.errors) {
            this.errors = error.response.data.errors; // Store errors
            Object.values(this.errors).forEach((err) => {
              this.$vToastify.warning(err[0]); // Show each error as a toast
            });
          } else {
            this.$vToastify.error('Failed to send message.');
          }
        })
        .finally(() => {
          this.isSending = false;
        });
    },

    deleteConversation(id) {
      axios
        .delete('/projects/' + this.slug + '/conversations/' + id, { useProgress: true })
        .then(() => {
          this.$vToastify.info('Conversation deleted sucessfully');
        })
        .catch((error) => {
          const msg = error?.response?.data?.message || error?.message || 'Failed to delete project conversation';
          this.$vToastify.warning(msg);
        });
    },

    isTyping: debounce(function () {
      Echo.private(`typing.${this.slug}`).whisper('typing-indicator', {
        user: this.auth,
        typing: true,
      });
    }, 500), // Only fires every 500ms

    toggleEmojiModal() {
      this.emojiModal = !this.emojiModal;
    },

    loadConversations() {
      return axios
        .get('/projects/' + this.slug + `/conversations`)
        .then((response) => {
          const payload = response.data;
          if (payload && Array.isArray(payload.data)) {
            this.conversations = payload;
            return;
          }
          if (Array.isArray(payload)) {
            this.conversations = { data: payload };
            return;
          }
          this.conversations = { data: [] };
        })
        .catch((error) => {
          this.conversations = { data: [] };
          this.handleErrorResponse(error);
        });
    },

    listenForNewMessage() {
      Echo.private(`project.${this.slug}.conversations`)
        .listen('NewMessage', (e) => {
          if (!this.conversations.data.some((conv) => conv.id === e.id)) {
            this.conversations.data.push(e);
          }
        })
        .error((error) => {
          this.handleErrorResponse(error);
        });
    },

    listenToDeleteConversation() {
      Echo.private(`deleteConversation.${this.slug}`).listen('DeleteConversation', (e) => {
        const index = this.conversations.data.findIndex((c) => c.id === e.conversation_id);
        if (index !== -1) {
          this.conversations.data.splice(index, 1);
        }
        this.$vToastify.success('conversation deleted');
      });
    },

    listenToWhisperEvent() {
      Echo.private(`typing.${this.slug}`).listenForWhisper('typing-indicator', (e) => {
        this.user = e.user;
        this.typing = e.typing;

        // remove is typing indicator after 0.3s
        setTimeout(() => {
          this.typing = false;
        }, 3000);
      });
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
