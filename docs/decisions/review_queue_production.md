# Cover queue schdulat edge cases

## High

1. CancelZoomMeetingsJob catches ZoomException, logs it, and still lets the job succeed, so 401/403/5xx failures do not retry or land in failed_jobs. The FailOnException middleware will not help because the exception is swallowed. See [CancelZoomMeetingsJob.php (line 71)](C:/Users/Hamza/daywright/app/Jobs/CancelZoomMeetingsJob.php:71).


2. Abandoned project cleanup dispatches Zoom cancellation before forceDelete(), and afterCommit() is not protective unless this action is inside a transaction. If delete fails after dispatch, Zoom meetings can be cancelled for a project still in DB. See [ForceDeleteAbandonedProjectAction.php (line 25)](C:/Users/Hamza/daywright/app/Actions/Project/ForceDeleteAbandonedProjectAction.php:25).


3. Task reminders set notify_sent = true before notification enqueue/send. If enqueue fails, the task is skipped forever; also due_at >= now() means scheduler downtime can permanently miss due reminders. See [SendTaskDueNotificationAction.php (line 34)](C:/Users/Hamza/daywright/app/Actions/SendTaskDueNotificationAction.php:34), [TaskQueryBuilder.php (line 82)](C:/Users/Hamza/daywright/app/QueryBuilder/TaskQueryBuilder.php:82).


## Medium
4. Meeting started/ended jobs update the sent flag after Notification::send() and swallow update failures. Worker death after send, or DB update failure, can duplicate meeting notifications later. See [SendMeetingStartedNotification.php (line 59)](C:/Users/Hamza/daywright/app/Jobs/SendMeetingStartedNotification.php:59), [SendMeetingEndedNotification.php (line 59)](C:/Users/Hamza/daywright/app/Jobs/SendMeetingEndedNotification.php:59).



