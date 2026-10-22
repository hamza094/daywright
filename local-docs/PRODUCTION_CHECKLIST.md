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
- [ ] Nightwatch DSN configured
- [ ] User context verified in dashboard

### Infrastructure Configuration

- [ ] Server/environment provisioned
- [ ] Database configured
- [ ] Redis/cache configured
- [ ] Storage (S3 or equivalent) configured

### Application Configuration

- [ ] `.env` production variables set
- [ ] Laravel `APP_KEY` generated
- [ ] Database migrations tested
- [ ] Queue workers configured
- [ ] Scheduler (cron) configured
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
- [ ] Queue workers process jobs

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
- [ ] Disaster recovery plan documented
- [ ] Rollback procedure tested

---

## Environment Variables Reference

### Required for Production

```bash
# Laravel
APP_ENV=production
APP_DEBUG=false
APP_KEY=
APP_URL=https://your-domain.com

# Database
DB_CONNECTION=pgsql
DB_HOST=
DB_PORT=5432
DB_DATABASE=
DB_USERNAME=
DB_PASSWORD=

# Sentry
VITE_SENTRY_DSN=
VITE_APP_ENV=production
VITE_SENTRY_RELEASE=  # Set by CI/CD
SENTRY_AUTH_TOKEN=  # CI/CD secret only

# Nightwatch
NIGHTWATCH_LOG_LEVEL=warning
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
