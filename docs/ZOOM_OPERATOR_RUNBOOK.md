# Zoom Operator Runbook

Created: 2026-06-02

This runbook covers the two operator workflows Phase 5 requires: reconnecting revoked Zoom accounts and replaying failed Zoom webhook jobs.

## Signals To Check First

- `storage/logs/zoom.log` for structured `zoom_request_failed`, `zoom_webhook_retry_scheduled`, `zoom_webhook_failed`, and `zoom_webhook_ignored` entries.
- `storage/logs/exception-metrics.log` for business-level Zoom user errors such as revoked or disconnected accounts.
- Queue worker failure output for tagged Zoom jobs.

Structured Zoom log fields to use during triage:

- `provider`
- `operation`
- `meeting_id`
- `request_id`
- `user_id`
- `retry_after_seconds`

## Revoked Or Unusable Zoom Account Checklist

1. Find the affected `user_id` from `zoom.log`, API logs, or the frontend error.
2. Confirm whether the latest error is a user-correctable Zoom error (`zoom_error`, `zoom_forbidden`, `User is not connected to Zoom.`) or an upstream outage (`zoom_unavailable`).
3. If the account was revoked or disconnected, instruct the user to reconnect Zoom through the normal OAuth flow.
4. Verify the reconnect completed by confirming the user has a fresh Zoom token bundle and the callback succeeds.
5. Retry the original product action only after reconnection is confirmed.

## Failed Zoom Webhook Replay Checklist

1. Identify the failed job from the queue worker, Horizon, Telescope, or `zoom.log` using the job tags and `request_id`.
2. Confirm the failure is not a stale or already-processed event by checking `zoom_webhook_ignored` entries first.
3. If the job failed because of a transient dependency issue, replay the failed job after the dependency recovers.
4. If the failure was caused by missing local data, confirm whether the event should remain an intentional no-op before replaying.
5. After replay, confirm the expected meeting state transition, notification behavior, or delete/update side effect actually occurred.

## Policy Decisions

- Ignored stale or duplicate Zoom webhook events are logged at `info` because they are expected idempotent behavior, not operator action by default.
- Retryable webhook execution failures are logged at `warning` with `retry_after_seconds` so operators can distinguish transient failures from terminal failures.
- Terminal webhook failures are logged at `error` and should be investigated or replayed.
- Provider `429` responses may expose `retry_after_seconds` in API error metadata when Zoom provides it. Other upstream failures stay generic in the API response and detailed in logs.
