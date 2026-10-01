# Adding an Inbound Webhook Provider

Use this guide when connecting an external service that sends event notifications to DayWright. For example, Zoom sends DayWright a webhook when a meeting starts or ends.

The webhook inbox is currently used by Zoom. When adding another provider, keep that provider's security checks and payload handling specific to it, and reuse the inbox for saving, processing, and recovering events.

## How webhook handling works

1. The provider sends DayWright an event.
2. DayWright checks that the request really came from that provider and that its data is valid.
3. DayWright saves the event before acknowledging it, so it can still be processed if a worker is unavailable.
4. A background worker applies the change in DayWright.
5. If processing fails, the scheduled recovery process can try again.

Providers may send the same event more than once, and a worker may start processing an event again after a failure. The integration must recognize duplicates and avoid repeating the same change.

## Where to look in the code

The Zoom integration is the working example:

- [`routes/api/v1/webhooks.php`](../routes/api/v1/webhooks.php) registers the webhook routes and request checks.
- [`VerifyZoomWebhook.php`](../app/Http/Middleware/VerifyZoomWebhook.php) verifies Zoom requests.
- [`ZoomWebhookController.php`](../app/Http/Controllers/Api/V1/Webhooks/ZoomWebhookController.php) accepts supported events.
- [`ZoomWebhookInboxService.php`](../app/Services/Webhooks/ZoomWebhookInboxService.php) saves events and coordinates processing and recovery.
- [`ProcessZoomWebhookInbox.php`](../app/Jobs/Webhooks/ProcessZoomWebhookInbox.php) processes an event in a queue worker.
- [`RecoverPendingWebhooks.php`](../app/Console/Commands/RecoverPendingWebhooks.php) retries events that were not completed.

Follow this flow when adding a provider, while keeping its request checks and event handling specific to that provider.

## Steps for a new integration

### 1. Learn the provider's webhook rules

Check the provider's documentation for:

- how to verify a request (usually a signature and timestamp)
- which events DayWright needs
- whether events have a stable unique ID
- which response tells the provider it can stop retrying
- how often the provider retries a failed delivery

Do not accept an event until its request has been verified. Never log signing secrets or raw credentials.

### 2. Validate and identify each event

Validate the fields DayWright uses, then convert them into the data needed by the relevant application action. Keep provider-specific event formats out of the shared inbox code.

Use the provider's stable event ID to recognize a duplicate when available. If it has none, derive a repeatable key from the verified request data. The database uses the provider name and event key together to prevent saving the same event twice.

### 3. Save before acknowledging

Save the verified event in the webhook inbox before telling the provider it was received. If saving fails, return an error so the provider can retry. If the event is already saved, respond according to that provider's documented acknowledgement rules.

Webhook payloads are stored encrypted. Keep only the data needed to process and investigate supported events.

### 4. Process events in a background job

Create a focused handler for each supported event type and dispatch it through the inbox. A handler must be safe to run again: check current state or use a unique database record so a retry does not repeat the same business change.

Do not rely on the original HTTP request being available to the background job.

### 5. Use the shared recovery process

The inbox tracks whether an event is waiting, being processed, completed, or has failed. It also tracks attempts and when a failed or interrupted event can be tried again.

Use the existing inbox recovery command and scheduled task. Keep retry timing in one place: the inbox owns webhook retries, while the queue job makes one processing attempt.

### 6. Document and test the integration

Document the webhook endpoint, required provider settings, supported events, and how to investigate or retry a failed event.

Add tests that cover the essential failure cases:

- valid and invalid provider signatures
- missing or malformed event data
- duplicate delivery
- database or queue failure
- retry after processing failure
- ensuring a repeated event does not repeat its business effect
- the response DayWright returns to the provider

## Keep the design simple

Keep provider-specific verification, event formats, and handlers in that provider's integration. Share the existing inbox behavior for saving and recovering events. Add a shared abstraction only when another real integration needs the same behavior and the abstraction makes the code simpler.

For the current Zoom implementation, see [Webhook Inbox](WEBHOOK_INBOX.md).
