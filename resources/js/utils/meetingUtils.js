export function shouldShowStartButton(meeting, auth, notAuthorize) {
  return !notAuthorize && meeting.owner.id === auth.id && meeting.status.toLowerCase() !== 'started';
}

export function shouldShowJoinButton(meeting, auth, members) {
  if (meeting.owner.id === auth.id) {
    return false;
  }
  return members.some((member) => member.id === auth.id || (member.uuid && member.uuid === auth.uuid));
}
