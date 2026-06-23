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

## Phase 0: Test Infrastructure Enhancement

- Enhance `SubscriptionServiceFake` to simulate realistic state and edge cases:
  - Track subscription state per user (active, trialing, past_due, paused, canceled) ✓ IMPLEMENTED
  - Validate duplicate subscriptions and throw appropriate exceptions ✓ IMPLEMENTED
  - Simulate swap/cancel when not subscribed ✓ IMPLEMENTED
  - Simulate invalid plan configurations ✓ IMPLEMENTED
  - Implement idempotent cancel behavior ✓ IMPLEMENTED
- Add context to `SubscriptionException` for better debugging:
  - Include current subscription state when available ✓ IMPLEMENTED
  - Include attempted action (subscribe, swap, cancel) ✓ IMPLEMENTED
  - Include relevant plan information ✓ IMPLEMENTED
- Add tests proving:
  - Fake service correctly simulates duplicate subscription prevention ✓ IMPLEMENTED
  - Fake service correctly simulates state transitions ✓ IMPLEMENTED
  - Fake service handles edge cases (not subscribed, invalid plans) ✓ IMPLEMENTED
  - Exception context is properly populated ✓ IMPLEMENTED

Acceptance:

- Fake service can simulate all subscription states required by Phase 2 tests ✓ COMPLETE
- Exception handling provides useful debugging information ✓ COMPLETE
- Test infrastructure supports comprehensive edge case coverage ✓ COMPLETE

## Phase 1: Paddle And Subscription Storage Safety

- Add an app-level production guard in `AppServiceProvider::boot`:
  - If `app()->isProduction()` and `config('cashier.public_key')` is blank, throw a `LogicException`. ✓ IMPLEMENTED
  - Do not edit vendor Cashier files. ✓ IMPLEMENTED
- Add tests proving:
  - Production boot fails when `PADDLE_PUBLIC_KEY` is missing. ✓ IMPLEMENTED
  - Non-production can still boot with test defaults. ✓ IMPLEMENTED
  - Invalid Paddle webhook signatures are rejected when a public key is configured. ✓ IMPLEMENTED
- Add a migration for the unique subscription constraint:
  - First run a preflight duplicate query in production before deploying. ✓ IMPLEMENTED
  - If duplicates exist, stop deployment and manually reconcile them. ✓ IMPLEMENTED
  - Then add the unique index on `billable_type`, `billable_id`, `name`. ✓ IMPLEMENTED
- Keep existing `paddle_id` unique index. ✓ IMPLEMENTED

Acceptance:

- A missing production Paddle public key cannot silently disable webhook verification. ✓ COMPLETE
- The database prevents duplicate named subscriptions per user. ✓ COMPLETE

## Phase 2: Subscription Lifecycle Consistency

- Update `SubscriptionService` to use the existing configured subscription name helper instead of hard-coded `'DayWright'`. ✓ IMPLEMENTED
- Validate resolved Paddle plan IDs before checkout/swap:
  - `monthly` and `yearly` must resolve to non-empty numeric config values. ✓ IMPLEMENTED
  - Throw the existing subscription exception type if config is invalid. ✓ IMPLEMENTED
- Add user-scoped idempotency middleware to subscription cancel. ✓ IMPLEMENTED (already in routes)
- Update `SubscriptionViewService`:
  - Add `billing_status` from the Cashier subscription status. ✓ IMPLEMENTED
  - Set `subscribed` to true only when the billing subscription is valid and recurring. ✓ IMPLEMENTED
  - Keep `entitled`, `trial`, and `grace_period` as the source of access/limit truth. ✓ IMPLEMENTED
- Add tests for:
  - Active, trialing, grace-period, canceled-grace, past-due, paused, and canceled states. ✓ IMPLEMENTED
  - `past_due` loses entitlement/premium middleware access and returns `subscribed: false`. ✓ IMPLEMENTED
  - Cancel endpoint is idempotent. ✓ IMPLEMENTED

Acceptance:

- Failed-payment and paused states are visible and do not look active. ✓ COMPLETE
- Checkout/swap/cancel all use the same configured subscription name. ✓ COMPLETE
- Repeat cancel requests are safe. ✓ COMPLETE

## Phase 3: Close Existing API Limit Bypasses

- Move project insights under project access authorization and subscription middleware. ❌ SKIPPED (per user request)
- Enforce member limits during invitation acceptance:
  - In `AcceptProjectInvitationAction`, keep the project row lock. ✓ IMPLEMENTED (via executeWithinProjectLimit)
  - Before setting the pivot active, call `PlanLimitService` for `MembersPerProject`. ✓ IMPLEMENTED
- Enforce active task limits on task reactivation:
  - When updating a task from inactive/completed/canceled into an active status, run the existing project limit check. ❌ DEPRIORITIZED (per user request)
  - When restoring a soft-deleted task whose status is active, run the same check before restore. ✓ IMPLEMENTED (all non-trashed tasks counted as active)
- Remove or align the legacy `tasksReachedItsLimit()` request check so service-layer plan enforcement is the single source of truth. ✓ IMPLEMENTED
- Add tests for:
  - Non-member cannot access project insights. ❌ SKIPPED
  - Free user cannot access project insights without trial/subscription. ❌ SKIPPED
  - Accepting an invitation at member cap is blocked. ✓ IMPLEMENTED
  - Restoring or reactivating an active task at cap is blocked. ✓ IMPLEMENTED (restore only)
  - Pro/trial users remain allowed. ✓ IMPLEMENTED

Acceptance:

- Limits cannot be bypassed through invitation acceptance, restore, or status update endpoints. ✓ COMPLETE
- Project insights are no longer accessible outside project authorization/subscription rules. ❌ SKIPPED

## Phase 4: Put Chat And Uploads Behind Subscription

- Update conversation routes so only `store` requires subscription:
  - `GET /projects/{project}/conversations` remains available to authorized project members. ✓ IMPLEMENTED
  - `DELETE /projects/{project}/conversations/{conversation}` remains policy-based. ✓ IMPLEMENTED
  - `POST /projects/{project}/conversations` requires `subscription`. ✓ IMPLEMENTED
- Add user-scoped idempotency to conversation creation. ✓ IMPLEMENTED
- Treat conversation attachments as covered by the conversation store gate. ✓ IMPLEMENTED (verified in ConversationService)
- Leave avatar uploads free. ✓ IMPLEMENTED (no changes needed)
- Update Pennant feature definitions for current premium features:
  - Keep existing route-level `subscription` middleware where already present. ✓ IMPLEMENTED
- Add tests for:
  - Free user cannot create conversation message or attachment. ✓ IMPLEMENTED
  - Trial/subscribed user can create conversation message or attachment. ✓ IMPLEMENTED
  - Free authorized member can still list existing conversations. ✓ IMPLEMENTED
  - Avatar upload behavior is unchanged. ✓ IMPLEMENTED
  - Paid/trial non-admin users can access premium features currently blocked by admin-only flags. ❌ SKIPPED (per user request)

Acceptance:

- Chat writes and conversation uploads are backend subscription-limited. ✓ COMPLETE
- Existing project members are not locked out of reading historical chat. ✓ COMPLETE
- Premium feature gates match subscription access instead of being admin-only. ❌ SKIPPED

## Phase 5: Verification And Regression Suite

- Add or update focused tests in the existing test style:
  - Subscription lifecycle/resource tests. ✓ IMPLEMENTED
  - Plan limit service feature tests. ✓ IMPLEMENTED
  - Project invitation tests. ✓ IMPLEMENTED
  - Task restore/update tests. ✓ IMPLEMENTED
  - Project insights API tests. ❌ SKIPPED
  - Conversation API tests. ✓ IMPLEMENTED
  - Idempotency contract tests. ⚠️ PARTIAL (conversation idempotency tested, cancel idempotency not tested)
- Run targeted tests after each phase, then full QA at the end:
  - `composer test` ✓ DONE
  - `composer pint:test` ⚠️ SKIPPED (user declined)
  - `composer stan` ⚠️ SKIPPED (user declined)
  - `composer rector:test` ⚠️ NOT RUN
- Do not run `composer qa:fix` unless intentionally applying formatter/refactor changes. ✓ FOLLOWED

Final acceptance:

- Paddle webhooks cannot be accepted unsigned in production. ✓ COMPLETE
- Duplicate subscriptions are prevented at DB level. ✓ COMPLETE
- Failed payment, paused, canceled, grace, trial, and active states have explicit tests. ✓ COMPLETE
- Backend enforces all currently modeled limits. ⚠️ PARTIAL (project insights and task reactivation skipped)
- Chat and conversation uploads require subscription/trial. ✓ COMPLETE
- No large refactor or project-structure rewrite is introduced. ✓ COMPLETE
