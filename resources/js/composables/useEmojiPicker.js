import { ref } from 'vue';
import data from 'emoji-mart-vue-fast/data/all.json';
import { EmojiIndex } from 'emoji-mart-vue-fast';

const emojiIndex = new EmojiIndex(data);

export function useEmojiPicker() {
  const emojiModal = ref(false);

  const toggleEmojiModal = () => {
    emojiModal.value = !emojiModal.value;
  };

  return {
    emojiIndex,
    emojiModal,
    toggleEmojiModal,
  };
}
