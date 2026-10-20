# Sentry Hardening Documentation

**Date:** 2026-10-20
**Status:** Configuration complete, pending CI secret setup and staging verification

## Changes Made

### 1. Sensitive Data Protection ✅

**Files Modified:** `resources/js/app.js`

**Changes:**

- Removed raw Vue component props from Sentry error events
- `beforeSend` hook now only sends component name, not propsData
- Vue error handler no longer sends propsData
- This prevents exposing private messages, notes, document contents, user IDs, or tokens

**Before:**

```javascript
beforeSend(event, hint) {
  if (event.exception) {
    event.contexts = {
      ...event.contexts,
      vue: {
        componentName: hint.componentName,
        propsData: hint.propsData, // ❌ Leaks sensitive data
      },
    };
  }
  return event;
}
```

**After:**

```javascript
beforeSend(event, hint) {
  if (event.exception) {
    event.contexts = {
      ...event.contexts,
      vue: {
        componentName: hint.componentName || 'Unknown', // ✅ Safe only
      },
    };
  }
  return event;
}
```

### 2. Environment Separation ✅

**Files Modified:** `resources/js/app.js`, `.env.example`

**Changes:**

- Added explicit environment configuration: `environment: import.meta.env.VITE_APP_ENV || import.meta.env.MODE`
- Added `VITE_APP_ENV` to `.env.example` for explicit environment control
- Allows distinct `development`, `staging`, and `production` environments in Sentry

**Environment Variables:**

```bash
# .env.example
VITE_APP_ENV=development  # Options: development, staging, production
```

**Deployment Configuration:**

- Development: Set `VITE_APP_ENV=development` (or omit, defaults to MODE)
- Staging: Set `VITE_APP_ENV=staging` in staging environment
- Production: Set `VITE_APP_ENV=production` in production environment

### 3. Session Replay Privacy ✅

**Files Modified:** `resources/js/app.js`

**Changes:**

- Enabled conservative privacy settings:
  - `maskAllText: true` - All text is masked in replays
  - `blockAllMedia: true` - All media (images, videos) is blocked
- Reduced replay sampling rates:
  - `replaysSessionSampleRate: 0.05` (5% of normal sessions)
  - `replaysOnErrorSampleRate: 0.5` (50% of error sessions)

**Configuration:**

```javascript
Sentry.replayIntegration({
  maskAllText: true, // Masks all text content
  blockAllMedia: true, // Blocks all images/videos
});
```

**What This Protects:**

- Private notes and chat messages
- Uploaded document previews
- Account information pages
- User input fields
- Any text content in the UI

### 4. Source Maps Configuration ✅

**Files Modified:** `vite.config.js`, `package.json`

**Changes:**

- Installed `@sentry/vite-plugin` as dev dependency
- Added Sentry Vite plugin to build pipeline
- Enabled source maps in build: `sourcemap: true`
- Configured artifact cleanup: `cleanArtifacts: true`
- Added build script for production

**Vite Configuration:**

```javascript
import { sentryVitePlugin } from '@sentry/vite-plugin';

export default defineConfig({
  plugins: [
    // ... other plugins
    sentryVitePlugin({
      org: 'hik-tech',
      project: 'daywright',
      authToken: process.env.SENTRY_AUTH_TOKEN, // ⚠️ Must be set in CI
    }),
  ],
  build: {
    sourcemap: true, // Generate source maps
  },
  sentry: {
    disableClientReports: true,
    cleanArtifacts: true, // Clean up old artifacts
  },
});
```

**⚠️ IMPORTANT: CI Secret Required**
The `SENTRY_AUTH_TOKEN` must be set in your CI/CD environment for source map upload. This token should:

- Be stored in CI secrets (GitHub Actions, Forge, etc.)
- Never be committed to code
- Never be in frontend environment variables

**How to Generate Auth Token:**

1. Go to Sentry → Settings → Auth Tokens
2. Create new token with `project:releases` scope
3. Add to CI secrets as `SENTRY_AUTH_TOKEN`

### 5. Test Button Removal ✅

**Files Modified:** `resources/js/components/Dashboard/Dashboard.vue`

**Changes:**

- Removed development-only "Break the world" test button
- This prevents accidental test errors in production builds
- Removed `triggerSentryTest` method

## Verification Steps

### Before Production Deployment

#### 1. Verify Environment Labels

```bash
# Local development
VITE_APP_ENV=development npm run build
# Check Sentry dashboard → errors should show environment: "development"

# Staging (when configured)
VITE_APP_ENV=staging npm run build
# Check Sentry dashboard → errors should show environment: "staging"

# Production
VITE_APP_ENV=production npm run build
# Check Sentry dashboard → errors should show environment: "production"
```

#### 2. Verify Privacy Settings

1. Trigger an error in staging environment
2. Go to Sentry dashboard → find the error
3. Check event details:
   - ✅ Component name is present
   - ✅ No propsData or sensitive fields
   - ✅ If replay exists, all text is masked, media is blocked

#### 3. Verify Source Maps (After CI Secret Setup)

1. Ensure `SENTRY_AUTH_TOKEN` is set in CI
2. Deploy to staging
3. Trigger an error
4. Check Sentry dashboard → error stack trace
5. ✅ Stack traces should point to original source files, not minified code
6. ✅ Source maps should NOT be publicly accessible (check network tab)

#### 4. Verify No Sensitive Data

1. Trigger an error with sensitive data in component props
2. Check Sentry event
3. ✅ Confirm propsData is NOT present
4. ✅ Confirm no tokens, passwords, or private content

### Build Verification

```bash
npm run lint     # ✅ Should pass
npm run build    # ✅ Should pass with source maps
```

**Expected Build Output:**

- Source maps generated (`.map` files)
- Sentry plugin runs (may warn about auth token until CI secret is set)
- No build errors

## Deployment Checklist

- [ ] Set `VITE_APP_ENV=production` in production environment
- [ ] Add `SENTRY_AUTH_TOKEN` to CI/CD secrets
- [ ] Run staging deployment with `VITE_APP_ENV=staging`
- [ ] Verify environment labels in Sentry dashboard
- [ ] Verify error events contain no sensitive data
- [ ] Verify source maps are uploaded (check Sentry → Settings → Source Maps)
- [ ] Verify source maps are not publicly deployed
- [ ] Monitor first production errors in Sentry

## CI/CD Integration

### GitHub Actions Example

```yaml
jobs:
  build:
    runs-on: ubuntu-latest
    env:
      VITE_APP_ENV: production
      SENTRY_AUTH_TOKEN: ${{ secrets.SENTRY_AUTH_TOKEN }}
    steps:
      - uses: actions/checkout@v3
      - name: Install dependencies
        run: npm ci
      - name: Build
        run: npm run build
      - name: Deploy
        run: # your deployment script
```

### Laravel Forge Example

- Add `VITE_APP_ENV=production` to environment variables
- Add `SENTRY_AUTH_TOKEN` to environment variables
- In deploy script, ensure source maps are uploaded during build

## Troubleshooting

### Warning: "No auth token provided"

**Cause:** `SENTRY_AUTH_TOKEN` not set in environment
**Solution:** Add `SENTRY_AUTH_TOKEN` to CI secrets or local environment
**Impact:** Source maps won't upload, but error reporting still works

### Source maps publicly accessible

**Cause:** Source maps deployed to public directory
**Solution:** Sentry plugin should handle cleanup. Verify `cleanArtifacts: true` is set

### Wrong environment label

**Cause:** `VITE_APP_ENV` not set correctly
**Solution:** Set explicitly in environment variables, don't rely on `MODE` alone

### Seeing sensitive data in Sentry

**Cause:** Old code before hardening
**Solution:** Verify `beforeSend` hook removes propsData, redeploy

## Rollback Plan

If issues occur after deployment:

1. Revert `resources/js/app.js` to previous version
2. Revert `vite.config.js` to remove Sentry plugin
3. Rebuild and redeploy
4. Clear Sentry release if needed (via dashboard)

## Related Documentation

- [Frontend Pre-Production Plan](./FRONTEND_PRE_PRODUCTION_PLAN.md)
- [Sentry Vue Documentation](https://docs.sentry.io/platforms/javascript/guides/vue/)
- [Sentry Vite Plugin](https://docs.sentry.io/platforms/javascript/guides/vite/)

---

## Alert Configuration & Best Practices

### Avoiding Alert Fatigue

After deployment, configure Sentry to only alert you on **important** errors. This prevents being overwhelmed with notifications.

### What We Already Filter (Code-Level)

**Automatic filtering in code:**

- ✅ Axios 401/403/404/422/429 errors are NOT sent to Sentry (handled locally)
- ✅ Only production/staging errors are captured (not local development)
- ✅ Similar errors are automatically grouped
- ✅ Environment separation (development/staging/production)

**Examples of errors we DON'T alert:**

- User types wrong password (401)
- User accesses forbidden page (403)
- User submits invalid form (422)
- Resource not found (404)
- Rate limited (429)

**Examples of errors we DO alert:**

- JavaScript crashes (bugs in your code)
- Component rendering failures (bugs in your code)
- Unexpected 500 server errors

### Configure in Sentry Dashboard

After deploying to staging, configure these settings in Sentry → Settings → Alerts:

#### Alert Conditions

```
Alert me when:
✓ Error affects 10+ users (ignore isolated issues)
✓ Error happens 50+ times (ignore one-time flukes)
✓ NEW error appears (never seen before)
✓ Only alert for "production" environment (ignore staging)
```

#### Email Rate Limits

```
Email settings:
✓ Maximum 1 email per hour per issue
✓ Maximum 5 emails per day total
✓ Send daily digest instead of instant alerts
```

#### Priority Rules

```
Priority by impact:
🔴 HIGH: 100+ users affected, happens frequently
🟡 MEDIUM: 10-100 users affected, happens occasionally
🟢 LOW: <10 users affected, happened once
```

### Manual Issue Management

**Ignore unimportant errors:**

1. In Sentry dashboard, click the error
2. Click "Ignore" or "Resolve"
3. Sentry stops alerting you about it

**When to ignore:**

- Browser extension conflicts
- Third-party library bugs you can't fix
- Known issues that don't affect functionality
- Testing/development errors

### Monitoring Strategy

**Daily routine:**

1. Check Sentry dashboard once per day
2. Focus on HIGH priority errors first
3. Fix bugs affecting most users
4. Ignore or resolve low-priority issues

**Goal:** 5 meaningful alerts per day instead of 100 noise alerts.
