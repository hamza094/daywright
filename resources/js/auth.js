export function permission(authId, members, userId, isAdmin) {
  const isMember = members?.some(({ id, uuid }) => id === authId || uuid === authId);
  const owner = userId === authId;
  const access = isMember || owner || isAdmin;

  return { access, owner };
}

export function admin(isAdmin) {
  return { access: isAdmin };
}
