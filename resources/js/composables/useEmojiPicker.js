import { ref } from 'vue';
import data from 'emoji-mart-vue-fast/data/all.json';
import { EmojiIndex } from 'emoji-mart-vue-fast';

export function useEmojiPicker() {
  const emojiIndex = new EmojiIndex(data);
  const emojiModal = ref(false);

  const toggleEmojiModal = () => {
    emojiModal.value = !emojiModal.value;
  };

  const showEmoji = (emoji, messageRef) => {
    if (!emoji || !messageRef) return;
    messageRef.value += emoji.native;
  };

  return {
    emojiIndex,
    emojiModal,
    toggleEmojiModal,
    showEmoji,
  };
}
