Edge Cases Found

1. 🔴 Critical — "Send first, flag later" in the Jobs (Original Codex Finding)
   Files:
   SendMeetingStartedNotification.php
   ,
   SendMeetingEndedNotification.php

The jobs send the notification on line 59, then try to set the \*\_notification_sent_at flag on line 66 inside a try/catch(Throwable) that swallows failures.

Failure scenarios:

Worker dies (OOM, deploy, timeout) between Notification::send() and update() → flag stays null → next dispatch sends again
DB error on the update() is swallowed → flag stays null → same result
Fix: Atomic claim-before-send pattern (detailed in the solution section below).

2. 🟡 Medium — Webhook action dispatches notification without atomically guarding the flag
   File:
   HandleMeetingStartedWebhook.php (line 34)

php

if ($transitioned || ($meeting->status === MeetingState::START->value
&& $meeting->started_notification_sent_at === null)) {
    $this->dispatchNotificationJob($meeting, $data->startTime);
}
This reads started_notification_sent_at from the in-memory model (potentially stale from the initial getMeeting() call). If two Zoom webhook retries arrive near-simultaneously:

Both load the model, both see started_notification_sent_at === null
The transitionToStarted() atomic guard prevents double status update — but the fallback condition on line 34 ($meeting->status === MeetingState::START->value && ...null) means the second request still dispatches the job
The ShouldBeUnique lock on the job mitigates this during the 120s window, but if the first job finishes before the second webhook request runs, a second job dispatch is allowed.

Fix: Re-query the flag from the DB just before dispatch, or skip the fallback condition entirely and rely solely on the job's own idempotency guard.

3. 🟡 Medium — HandleMeetingEndedWebhook dispatches notification regardless of transition result
   File:
   HandleMeetingEndedWebhook.php (lines 30-34)

php

$this->transitionToEnded($meeting, $data->meetingId, $data->requestId);
if ($meeting->ended_notification_sent_at === null) {
$this->dispatchNotificationJob(...);
}
The $transitioned return value is discarded. Even when transitionToEnded() returns false (meeting was already ended), the notification dispatch still runs if the flag is null. This is an explicit "retry recovery" path — but combined with the job's send-first-flag-later pattern, it amplifies the duplicate window.

Additionally, logWebhookProcessed is called unconditionally on line 35, even when the transition was ignored and a notification was already sent, which produces misleading logs.

Fix: Use the $transitioned result consistently, as the started webhook does.

4. 🟢 Low — Stale model data used for notification payload
   Files:
   HandleMeetingStartedWebhook.php (lines 70-78)
   ,
   HandleMeetingEndedWebhook.php (lines 64-72)

dispatchNotificationJob() reads $meeting->project->name, $meeting->project->user, etc. The model was loaded via getMeeting() which does not eager-load project.asignees or project.user. This triggers lazy-loading and means the payload is read from the DB at webhook time — but if the project/user changed between webhook receipt and job processing, the data could be stale. The job itself re-loads with ->with(['project.asignees', 'project.user']), so the recipients are fresh, but the payload data (serialized into $notificationData at dispatch time) could mismatch.

Risk: Low — project names and slugs rarely change during a meeting.

5. 🟢 Low — uniqueFor = 120 seconds may be too short
   Files:
   SendMeetingStartedNotification.php (line 27)
   ,
   SendMeetingEndedNotification.php (line 27)

If the queue is backed up and the job hasn't been picked up within 120 seconds, the ShouldBeUnique lock expires. A duplicate dispatch (from a Zoom webhook retry) will then be allowed through.

Fix: Increase uniqueFor to something like 300-600 seconds, or remove it entirely and rely on the job-level idempotency guard instead.

6. 🟢 Low — $tries = 1 means no retry on transient failures
   Files:
   SendMeetingStartedNotification.php (line 23)
   ,
   SendMeetingEndedNotification.php (line 23)

With $tries = 1, any transient failure (mail server hiccup, network blip) permanently drops the notification. Combined with the swallowed flag-update error, this creates a confusing state: the notification wasn't sent, the flag might or might not be set, and there's no retry.

Fix: After switching to claim-before-send, set $tries = 3 with $backoff = [10, 60]. The claim pattern makes retries safe since a failed send rolls back the flag.

Recommended Production-Safe Solution
Job: Atomic claim → send → rollback-on-failure
php

public function handle(): void
{
$meeting = Meeting::query()
        ->with(['project.asignees', 'project.user'])
        ->findOrFail($this->meetingId);
// Already sent — idempotent exit
if ($meeting->started_notification_sent_at !== null) {
        return;
    }
    // Atomically claim — only ONE process can succeed
    $claimed = Meeting::query()
        ->where('id', $this->meetingId)
        ->whereNull('started_notification_sent_at')
        ->update(['started_notification_sent_at' => now()]);
    if ($claimed === 0) {
return; // Another worker or previous run already claimed
}
try {
Notification::send(
$meeting->project->asignees,
            new MeetingStarted($this->notificationData),
);
} catch (Throwable $e) {
// Roll back the claim so a retry can re-attempt
Meeting::query()
->where('id', $this->meetingId)
->update(['started_notification_sent_at' => null]);
throw $e; // Let the job fail and retry naturally
}
}
Webhook: Tighten the dispatch conditions
For the started webhook, drop the stale-model fallback:

diff

- if ($transitioned || ($meeting->status === MeetingState::START->value
-     && $meeting->started_notification_sent_at === null)) {

* if ($transitioned) {
      $this->dispatchNotificationJob($meeting, $data->startTime);
  }
  For the ended webhook, use the $transitioned return value:

diff

- $this->transitionToEnded($meeting, ...);
- if ($meeting->ended_notification_sent_at === null) {

* $transitioned = $this->transitionToEnded($meeting, ...);
* if ($transitioned) {
  $this->dispatchNotificationJob(...);
  }
* if ($transitioned) {
  $this->support->logger->logWebhookProcessed(...);
* }
  IMPORTANT

Removing the stale-model fallback means if a webhook fires but the job silently fails (no retry), the notification is lost. This is acceptable only if you also increase $tries on the job to allow retries. The claim-before-send pattern makes retries safe.

Summary of changes
Change Why
Atomic whereNull()->update() claim before send Eliminates duplicate sends from worker death or concurrent execution
Rollback flag on send failure + throw Makes retries safe — next attempt can re-claim
$tries = 3, $backoff = [10, 60] Recovers from transient mail/notification failures
Remove stale-model fallback in webhook actions Eliminates extra dispatch path that bypasses the transition guard
Use $transitioned consistently in ended webhook Aligns logging and dispatch with actual state change
Optional: increase uniqueFor to 300 Wider protection window against Zoom webhook retries
