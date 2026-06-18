export function isMeetingActive(meeting) {
  return meeting.sync_status === 'active';
}

export function getSyncStatusBadge(syncStatus) {
  const statusConfig = {
    active: { color: 'bg-green', label: 'Active' },
    update_failed: { color: 'bg-orange', label: 'Sync Failed' },
    delete_failed: { color: 'bg-red', label: 'Delete Failed' },
    deleted: { color: 'bg-gray', label: 'Deleted' },
    pending: { color: 'bg-yellow', label: 'Pending' },
    updating: { color: 'bg-blue', label: 'Updating' },
    deleting: { color: 'bg-purple', label: 'Deleting' },
    failed: { color: 'bg-red', label: 'Failed' },
  };

  return statusConfig[syncStatus] || { color: 'bg-gray', label: syncStatus };
}

export function shouldShowStartButton(meeting, auth, notAuthorize) {
  return (
    !notAuthorize &&
    meeting.owner.id === auth.id &&
    meeting.status.toLowerCase() !== 'started' &&
    meeting.status.toLowerCase() !== 'ended' &&
    isMeetingActive(meeting)
  );
}

export function shouldShowJoinButton(meeting, auth, members) {
  if (meeting.owner.id === auth.id) {
    return false;
  }
  const isMember = members.some((member) => member.id === auth.id || (member.uuid && member.uuid === auth.uuid));
  return isMember && meeting.status.toLowerCase() !== 'ended' && isMeetingActive(meeting);
}

export function canViewMeeting(meeting, isOwner) {
  // Hide deleted meetings from normal UI
  if (meeting.sync_status === 'deleted') {
    return false;
  }

  // Normal members can only see active meetings
  if (!isOwner) {
    return meeting.sync_status === 'active';
  }

  // Project owners can see active, update_failed, and delete_failed meetings
  return ['active', 'update_failed', 'delete_failed'].includes(meeting.sync_status);
}
