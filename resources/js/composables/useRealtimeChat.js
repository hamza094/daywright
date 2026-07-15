export function useRealtimeChat(slug, conversations) {
  const listenForNewMessage = () => {
    Echo.private(`project.${slug}.conversations`)
      .listen('NewMessage', (e) => {
        if (!conversations.value.data.some((conv) => conv.id === e.id)) {
          conversations.value.data.push(e);
        }
      })
      .error((error) => {
        console.error('Error listening for new messages:', error);
      });
  };

  const listenToDeleteConversation = () => {
    Echo.private(`deleteConversation.${slug}`).listen('DeleteConversation', (e) => {
      const index = conversations.value.data.findIndex((c) => c.id === e.conversation_id);
      if (index !== -1) {
        conversations.value.data.splice(index, 1);
      }
    });
  };

  return {
    listenForNewMessage,
    listenToDeleteConversation,
  };
}
