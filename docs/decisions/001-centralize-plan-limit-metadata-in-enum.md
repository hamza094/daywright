# ADR-001: Centralize Plan Limit Metadata in PlanLimitType Enum

**Date:** 2026-04-07
**Status:** Accepted

## Context

Subscription plan limits (projects, active tasks, members, meetings, API tokens) required metadata scattered across multiple services — `SubscriptionUsageService`, `PlanLimitService`, and `ResolveUsageCountAction` each maintained their own `loadCount` lists and attribute mappings. Adding a new limit type meant editing 4+ files with no compile-time or test-time guard against forgetting one.

## Decision

Consolidate all per-limit metadata into `PlanLimitType::definition()` — a single match block returning config key, exception key, display label, scope, Eloquent count loaders, and the loaded count attribute name. Services consume enum helpers (`accountCountLoaders()`, `projectCountLoaders()`, `loadedCountAttribute()`) instead of maintaining their own lists.

## Consequences

- **One-place edits:** Adding a limit type requires only a new enum case and its `definition()` entry.
- **Drift protection:** `PlanLimitServiceTest` asserts that enforcement uses preloaded counts for every type — a missing loader fails the test, not production.
- **No N+1 regressions:** `ResolveUsageCountAction` reads the preloaded attribute from the enum; fallback queries only fire when the attribute wasn't preloaded.
- **Trade-off:** The enum carries more responsibility than a typical PHP enum, but the cohesion benefit outweighs the size increase.
