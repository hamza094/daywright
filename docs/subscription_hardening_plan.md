# DayWright Subscription Hardening Plan

## Summary

Implement the audit fixes in five small phases, keeping the current Laravel/Cashier/Paddle structure and existing `PlanLimitType` / `PlanLimitService` pattern. The first phases harden billing correctness and security; the later phases close API bypasses and put chat/uploads behind subscription access.

Assumptions:

- Upload enforcement means conversation attachments, not user avatars.
- Chat enforcement means creating project conversations/messages requires subscription/trial; reading existing conversations stays available to project members.
- Notes and notifications remain unchanged for now, but are documented follow-ups if they become paid features.
- Admin feature behavior is preserved while allowing paid/trial users to access premium features.

## Public/API And Schema Changes

- Add a DB unique constraint for one subscription name per billable user: `(billable_type, billable_id, name)`.
- Add `billing_status` to `GET /users/me/subscription`; keep existing response keys.
- Tighten `subscribed` semantics so `past_due` and paused subscriptions do not appear billing-active.
- Add subscription access requirements to:
  - `GET /projects/{project}/insights`
  - `POST /projects/{project}/conversations`
- Add idempotency to `DELETE /users/me/subscription`.

## Phase 1: Paddle And Subscription Storage Safety

- Add an app-level production guard in `AppServiceProvider::boot`:
  - If `app()->isProduction()` and `config('cashier.public_key')` is blank, throw a `LogicException`.
  - Do not edit vendor Cashier files.
- Add tests proving:
  - Production boot fails when `PADDLE_PUBLIC_KEY` is missing.
  - Non-production can still boot with test defaults.
  - Invalid Paddle webhook signatures are rejected when a public key is configured.
- Add a migration for the unique subscription constraint:
  - First run a preflight duplicate query in production before deploying.
  - If duplicates exist, stop deployment and manually reconcile them.
  - Then add the unique index on `billable_type`, `billable_id`, `name`.
- Keep existing `paddle_id` unique index.

Acceptance:

- A missing production Paddle public key cannot silently disable webhook verification.
- The database prevents duplicate named subscriptions per user.

## Phase 2: Subscription Lifecycle Consistency

- Update `SubscriptionService` to use the existing configured subscription name helper instead of hard-coded `'DayWright'`.
- Validate resolved Paddle plan IDs before checkout/swap:
  - `monthly` and `yearly` must resolve to non-empty numeric config values.
  - Throw the existing subscription exception type if config is invalid.
- Add user-scoped idempotency middleware to subscription cancel.
- Update `SubscriptionViewService`:
  - Add `billing_status` from the Cashier subscription status.
  - Set `subscribed` to true only when the billing subscription is valid and recurring.
  - Keep `entitled`, `trial`, and `grace_period` as the source of access/limit truth.
- Add tests for:
  - Active, trialing, grace-period, canceled-grace, past-due, paused, and canceled states.
  - `past_due` loses entitlement/premium middleware access and returns `subscribed: false`.
  - Cancel endpoint is idempotent.

Acceptance:

- Failed-payment and paused states are visible and do not look active.
- Checkout/swap/cancel all use the same configured subscription name.
- Repeat cancel requests are safe.

## Phase 3: Close Existing API Limit Bypasses

- Move project insights under project access authorization and subscription middleware.
- Enforce member limits during invitation acceptance:
  - In `AcceptProjectInvitationAction`, keep the project row lock.
  - Before setting the pivot active, call `PlanLimitService` for `MembersPerProject`.
- Enforce active task limits on task reactivation:
  - When updating a task from inactive/completed/canceled into an active status, run the existing project limit check.
  - When restoring a soft-deleted task whose status is active, run the same check before restore.
- Remove or align the legacy `tasksReachedItsLimit()` request check so service-layer plan enforcement is the single source of truth.
- Add tests for:
  - Non-member cannot access project insights.
  - Free user cannot access project insights without trial/subscription.
  - Accepting an invitation at member cap is blocked.
  - Restoring or reactivating an active task at cap is blocked.
  - Pro/trial users remain allowed.

Acceptance:

- Limits cannot be bypassed through invitation acceptance, restore, or status update endpoints.
- Project insights are no longer accessible outside project authorization/subscription rules.

## Phase 4: Put Chat And Uploads Behind Subscription

- Update conversation routes so only `store` requires subscription:
  - `GET /projects/{project}/conversations` remains available to authorized project members.
  - `DELETE /projects/{project}/conversations/{conversation}` remains policy-based.
  - `POST /projects/{project}/conversations` requires `subscription`.
- Add user-scoped idempotency to conversation creation.
- Treat conversation attachments as covered by the conversation store gate.
- Leave avatar uploads free.
- Update Pennant feature definitions for current premium features:
  - `project-export` and `project-messaging` should allow admins, active subscribers, and trial users.
  - Keep existing route-level `subscription` middleware where already present.
- Add tests for:
  - Free user cannot create conversation message or attachment.
  - Trial/subscribed user can create conversation message or attachment.
  - Free authorized member can still list existing conversations.
  - Avatar upload behavior is unchanged.
  - Paid/trial non-admin users can access premium features currently blocked by admin-only flags.

Acceptance:

- Chat writes and conversation uploads are backend subscription-limited.
- Existing project members are not locked out of reading historical chat.
- Premium feature gates match subscription access instead of being admin-only.

## Phase 5: Verification And Regression Suite

- Add or update focused tests in the existing test style:
  - Subscription lifecycle/resource tests.
  - Plan limit service feature tests.
  - Project invitation tests.
  - Task restore/update tests.
  - Project insights API tests.
  - Conversation API tests.
  - Idempotency contract tests.
- Run targeted tests after each phase, then full QA at the end:
  - `composer test`
  - `composer pint:test`
  - `composer stan`
  - `composer rector:test`
- Do not run `composer qa:fix` unless intentionally applying formatter/refactor changes.

Final acceptance:

- Paddle webhooks cannot be accepted unsigned in production.
- Duplicate subscriptions are prevented at DB level.
- Failed payment, paused, canceled, grace, trial, and active states have explicit tests.
- Backend enforces all currently modeled limits.
- Chat and conversation uploads require subscription/trial.
- No large refactor or project-structure rewrite is introduced.
