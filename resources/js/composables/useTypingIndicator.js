import { ref } from 'vue';
import { debounce } from 'lodash';

const TYPING_CONFIG = {
  DEBOUNCE_MS: 500,
  INDICATOR_TIMEOUT_MS: 3000,
};

export function useTypingIndicator(slug, auth) {
  const typing = ref(false);
  const user = ref(null);
  let timeout = null;

  const isTyping = debounce(function () {
    Echo.private(`typing.${slug}`).whisper('typing-indicator', {
      user: auth,
      typing: true,
    });
  }, TYPING_CONFIG.DEBOUNCE_MS);

  const listenToWhisperEvent = () => {
    Echo.private(`typing.${slug}`).listenForWhisper('typing-indicator', (e) => {
      user.value = e.user;
      typing.value = e.typing;

      // Remove is typing indicator after timeout
      if (timeout) clearTimeout(timeout);
      timeout = setTimeout(() => {
        typing.value = false;
      }, TYPING_CONFIG.INDICATOR_TIMEOUT_MS);
    });
  };

  return {
    typing,
    user,
    isTyping,
    listenToWhisperEvent,
  };
}
