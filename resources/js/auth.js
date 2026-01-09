export function permission(auth, members, user, isAdmin) {
  const access =
    (members &&
      members.some(
        (member) =>
          // Support both numeric IDs and UUID-based auth
          member?.id === auth || member?.uuid === auth,
      )) ||
    user === auth ||
    isAdmin;
  const owner = user === auth;

  return { access, owner };
}

export function admin(isAdmin) {
  return { access: isAdmin };
}
