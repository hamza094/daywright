import { ref } from 'vue';
import { getCursorPaginatedData } from '../utils/apiResponse';

const EMPTY_CURSOR_CONVERSATIONS = {
  data: [],
  meta: { next_cursor: null, prev_cursor: null },
  links: {},
};

export function useChatMessages(slug) {
  const conversations = ref({ ...EMPTY_CURSOR_CONVERSATIONS });
  const loadingMore = ref(false);
  const scrollObserver = ref(null);

  const loadConversations = () => {
    return axios
      .get(`/projects/${slug}/conversations`)
      .then((response) => {
        conversations.value = getCursorPaginatedData(response);
      })
      .catch((error) => {
        conversations.value = { ...EMPTY_CURSOR_CONVERSATIONS };
        throw error;
      });
  };

  const loadMoreConversations = () => {
    if (!conversations.value.meta.next_cursor) {
      return Promise.resolve();
    }

    loadingMore.value = true;

    return axios
      .get(`/projects/${slug}/conversations`, {
        params: { cursor: conversations.value.meta.next_cursor },
      })
      .then((response) => {
        const newData = getCursorPaginatedData(response);
        conversations.value = {
          data: [...newData.data, ...conversations.value.data],
          meta: newData.meta,
          links: newData.links,
        };
      })
      .catch((error) => {
        throw error;
      })
      .finally(() => {
        loadingMore.value = false;
      });
  };

  const setupScrollObserver = (scrollSentinelRef, chatPanelRef) => {
    if (!scrollSentinelRef || !chatPanelRef) {
      return;
    }

    scrollObserver.value = new IntersectionObserver(
      (entries) => {
        if (entries[0].isIntersecting && !loadingMore.value) {
          loadMoreConversations();
        }
      },
      {
        root: chatPanelRef,
        rootMargin: '100px',
      },
    );

    scrollObserver.value.observe(scrollSentinelRef);
  };

  const disconnectScrollObserver = () => {
    if (scrollObserver.value) {
      scrollObserver.value.disconnect();
      scrollObserver.value = null;
    }
  };

  return {
    conversations,
    loadingMore,
    scrollObserver,
    loadConversations,
    loadMoreConversations,
    setupScrollObserver,
    disconnectScrollObserver,
  };
}
