export const buildNotificationIndexParams = (filter = null, cursor = null) => {
  const params = {};

  if (typeof cursor === 'string' && cursor.trim() !== '') {
    params.cursor = cursor;
  }

  if (filter === 'read' || filter === 'unread') {
    params.filter = { status: filter };
  }

  return params;
};
