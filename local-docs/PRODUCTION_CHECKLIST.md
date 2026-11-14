# Production Deployment Checklist

This checklist covers the steps needed to deploy DayWright to production.

---

## Nightwatch Configuration

### Environment Variables

Add to production `.env`:

```bash
NIGHTWATCH_LOG_LEVEL=warning
```

**Why:** This tells Nightwatch to only send `warning`, `error`, and `critical` logs to the dashboard, keeping `debug` and `info` logs on disk to reduce noise in production.

### Application Configuration

The following is already configured in `AppServiceProvider.php`:

- **User Context:** Captures user ID, UUID, name, email, and admin status for debugging
- **Unrecoverable Exception Handler:** Logs Nightwatch agent failures to the `daily` channel as a safety net

No additional code changes needed.

---

## Pre-Deployment Checklist

### Sentry Configuration

- [ ] `VITE_SENTRY_DSN` configured in environment
- [ ] `SENTRY_AUTH_TOKEN` added to CI/CD secrets
- [ ] `VITE_APP_ENV` set to `production` (or `staging`)
- [ ] Source maps upload tested in CI/CD
- [ ] Release tracking configured (when ready)

### Nightwatch Configuration

- [ ] `NIGHTWATCH_LOG_LEVEL=warning` set in production `.env`
- [ ] `NIGHTWATCH_TOKEN` configured in production `.env`
- [ ] Nightwatch user context verified in dashboard

### Infrastructure Configuration

- [ ] Server/environment provisioned
- [ ] Database configured
- [ ] Redis/cache configured
- [ ] Storage (S3 or equivalent) configured

### Application Configuration

- [ ] `.env` production variables set
- [ ] Laravel `APP_KEY` generated
- [ ] Database migrations tested
- [ ] Queue workers running via Laravel Horizon (`php artisan horizon`)
- [ ] Linux process supervisor (Supervisor or Docker) keeps Horizon running 24/7 with auto-restart
- [ ] Deployment script executes `php artisan horizon:terminate` for zero-downtime worker reloads
- [ ] PHP CLI `pcntl` extension verified enabled for worker signal/timeout enforcement
- [ ] Scheduler (cron) configured (`* * * * * cd /path/to/daywright && php artisan schedule:run >> /dev/null 2>&1`)
- [ ] File permissions correct
- [ ] Logging configured

### Security

- [ ] HTTPS enforced
- [ ] Security headers configured (CSP, HSTS, etc.)
- [ ] Database credentials in secrets (not code)
- [ ] API keys in secrets (not code)
- [ ] CORS configured correctly
- [ ] Rate limiting configured
- [ ] Input validation tested

### Monitoring

- [ ] Monitoring alarms configured
- [ ] Error tracking (Sentry) tested
- [ ] Uptime monitoring configured
- [ ] Log aggregation configured
- [ ] Performance monitoring configured

### Testing

- [ ] Staging environment tested
- [ ] Smoke tests passed
- [ ] E2E tests passed (Playwright)
- [ ] Load testing completed
- [ ] Security scan completed
- [ ] Phase 5 Zoom integration remediation: production/staging evidence recorded for the 11 completed remediation tickets (implementation plan removed after completion; see `local-docs/audits/2026-09-06/implementation/phase-4-production-readiness.md` for the sign-off format)

---

## Post-Deployment Checklist

### Verification

- [ ] Application loads successfully
- [ ] Login works
- [ ] 2FA works
- [ ] API endpoints respond
- [ ] Real-time features work (Echo/Pusher)
- [ ] File uploads work
- [ ] Cron jobs run
- [ ] Horizon dashboard accessible at `/horizon` by authorized `ADMIN_EMAILS` (verified under `config:cache`)
- [ ] All 4 Horizon supervisors active (`supervisor-auth`, `supervisor-default`, `supervisor-webhooks`, `supervisor-metrics`)
- [ ] Queue workers successfully process jobs across `critical`, `default`, `webhooks`, and `metrics` queues

### Sentry Verification

- [ ] Events appear in Sentry dashboard
- [ ] Environment label correct (production/staging)
- [ ] User context shows correct user ID
- [ ] User context cleared after logout
- [ ] Source maps resolve stack traces
- [ ] Session Replay works and masks correctly
- [ ] ignoreErrors filters working as expected

### Nightwatch Verification

- [ ] Events appear in Nightwatch dashboard
- [ ] Environment label correct (production/staging)
- [ ] User context shows correct user information (id, uuid, name, email, is_admin)
- [ ] Only warning/error/critical logs sent to dashboard (not debug/info)
- [ ] Log level filter working correctly

### Performance

- [ ] Page load times acceptable
- [ ] API response times acceptable
- [ ] Database query performance acceptable
- [ ] Cache hit rate good

### Backup & Recovery

- [ ] Database backups configured
- [ ] Backup restoration tested
- [ ] Redis maintenance quiesce procedure documented (`horizon:pause` -> `bgsave` -> restart -> `horizon:continue`)
- [ ] Disaster recovery plan documented
- [ ] Rollback procedure tested

### Operational References

- **Webhook inbox / Zoom recovery:** `docs/WEBHOOK_INBOX.md` is the operational source of truth for webhook behavior — timestamp normalization, provider watermark ordering, update/delete ties, timestamp-less deletes, and recovery commands (`webhooks:recover-pending`, `meetings:recover-ambiguous`, `meetings:recover-pending`, `subscriptions:recover-operations`). The completed Phase 5 Zoom remediation plan was removed after all 11 tickets were implemented; consult git history if implementation rationale is needed.

---

## Environment Variables Reference

### Required for Production

```bash
# Laravel
APP_ENV=production
APP_DEBUG=false
APP_KEY=
APP_URL=https://your-domain.com

# Database (MySQL 8.4 — matches CI and DEPLOYMENT.md)
DB_CONNECTION=mysql
DB_HOST=
DB_PORT=3306
DB_DATABASE=
DB_USERNAME=
DB_PASSWORD=

# Sentry
VITE_SENTRY_DSN=
VITE_APP_ENV=production
SENTRY_AUTH_TOKEN=  # CI/CD secret only (vite build source map upload)

# Nightwatch
NIGHTWATCH_TOKEN=
NIGHTWATCH_LOG_LEVEL=warning
NIGHTWATCH_REQUEST_SAMPLE_RATE=0.1

# Real-time broadcasting (Pusher / Laravel Echo)
PUSHER_APP_ID=
PUSHER_APP_KEY=
PUSHER_APP_SECRET=
PUSHER_APP_CLUSTER=
VITE_PUSHER_APP_KEY=
VITE_PUSHER_APP_CLUSTER=

# Queues & Horizon
QUEUE_CONNECTION=redis
ADMIN_EMAILS=admin@example.com
```

---

## Rollback Procedure

### If Deployment Fails

1. Stop deployment
2. Review logs
3. Fix the issue
4. Redeploy

### If Deployment Succeeds but App Broken

1. Revert to previous Git commit
2. Redeploy via CI/CD
3. Verify app works
4. Investigate failed deployment

### Quick Rollback Commands

```bash
# Revert to previous commit (creates a new commit that undoes the last one)
git revert HEAD --no-edit

# Redeploy
git push origin main
```

---

## Post-Launch Hardening & Technical Debt

Items identified during code review to address in dedicated maintenance PRs:

### 1. Reliable S3 File Deletion in Conversations (`ConversationService`)

- [ ] **Current State:** `ConversationService::deleteConversation()` uses Laravel's `defer(fn () => $this->deleteFileIfExists(...))` to delete attachments from Amazon S3.
- [ ] **Risk:** If the AWS S3 network connection times out or fails during `defer()`, the database record is already deleted and the file remains orphaned in S3 storage without retry.
- [ ] **Action:** Migrate file deletion to a queued job (e.g. `DeleteConversationAttachmentJob`) dispatched on the `default` queue with 3 retries and failed-job logging.

### 2. Playwright E2E Tests in CI/CD Pipeline

- [ ] **Current State:** Playwright auth smoke tests run locally against Sail / Docker.
- [ ] **Action:** Configure GitHub Actions with service containers (MySQL 8.4 + Redis) or point Playwright tests against an active Staging server URL so E2E checks run automatically on release or PR merges.
