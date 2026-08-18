# Scramble OpenAPI Documentation — Request Audit Implementation Plan

## Background

Following the successful documentation of the API controllers, this plan addresses the Developer Experience (DX) gaps found in the FormRequest classes. The audit identified areas where Scramble's static analysis falls short (closures, custom rules) and highlighted a few actual code inconsistencies.

---

## Phase 1: Logic & Consistency Fixes (Critical)

These are actual code inconsistencies or bugs that will break runtime behavior or generate invalid OpenAPI schemas.

- [x] **`InvitationUsersRequest`:**
  - Remove the `emails` and `emails.*` arrays from the validation rules so it only accepts a single `email` string, matching the controller's actual logic.
  - Make `email` a required field.
  - Delete the `withValidator` method since the conditional logic is no longer needed.
- [x] **`ScrambleServiceProvider.php`:**
  - Fix the pagination injection for the `notifications` endpoint (line 348). It currently injects `page`, but `NotificationIndexRequest` uses `cursor` pagination. Update the provider to reflect `cursor`.
  - Remove the redundant `page` and `per_page` parameter injection for the `projects/{project}/tasks` endpoint (lines 352-355), as these are already defined and documented in `TaskIndexRequest`.
- [x] **`TaskUpdateRequest.php`:** Fix the invalid `@example all` on the `notified` field. Replace it with a valid enum string (e.g., `1 Day Before`) that matches the `TaskDueNotifies` enum.

---

## Phase 2: Core PHPDoc Additions

Add descriptions, explicit `@var` types, and `@example` tags to provide context that Scramble cannot infer from validation rules.

- [x] **`ConversationIndexRequest`:** Add descriptions and examples for `cursor` and `per_page` (document default of 10).
- [x] **`NotificationIndexRequest`:** Add an example for the filter object and document default `per_page` of 25.
- [x] **`DashboardProjectRequest`:** Document `filter`, `filter.search`, `filter.member`, `filter.abandoned`, `sort`, `page`, and `per_page`. Add examples (e.g., `filter[member]=true`, `sort=-created_at`).
- [x] **`ProjectActivityIndexRequest`:** Document filters and pagination. Explicitly list the allowed filter types (`specifics`, `tasks`, `members`, `mine`).
- [x] **`ProjectInsightsRequest`:** Add `@var array<int,string>` and an example `["health","risk"]`. Document that omitting the array returns all sections.
- [x] **`ProjectInvitationIndexRequest`:** Document the `filter` object and explain that `filter.status` currently only accepts `pending`.
- [x] **`UserInvitationsIndexRequest`:** Add descriptions/examples for pagination.
- [x] **`StageRequest`:** Document `stage` as a stage ID and explain when `postponed_reason` is required.

---

## Phase 3: Complex Schema Workarounds

These requests contain closures or custom rules that Scramble cannot parse. We must use explicit PHPDoc to override or supplement the generated schema.

- [x] **`ProjectStoreRequest`:** Add descriptions for every field. Explain that terminal stages (6 and 7) are intentionally excluded during creation. Crucially, add `@var array<int,array{title:string}>` to the `tasks` field with a valid JSON example (`[{"title":"Prepare launch checklist"}]`).
- [x] **`ProjectUpdateRequest`:** Document that `name`, `about`, and `notes` must differ from current values (since Scramble cannot read the closure logic).
- [x] **`TaskRequest` & `TaskUpdateRequest`:** Add explicit `@var string` for `title` and `description`. For `due_at`, add an explicit format hint (`@example 2024-12-09T15:25:00+00:00`) because Scramble won't parse the `Iso8601Timestamp` custom rule into a date-time format natively.
- [x] **`UserRequest`:** Add `@var string` for `username` and `mobile`. Provide quoted string examples for phone numbers to preserve leading zeros. Describe `timezone` as an IANA timezone identifier.
- [x] **`TaskMembersRequest`:** Add explicit PHPDoc type hints (e.g. `@var array<int>`) to `members` and `members.*` fields to ensure Scramble generates a strict array-of-integers schema.
- [x] **`UserActivitiesRequest`:** Update the PHPDoc for `end_date` to explicitly document the business constraint enforced by the `after()` hook: the range between `start_date` and `end_date` cannot exceed 31 days.
- [x] **`InvitationUsersRequest`:** Add a descriptive PHPDoc blocks and a realistic example for the single `email` field.

---

## Phase 4: Scramble Best Practices & Standards

These are Scramble-specific patterns to apply throughout the implementation.

- [x] **Utilizing `@ignoreParam` or `#[IgnoreParam]`:**
  - **Verdict:** Agree 🟢
  - **Why:** If a FormRequest has helper parameters (e.g., internal flags or parameters mapped from route parameters that are used solely for internal authorization/validation but not expected in the API request body), adding `@ignoreParam` in the PHPDoc is the correct way to keep the OpenAPI spec clean and clutter-free.
  - **Action:** Audit all FormRequest classes for internal-only parameters and mark them with `@ignoreParam` or `#[IgnoreParam]` attribute.
  - **Note:** This will be added to the Backend Guidelines standards.
  - **Result:** No internal-only parameters found in reviewed FormRequest classes. Route parameters are handled by Laravel's route model binding and Scramble automatically.

- [x] **Explicitly formatting date-time fields:**
  - **Verdict:** Agree (But keep it in PHPDoc) 🟢
  - **Why:** For custom rules like `Iso8601Timestamp`, Scramble will default to a generic string type. Documenting this clearly in the description (e.g., specifying "ISO 8601 date-time string with timezone" and providing a valid `@example 2024-12-09T15:25:00+00:00`) is the best way to handle this without polluting the controller.
  - **Action:** For all date-time fields using custom rules, add explicit format documentation in PHPDoc descriptions with valid ISO 8601 examples.
  - **Result:** `TaskUpdateRequest.due_at` has explicit ISO 8601 format documentation.

---

## Phase 5: Verification

Generate the OpenAPI spec to verify the PHPDoc annotations successfully override and enhance the schemas.

- [ ] Run `php artisan scramble:export`.
- [ ] Serve the API docs locally and visually inspect the schemas for `ProjectStoreRequest`, `TaskUpdateRequest`, and the pagination endpoints to ensure they render perfectly.
