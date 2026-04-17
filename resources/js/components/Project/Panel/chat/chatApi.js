import axios from 'axios';

function buildConversationPage(items) {
  return (items || []).slice().reverse();
}

export async function loadChatConversations(vm, cursor = null) {
  try {
    const url = `/projects/${vm.slug}/conversations${cursor ? `?cursor=${cursor}` : ''}`;
    const response = await axios.get(url);
    const payload = response.data;
    const page = buildConversationPage(payload.data || []);

    if (cursor) {
      vm.conversations.data = [...page, ...vm.conversations.data];
    } else {
      vm.conversations.data = page;
    }

    vm.nextCursor = payload.meta?.next_cursor || null;
    vm.hasMore = payload.meta?.has_more || false;
  } catch (error) {
    vm.conversations = { data: [] };
    vm.handleErrorResponse(error);
  }
}

export function buildChatFormData(message, file) {
  const formData = new FormData();

  if (message) {
    formData.append('message', message);
  }

  if (file) {
    formData.append('file', file);
  }

  return formData;
}

export function sendChatMessage(vm) {
  const formData = buildChatFormData(vm.message, vm.file);

  return axios.post(`/projects/${vm.slug}/conversations`, formData, { useProgress: true });
}

export function deleteChatConversation(vm, id) {
  return axios.delete(`/projects/${vm.slug}/conversations/${id}`, { useProgress: true });
}
