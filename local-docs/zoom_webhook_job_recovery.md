# Zoom Webhook Job Recovery Guide

## Overview

Zoom webhook jobs are processed asynchronously to ensure fast 200 responses to Zoom. Failed jobs are automatically retried with exponential backoff, and failures are logged to a dedicated channel for monitoring and alerting.

## Job Configuration

All Zoom webhook jobs extend `ZoomMeetingWebhookJob` and include:

- **Tries**: 3 attempts
- **Backoff**: [5, 30] seconds (5s on first retry, 30s on second)
- **WithoutOverlapping**: Prevents duplicate processing of the same meeting
- **Timeout**: 120 seconds
- **FailOnTimeout**: true

## Monitoring

Failed webhook jobs are logged to the `zoom_webhook_failed` log channel:

- **Log file**: `storage/logs/zoom-webhook-failed-YYYY-MM-DD.log`
- **Retention**: 30 days
- **Level**: error

Logs include only safe identifiers:

- `meeting_id` (Zoom meeting number)
- `request_id` (Zoom request ID)
- `operation` (e.g., `zoom.webhook.meeting.started`)
- `user_uuid` (user UUID if available)
- Exception class and sanitized message

All sensitive data is redacted:

- Access tokens
- Refresh tokens
- ZAK tokens
- Start URLs
- Join URLs
- Passwords
- Webhook secrets
- API keys

## Alerting

Monitor the `zoom_webhook_failed` log channel for errors. Set up alerts to notify operators when:

- Failed webhook events occur
- Error rate exceeds threshold
- Specific meeting IDs repeatedly fail

## Operator Path for Retrying Failed Jobs

### Using Laravel Queue Commands

#### View Failed Jobs

```bash
php artisan queue:failed
```

#### Retry All Failed Jobs

```bash
php artisan queue:retry all
```

#### Retry Specific Failed Job

```bash
php artisan queue:retry <job-id>
```

#### Retry Failed Jobs for Specific Queue

```bash
php artisan queue:retry --queue=zoom-webhooks
```

### Using Horizon (if configured)

If using Laravel Horizon for queue management:

1. Access Horizon dashboard
2. Navigate to "Failed Jobs"
3. Filter by tag: `provider:zoom`
4. Select failed jobs and click "Retry"

### Manual Webhook Replay

For critical missed webhooks, you may need to manually replay the webhook:

1. **Check the meeting status**:

   ```bash
   # Check if meeting exists and its current state
   php artisan tinker
   >>> $meeting = App\Models\Meeting::where('meeting_id', <zoom-meeting-id>)->first();
   >>> $meeting->status;
   >>> $meeting->sync_status;
   ```

2. **Verify Zoom meeting state**:
   - Use Zoom API to check actual meeting status
   - Compare with local database state

3. **Manual state correction** (if necessary):

   ```bash
   php artisan tinker
   >>> $meeting = App\Models\Meeting::where('meeting_id', <zoom-meeting-id>)->first();
   >>> $meeting->update(['status' => 'started']); // or 'ended'
   ```

4. **Trigger notifications manually** (if needed):
   ```bash
   php artisan tinker
   >>> $meeting = App\Models\Meeting::where('meeting_id', <zoom-meeting-id>)->first();
   >>> $project = $meeting->project;
   >>> $members = $project->asignees;
   >>> Notification::send($members, new App\Notifications\Zoom\MeetingStarted([...]));
   ```

## Common Failure Scenarios

### 1. Meeting Not Found

- **Cause**: Webhook received for a meeting that doesn't exist in database
- **Action**: Check if meeting was deleted or never synced
- **Recovery**: May need to sync meeting from Zoom API

### 2. Inactive Sync Status

- **Cause**: Meeting has `sync_status` other than `active`
- **Action**: Webhook is ignored, logged as `zoom_webhook_ignored`
- **Recovery**: Review why meeting is not active (update_failed, delete_failed, deleted)

### 3. Database Connection Issues

- **Cause**: Temporary database connectivity problems
- **Action**: Job will retry automatically
- **Recovery**: Monitor database health

### 4. Zoom API Rate Limits

- **Cause**: Exceeded Zoom API rate limits
- **Action**: Job will retry with backoff
- **Recovery**: Review API usage patterns

### 5. Notification Failures

- **Cause**: Email service or notification channel issues
- **Action**: Job may fail if notifications are critical
- **Recovery**: Check notification service status

## Prevention

### Regular Monitoring

- Monitor `zoom_webhook_failed` logs daily
- Set up alerts for error rate thresholds
- Review failed job patterns weekly

### Queue Worker Health

- Ensure queue workers are running continuously
- Monitor queue worker memory usage
- Restart workers if memory leaks detected

### Database Maintenance

- Ensure database indexes are optimized
- Monitor database connection pool health
- Regular database backups

## Testing

Test webhook job recovery:

```bash
# Run webhook job tests
php artisan test tests/Feature/Api/Jobs/Webhooks/Zoom/
```

Test log sanitization:

```bash
php artisan test --filter WebhookLogSanitization
```
