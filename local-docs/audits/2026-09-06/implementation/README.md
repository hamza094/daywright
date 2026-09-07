# REST production audit implementation handoff

Source: [rest-production-audit.md](../rest-production-audit.md), dated 6 September 2026.

This package extracts the audit's implementation guidance into five sequential, independently usable SWE 1.6 handoffs. Application code has not been changed by creating these documents. Every checkbox starts unchecked; the audit's historical test results are not evidence that these tasks are complete.

The [findings reference](findings-reference.md) contains descriptions, severity assessments, and reproduction evidence. The phase documents contain implementation work, code excerpts, tests, and acceptance checks.

## Phase documents

| Phase                         | Release boundary                                   | Handoff                                                                        |
| ----------------------------- | -------------------------------------------------- | ------------------------------------------------------------------------------ |
| 1: Critical security fixes    | Before developer-token workflows                   | [phase-1-critical-security-fixes.md](phase-1-critical-security-fixes.md)       |
| 2: Integration reliability    | Before relying on integrations                     | [phase-2-integration-reliability.md](phase-2-integration-reliability.md)       |
| 3: API contract stabilization | Before stabilizing public API                      | [phase-3-api-contract-stabilization.md](phase-3-api-contract-stabilization.md) |
| 4: Production readiness       | Before declaring production ready                  | [phase-4-production-readiness.md](phase-4-production-readiness.md)             |
| 5: Deployment repeatability   | Before a repeatable production release is accepted | [phase-5-deployment-repeatability.md](phase-5-deployment-repeatability.md)     |

The phase 1 title follows the requested roadmap. Its scope also includes the idempotency, webhook, and dependency work that the audit places before developer-token workflows.

## How to use with SWE 1.6

Open one phase document in the repository and provide this instruction:

> Implement the checklist in this phase document. Read its referenced files and applicable repository instructions first. Work through the implementation and tests, preserve unrelated working-tree changes, and record verification results. Treat proposed filenames as suggestions and reuse existing equivalents when suitable. Report unresolved design choices and any unmet acceptance criteria. Complete this phase before starting the next phase.

Each phase repeats the context and acceptance rules it needs. Sequential prerequisites remain explicit: self-contained instructions do not imply that later phases can ignore earlier security or reliability work.

## Provenance and implementation conventions

- **Audit requirement** means an implementation instruction extracted from the source report.
- **Implementation elaboration** means additional file placement, test scenarios, or verification detail supplied to make that instruction executable. It is not a new finding or a verbatim audit prescription.
- **Verbatim audit snippet** means the code appears in the report. Some snippets are deliberately incomplete patterns and need adaptation.
- **New (proposed)** files do not exist at extraction time. Their names are suggestions, not claims about current code.
- Existing paths were checked against the workspace. Line numbers quoted from the audit identify the audited code and can shift as implementation proceeds; locate the associated symbol before editing.
- Test additions and verification procedures are implementation elaborations unless explicitly identified as audit instructions. Verify behavior and business effects, not the existence of a class or helper.
- Preserve existing session/bearer boundaries, scope checks, policies, audit events, API documentation, and legitimate success cases while changing the affected behavior.
- Record a decision for alternatives explicitly allowed by the audit: concrete-URI key scope versus conflict on key reuse; PATCH-only versus full PUT replacement; archive-first deletion semantics; collaborator contact visibility; and optional endpoint renaming.
- Dependencies and provider capabilities must be rechecked when implementing. Version floors in the audit are historical minimums, not a guarantee that those versions resolve every advisory at implementation time.

## Reproduction probes are not regression tests

Source: [DaywrightRestAuditProbeTest.php](../DaywrightRestAuditProbeTest.php).

The ten probes intentionally assert defective behavior. Their passing result means the old failure was reproduced. Preserve the forensic source; port relevant scenarios into permanent tests with corrected expectations. After a fix, an original probe may fail because it still expects the defect.

The clock-expiry probe and two-snapshot transition probe are deterministic failure-window demonstrations. Phase 4 separately requires multiple processes, shared Redis, and the intended production database engine.

## Complete extraction coverage

The IDs below are organizational labels added for this handoff. F1-F10 correspond to the audit's ranked priorities; A1-A6 to its additional gaps; R items to remaining roadmap/assessment guidance.

| Source item                                           | Implementation coverage                                                                                      | Owner                                  |
| ----------------------------------------------------- | ------------------------------------------------------------------------------------------------------------ | -------------------------------------- |
| F1a: owner policy and admin override                  | Target-aware policy; actor/target tests across account and avatar mutations                                  | P1.1                                   |
| F1b: email/account scope boundary                     | Dedicated verified email-change flow; remove profile email mutation; protect deletion                        | P1.2                                   |
| F1c: active-account force deletion                    | Explicit archive-first state rule if that contract is retained                                               | P1.3                                   |
| F2: request identity and replay authorization         | Durable package customization; concrete resource identity; multipart canonicalization; current-access checks | P1.4                                   |
| F3: leases and post-lock completion checks            | Bounded deadlines, lease handling, cache recheck; durable externally visible operations                      | P1.5, P2.1-P2.3, P4.2                  |
| F4: webhook acceptance                                | Interim failure reservation repair; full durable inbox and pending-work recovery                             | P1.6, P2.1                             |
| F5: stale transitions and lost updates                | Refetch/lock task and project; decide version/precondition contract                                          | P2.5, P3.6, P4.2                       |
| F6: dependency advisories                             | Update four packages and transitive dependencies; required CI audit                                          | P1.7                                   |
| F7a: Zoom unknown outcomes                            | Transactional operation records, provider references, reconciliation                                         | P2.2                                   |
| F7b: Paddle network work in retryable transactions    | Separate remote work from local retries; serialize through durable operations                                | P2.3                                   |
| F7c: message claims and batch recovery                | Outbox, expiring ownership, terminal callbacks, recipient-specific retry                                     | P2.4                                   |
| F8: PUT/PATCH and no-op updates                       | Explicit method contract; remove must-differ rules; avoid duplicate notifications                            | P3.1                                   |
| F9: exception headers                                 | Preserve Allow/Retry-After and existing 429 behavior                                                         | P3.2                                   |
| F10: pagination query propagation                     | Canonical validated query in generated links; follow-link tests                                              | P3.3                                   |
| A1: subscription form routes                          | API singleton or explicit operations                                                                         | P3.4                                   |
| A2: infrastructure errors misclassified as validation | Separate domain validation and safe infrastructure 5xx errors                                                | P3.5                                   |
| A3: array/object error inconsistency                  | Shared formatter; raw JSON type tests                                                                        | P3.2                                   |
| A4: collaborator private fields                       | Explicit visibility decision and allowlisted collaborator representation                                     | P1.8; compatibility documented in P3.6 |
| A5: log and audit metadata sanitization               | Common sanitizer, channel coverage, exception/request context, Paddle allowlist                              | P1.9                                   |
| A6: action-oriented naming                            | Optional assignee, bulk-deletion job, and two-factor status resources                                        | P3.7                                   |
| R1: versioning/deprecation                            | Preserve consumed v1 contracts; explicit transition and documentation policy                                 | P3.6                                   |
| R2: real database/Redis/workers                       | Dedicated configuration and CI job; real concurrency and interruption tests                                  | P4.1-P4.3                              |
| R3: pagination/query performance                      | Realistic dataset, p95 and query measurements, eager-loading checks                                          | P4.4                                   |
| R4: PHP/runtime alignment                             | PHP requirement and repeatable environment/install procedure                                                 | P5.1                                   |
| R5: dependency health                                 | Database, Redis, worker, and scheduler checks                                                                | P5.2                                   |
| R6: restore and rollback                              | Isolated restore and release rollback/forward recovery exercises                                             | P5.3                                   |
| R7: operational alerts                                | Delivered alerts tied to a responsible operator and recovery procedure                                       | P5.4                                   |
| R8: PHPUnit deprecations                              | Migrate metadata while preserving test discovery and datasets                                                | P5.5                                   |
| R9: professional evidence                             | Scope, duplicate-operation, and webhook recovery case studies                                                | P4.5                                   |

## Scope preserved from the audit

The extraction does not introduce a requirement for HATEOAS, a universal ApiController superclass, a framework rewrite, or new frontend work. Preserve valid `GET /me`, legitimate DELETE 200/204 behavior, and the intentional Sanctum session/bearer split. Remaining route spelling changes are optional contract work, not prerequisites for fixing the security items.

## Completion record

For each phase, record the implemented items, files changed, selected design alternatives, tests and commands run with outcomes, evidence paths, and unfinished acceptance checks. Phase 4 records operational evidence; phase 5 completes the deployment and recovery gates. Only a fresh review of the completed implementation can revise the audit's readiness assessment.
