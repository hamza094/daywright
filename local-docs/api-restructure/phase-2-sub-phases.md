# Scramble OpenAPI Documentation — Phased Implementation Plan

## Background

The Scramble setup (`ScrambleServiceProvider`) is robust. The goal is to improve the API documentation (Developer Experience) by fixing runtime bugs, centralizing cross-cutting concerns, and adding explicit descriptions and permissions to the exposed controllers.

This plan combines code fixes with the findings from the Controller Audit.

## Phase 1: Centralize Global API Conventions

Update `config/scramble_overview.php` to house cross-cutting concerns, preventing repetitive docblocks across methods.

- [ ] **Soft Deletes:** Add a note explaining that resources in an "abandoned" state use soft-deletes (`deleted_at`) and can be returned via endpoints using `withTrashed()`.
- [ ] **Rate Limiting:** Update the Rate Limiting table in `config/scramble_overview.php`:
  - Update `api` to 300/min by IP (Safety Net).
  - Add `user-ceiling` (200/min by user).
  - Add `per-token` (30/min by token).
  - Add the sensitive limits: `sensitive-destructive` (5/min), `sensitive-upload` (10/min), `sensitive-token-mgmt` (5/min), `sensitive-billing` (5/min), and `sensitive-password` (5/min).- [ ] **Subscription Gates:** Ensure the "Important Notes" clearly states that premium endpoints return `403 Forbidden` if the user's subscription is inactive or plan limits are exceeded.

## Phase 2: Document Critical Cascades & Preconditions

Update the docblocks in specific controllers to address severe developer experience gaps (verified against business logic).

- [ ] **`ProjectController::destroy`:** Clarify this is a soft-delete (abandon) operation.
- [ ] **`ForceDeleteProjectController::__invoke`:** State it only applies to abandoned projects. Warn that it triggers a permanent cascade delete of all associated tasks, conversations, messages, meetings, and member associations.
- [ ] **`RestoreProjectController::__invoke`:** State it only applies to abandoned projects.
- [ ] **`AssignTaskMembersController::__invoke`:** Document that this triggers a `TaskAssigned` email notification. Explicitly mention that only the **task owner** or **project owner** can assign members (as defined by `TasksPolicy::manage`).
- [ ] **`ProjectMemberController::__invoke`:** Clarify that when a member is removed, their existing task assignments and conversations **remain intact** (they are not automatically revoked or deleted).
- [ ] **`UserController::destroy`:** Fix the broken markdown (`* This will also soft delete all projects owned by the user*.`). Clarify which other resources are affected.
- [ ] **`ForceDeleteUserController::__invoke`:** State that the user must already be soft-deleted. Include an irreversibility warning and document cascade behaviors.

## Phase 3: Document Important Gaps & Polish

Update the docblocks for remaining methods with missing constraints, enums, or side effects.

- [ ] **`ProjectController::store`:** Explain creator is auto-added as project owner.
- [ ] **`ProjectController::update`:** Warn that empty data results in `400 Bad Request`.
- [ ] **`UpdateProjectStageController::__invoke`:** Mention stage change triggers notifications to all members.
- [ ] **`ProjectLimitsController::__invoke`:** Explain what limits are returned (task counts, member limits).
- [ ] **`TaskStatusController::__invoke`:** Document `TaskDueNotifies` enum values.
- [ ] **`ProjectInvitationController::store`:** Document that invited user receives email.
- [ ] **`AcceptProjectInvitationController::__invoke`:** Explain accepting grants full member access immediately.
- [ ] **`AvatarController::store`:** Document accepted file types, max size, and replacement behavior.
- [ ] **`SubscriptionController` (All):** Link to plans, document immediate vs end-of-cycle cancellation and swap behaviors.
- [ ] **Polish:** Add parameter context (year/month defaults, filter flags) to Dashboard endpoints and list default `per_page` values on paginated endpoints.
      .
