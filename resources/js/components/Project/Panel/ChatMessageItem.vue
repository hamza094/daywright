<template>
  <li :class="{ 'chat-item_own': isOwn }">
    <div class="chat-body clearfix">
      <div class="header d-flex align-items-start">
        <div class="d-flex align-items-center gap-2">
          <router-link :to="'/user/' + conversation.user.uuid + '/profile'">
            <img
              v-if="conversation.user.avatar"
              :src="$safeUrl(conversation.user.avatar)"
              alt="User Avatar"
              class="chat-user_image" />
          </router-link>

          <strong class="primary-font"> {{ conversation.user.name }}</strong>
        </div>

        <div v-if="isOwn" class="chat-message_actions ml-auto">
          <FeatureDropdown
            :feature-pop="isOpen"
            @update:featurePop="(val) => $emit('toggle-menu', conversation.id || conversation.created_at, val)">
            <ul class="feature-dropdown_menu">
              <li class="feature-dropdown_item-content" @click="$emit('delete', conversation.id)">
                <i class="fa-solid fa-ban"></i> Delete
              </li>
            </ul>
          </FeatureDropdown>
        </div>
      </div>
      <p v-if="conversation.message" class="mt-2">
        <span class="chat-message" v-text="conversation.message"></span>
      </p>

      <p v-if="conversation.file" class="mt-2">
        <span v-if="isImage(conversation.file)"
          ><img :src="$safeUrl(conversation.file)" class="chat-image" alt=""
        /></span>

        <span v-else>
          <a :href="$safeUrl(conversation.file)" target="_blank" rel="noopener noreferrer">
            {{ conversation.file }}
          </a>
        </span>
      </p>
      <span class="float-right chat-time">
        <i>{{ conversation.created_at | msgTime }}</i>
      </span>
    </div>
  </li>
</template>

<script>
import FeatureDropdown from '../../FeatureDropdown.vue';

export default {
  components: { FeatureDropdown },
  props: {
    conversation: {
      type: Object,
      required: true,
    },
    auth: {
      type: Object,
      required: true,
    },
    isOpen: {
      type: Boolean,
      default: false,
    },
  },
  computed: {
    isOwn() {
      return this.auth.uuid === this.conversation.user.uuid;
    },
  },
  methods: {
    isImage(file) {
      return /\.(png|jpg|jpeg)$/i.test(file);
    },
  },
};
</script>
