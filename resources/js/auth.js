export function permission(authId, members, userId, is_admin) {
  const isMember = members?.some(({ id, uuid }) => id === authId || uuid === authId);
  const owner = userId === authId;
  const access = isMember || owner || is_admin;

  return { access, owner };
}

export function admin(is_admin) {
  return { access: is_admin };
}
