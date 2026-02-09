# Feature Flags — Developer Guide

This document explains how to add, deprecate, and remove feature flags in DayWright.
It covers the backend enum, the `FeatureFlagsResource` API contract, and frontend consumption.

Goals

- Single source of truth: `App\\Enums\\FeatureFlag`.
- Secure exposure: only `visibleToClient()` flags are returned to non-admin users.
  - The frontend does NOT ship a hardcoded list of flag keys. Clients only receive flags returned by the backend.
- Simple frontend usage: import and call `hasFeature(this.$store, 'feature_key')` (missing keys treated as disabled).

1. Adding a new feature flag

- Add a new case to `app/Enums/FeatureFlag.php` (FeatureFlag::cases() is used).
- Decide whether the flag is safe to expose to clients; if yes, opt-in via `visibleToClient()`.
- Add or update Pennant toggles as required.
- Add backend tests covering the flag's resolution.

2. Frontend: consuming the new flag

- `/me` includes `features`; normalize these on bootstrap.
- Use the `resources/js/utils/features.js` helpers (preferred):
  - Import in components:

    ```javascript
    import { hasFeature } from '@/resources/js/utils/features.js';
    // or relative: import { hasFeature } from '../utils/features.js';
    ```

  - In scripts/computed properties: `hasFeature(this.$store, 'new_awesome_feature')`
  - In templates, use a computed wrapper and then `v-if="computedFlag"`.

  If you prefer a global API, register a small Vue plugin that attaches helpers to `Vue.prototype` (optional).

Update frontend tests for UI visibility where needed. Tests should avoid relying on a client-side canonical list of flags; instead mock the `/me` `features` payload.

3. Deprecation guidance

- Stop using the flag and document deprecation.
- Keep the enum case and set the toggle to `false` during the deprecation window.
- After clients are migrated, remove the enum case and Pennant toggle.

4. Exposure rules

- Non-admin users receive only active flags that are `visibleToClient()`.
- Admin users receive the full map with true/false values.
- If there is no user context, the resource returns an empty map.

Suggested tests

- Unit: `FeatureFlagsResource` (admin vs non-admin, visible vs hidden flags).
- Integration: `/me` and relevant endpoints.
- Frontend: small unit tests for the `resources/js/utils/features.js` helper (mock store).

Quick checklist

- Add enum case in `app/Enums/FeatureFlag.php`
- Add Pennant toggle / rollout
- Add backend tests (unit/integration)
- Update frontend usage and tests
- Document deprecation/rollout here

Questions or suggestions: open a PR and ping the team for rollout approval.
