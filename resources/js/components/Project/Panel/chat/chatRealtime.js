export function scrollChatToBottom(panel) {
  if (panel) {
    panel.scrollTop = panel.scrollHeight;
  }
}

export async function restoreChatScrollPosition(vm, previousScrollTop, previousScrollHeight) {
  await vm.$nextTick();
  await new Promise((resolve) => requestAnimationFrame(resolve));

  const panel = vm.$refs.chatPanel;

  if (panel) {
    panel.scrollTop = previousScrollTop + (panel.scrollHeight - previousScrollHeight);
  }
}

export function whisperTypingIndicator(slug, auth) {
  Echo.private(`typing.${slug}`).whisper('typing-indicator', {
    user: auth,
    typing: true,
  });
}

export function registerNewMessageListener(vm) {
  Echo.private(`project.${vm.slug}.conversations`)
    .listen('NewMessage', async (event) => {
      if (!vm.conversations.data.some((conversation) => conversation.id === event.id)) {
        vm.conversations.data.push(event);
        await vm.$nextTick();
        scrollChatToBottom(vm.$refs.chatPanel);
      }
    })
    .error((error) => {
      vm.handleErrorResponse(error);
    });
}

export function registerDeleteConversationListener(vm) {
  Echo.private(`deleteConversation.${vm.slug}`).listen('DeleteConversation', (event) => {
    const index = vm.conversations.data.findIndex((conversation) => conversation.id === event.conversation_id);

    if (index !== -1) {
      vm.conversations.data.splice(index, 1);
    }

    vm.$vToastify.success('conversation deleted');
  });
}

export function registerTypingListener(vm) {
  Echo.private(`typing.${vm.slug}`).listenForWhisper('typing-indicator', (event) => {
    vm.user = event.user;
    vm.typing = event.typing;

    setTimeout(() => {
      vm.typing = false;
    }, 3000);
  });
}
