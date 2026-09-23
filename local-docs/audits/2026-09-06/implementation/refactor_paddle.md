# P2.3 - finish Paddle Classic safely and simply

## Decision

- Incoming Paddle Classic webhooks use Cashier's `/paddle/webhook` route. Do not recreate a Paddle inbox.
- Outgoing `swap` and `cancel` use the existing `SubscriptionOperation` record. One operation may send one Paddle mutation. If its result is unknown, recovery reads Paddle; it never sends the mutation again.
- Keep the current service, recovery actions, commands, gateway, and snapshot. Do not add a generic outbox, registry, strategy, or another service layer.

## Already done

- Phase 0: checked for pending Paddle inbox work in development. Repeat the check in each deployed environment before switching its webhook URL.
- Phase 1: restored Cashier's route, removed the custom route, and added production HTTPS and `/paddle/webhook` URL checks.
- Phase 2: added basic route and configured URL tests. Cashier supplies its own webhook signature and business handling.
- Phase 3: removed the custom Paddle inbox classes and kept Zoom inbox recovery.
- Phase 4: made `swap` and `cancel` explicit in `SubscriptionService`; added the unique operation key, fingerprint check, `processing` state, claim token, read-only recovery, manual review, snapshot, and shared remote verification.

Do not repeat these implementation steps. The application currently uses only one custom durable operation for Paddle: `SubscriptionOperation` for outbound mutations.

## Safety fixes completed in this review

- `SubscriptionService` rechecks the same user/key under the transaction lock and sends the Paddle mutation only for a newly created operation. Final writes check the current token and `processing` state. Provider error text is not returned to the API.
- Recovery locks and checks the claim before finishing or rescheduling a read. Manual resolution rechecks `manual_review` under a lock before changing local state. The command prints a safe failure message.
- Recovered cancellation uses Cashier's `deleted` status and a verified remote end date. The snapshot stores that date directly rather than wrapping it in an array.
- Recovery and manual resolution cannot mark an operation complete when its local subscription is missing. Swap and cancel reconciliation now share one short code path.
- Stale command and fake tests were updated to the current DTO and method signatures. Focused tests cover an in-progress replay, a stale recovery claim, and a missing local subscription.
- Swap and cancel routes require an idempotency key but rely on `SubscriptionOperation` for replay and concurrency safety; Cashier's HTTP response-cache middleware remains only on checkout creation.

These are narrow fixes within the existing classes. `SubscriptionService` owns the user request and one Paddle call. `RecoverSubscriptionOperation` owns one read-only recovery attempt. `RecoverSubscriptionOperations` selects due rows and counts outcomes. `ResolveSubscriptionOperation` owns manual review. The commands parse input and print safe results. No further class splitting is needed for this release.
