/**
 * Determine access and ownership for a given auth identifier.
 *
 * @param {string|number} auth - Identifier to check (numeric id or UUID).
 * @param {Array<Object>} [members] - Optional list of member objects which may contain `id` or `uuid` properties.
 * @param {string|number} user - Current user's identifier.
 * @param {boolean} isAdmin - Whether administrative privileges are present.
 * @returns {{access: boolean, owner: boolean}} `access` is `true` if any member's `id` or `uuid` equals `auth`, or `user === auth`, or `isAdmin` is truthy; `owner` is `true` if `user === auth`, `false` otherwise.
 */
export function permission(auth, members, user, isAdmin) {
  const access =
    members?.some(
      (member) =>
        // Support both numeric IDs and UUID-based auth
        member?.id === auth || member?.uuid === auth,
    ) ||
    user === auth ||
    isAdmin;
  const owner = user === auth;

  return { access, owner };
}

export function admin(isAdmin) {
  return { access: isAdmin };
}