export const buildNotificationIndexParams = (filter = null, page = 1) => {
  const params = {};

  if (typeof page === 'number' && Number.isFinite(page) && page > 0) {
    params.page = page;
  }

  if (filter === 'read' || filter === 'unread') {
    params.filter = { status: filter };
  }

  return params;
};
