# AWS Production Deployment Checklist

This checklist covers the steps needed to deploy DayWright to AWS with Sentry release tracking.

---

## Sentry Release Tracking (Deferred)

### When to Implement

Implement this once you decide your AWS deployment strategy.

### Recommended Approach: Build in GitHub Actions

**Why build in GitHub Actions instead of on AWS:**

- AWS servers don't need Node.js or build tools installed
- GitHub Actions automatically has the Git SHA (`${{ github.sha }}`)
- Sentry auth token stays in GitHub Secrets (never on AWS infrastructure)
- Consistent builds across environments

### Implementation Steps

#### Step 1: Update `vite.config.js`

Add release detection to the Sentry plugin configuration:

```javascript
// vite.config.js
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue2';
import path from 'path';
import { sentryVitePlugin } from '@sentry/vite-plugin';

// Detect release from CI/Forge environment or fallback
const release = process.env.VITE_SENTRY_RELEASE || process.env.GITHUB_SHA || process.env.SENTRY_RELEASE;

export default defineConfig({
  plugins: [
    laravel({
      input: ['resources/sass/app.scss', 'resources/js/app.js'],
      refresh: true,
    }),
    vue(),
    sentryVitePlugin({
      org: 'hik-tech',
      project: 'daywright',
      authToken: process.env.SENTRY_AUTH_TOKEN,
      cleanArtifacts: true,
      disable: !process.env.SENTRY_AUTH_TOKEN,
      release: release ? { name: release, setCommits: { auto: true } } : undefined,
    }),
  ],
  // ... rest of config
});
```

#### Step 2: Update `resources/js/sentry.js`

Add release to Sentry.init (already partially configured):

```javascript
export function initSentry(Vue, router) {
  if (isMonitored && import.meta.env.VITE_SENTRY_DSN) {
    Sentry.init({
      Vue,
      dsn: import.meta.env.VITE_SENTRY_DSN,
      environment: sentryEnvironment,
      release: import.meta.env.VITE_SENTRY_RELEASE, // Already added
      // ... rest of config
    });
  }
}
```

#### Step 3: Configure GitHub Actions

Create or update `.github/workflows/deploy.yml`:

```yaml
name: Deploy to AWS

on:
  push:
    branches: [main, production]

jobs:
  build-and-deploy:
    runs-on: ubuntu-latest

    steps:
      - uses: actions/checkout@v3

      - name: Setup Node.js
        uses: actions/setup-node@v3
        with:
          node-version: '18'

      - name: Install dependencies
        run: npm ci

      - name: Build frontend with Sentry release
        run: npm run build
        env:
          VITE_APP_ENV: production
          VITE_SENTRY_DSN: ${{ secrets.VITE_SENTRY_DSN }}
          VITE_SENTRY_RELEASE: ${{ github.sha }}
          SENTRY_AUTH_TOKEN: ${{ secrets.SENTRY_AUTH_TOKEN }}

      - name: Deploy to AWS S3
        run: aws s3 sync public/build s3://your-bucket-name --delete
        env:
          AWS_ACCESS_KEY_ID: ${{ secrets.AWS_ACCESS_KEY_ID }}
          AWS_SECRET_ACCESS_KEY: ${{ secrets.AWS_SECRET_ACCESS_KEY }}
          AWS_REGION: ${{ secrets.AWS_REGION }}

      - name: Invalidate CloudFront cache
        run: aws cloudfront create-invalidation --distribution-id YOUR_DISTRIBUTION_ID --paths "/*"
        env:
          AWS_ACCESS_KEY_ID: ${{ secrets.AWS_ACCESS_KEY_ID }}
          AWS_SECRET_ACCESS_KEY: ${{ secrets.AWS_SECRET_ACCESS_KEY }}
          AWS_REGION: ${{ secrets.AWS_REGION }}
```

#### Step 4: Add GitHub Secrets

Add these secrets to your GitHub repository settings:

- `VITE_SENTRY_DSN` - Your Sentry DSN
- `SENTRY_AUTH_TOKEN` - Sentry auth token (from Sentry project settings)
- `AWS_ACCESS_KEY_ID` - AWS access key
- `AWS_SECRET_ACCESS_KEY` - AWS secret key
- `AWS_REGION` - AWS region (e.g., `us-east-1`)

### Alternative: Build on AWS

If you prefer to build on AWS (not recommended):

#### AWS CodeDeploy

Add to your `appspec.yml` or deployment script:

```bash
export VITE_SENTRY_RELEASE=$(git rev-parse --short HEAD)
export SENTRY_AUTH_TOKEN="your_token"
npm run build
```

#### AWS Elastic Beanstalk

Add to `.ebextensions/build.config`:

```yaml
commands:
  01_build_frontend:
    command: |
      export VITE_SENTRY_RELEASE=$(git rev-parse --short HEAD)
      export SENTRY_AUTH_TOKEN="your_token"
      npm run build
```

---

## AWS Deployment Options

### Option 1: S3 + CloudFront (Recommended for Static Assets)

**Best for:** Frontend-only or Laravel with API-only backend

**Pros:**

- Simple setup
- Fast global CDN
- Cost-effective
- Automatic HTTPS

**Cons:**

- Need separate backend deployment
- Dynamic routing requires Lambda@Edge

**Steps:**

1. Create S3 bucket for static assets
2. Configure CloudFront distribution
3. Upload `public/build` to S3
4. Configure Lambda@Edge for SPA routing (if needed)

---

### Option 2: ECS (Elastic Container Service)

**Best for:** Dockerized Laravel + Vue

**Pros:**

- Containerized deployment
- Auto-scaling
- Easy rollback
- Good for microservices

**Cons:**

- More complex setup
- Need to build Docker images
- Higher learning curve

**Steps:**

1. Dockerize Laravel application
2. Build Docker image with frontend assets included
3. Push to ECR (Elastic Container Registry)
4. Deploy to ECS

---

### Option 3: EC2 (Elastic Compute Cloud)

**Best for:** Traditional deployment

**Pros:**

- Full server control
- Familiar setup
- Can use existing Forge-like scripts

**Cons:**

- Manual scaling
- Server maintenance
- Higher operational overhead

**Steps:**

1. Launch EC2 instance
2. Install PHP, Node.js, Nginx
3. Deploy Laravel code
4. Run `npm run build` on server
5. Configure Nginx

---

### Option 4: Elastic Beanstalk

**Best for:** Managed deployment

**Pros:**

- AWS-managed
- Easy scaling
- Built-in load balancing

**Cons:**

- Less control
- Platform-specific quirks
- Higher cost for small apps

**Steps:**

1. Create Elastic Beanstalk environment
2. Configure deployment script
3. Push code via Git or EB CLI
4. AWS handles the rest

---

## Pre-Deployment Checklist

### Sentry Configuration

- [ ] `VITE_SENTRY_DSN` configured in environment
- [ ] `SENTRY_AUTH_TOKEN` added to CI/CD secrets
- [ ] `VITE_APP_ENV` set to `production` (or `staging`)
- [ ] Source maps upload tested in CI/CD
- [ ] Release tracking configured (when ready)

### AWS Configuration

- [ ] AWS account and region selected
- [ ] IAM roles and permissions configured
- [ ] Security groups configured (allow only necessary ports)
- [ ] SSL/TLS certificates configured (use ACM)
- [ ] Database (RDS) configured if needed
- [ ] Redis (ElastiCache) configured if needed
- [ ] S3 buckets configured for storage

### Application Configuration

- [ ] `.env` production variables set
- [ ] Laravel `APP_KEY` generated
- [ ] Database migrations tested
- [ ] Queue workers configured
- [ ] Scheduler (cron) configured
- [ ] File permissions correct
- [ ] Logging configured (CloudWatch)

### Security

- [ ] HTTPS enforced
- [ ] Security headers configured (CSP, HSTS, etc.)
- [ ] Database credentials in secrets (not code)
- [ ] API keys in secrets (not code)
- [ ] CORS configured correctly
- [ ] Rate limiting configured
- [ ] Input validation tested

### Monitoring

- [ ] CloudWatch alarms configured
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

### Performance

- [ ] Page load times acceptable
- [ ] API response times acceptable
- [ ] Database query performance acceptable
- [ ] CloudFront cache hit rate good

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

# AWS (if applicable)
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_REGION=
AWS_BUCKET=
```

---

## Rollback Procedure

### If Deployment Fails

1. Stop deployment
2. Check CloudWatch logs
3. Fix the issue
4. Redeploy

### If Deployment Succeeds but App Broken

1. Revert to previous Git commit
2. Redeploy via CI/CD
3. Verify app works
4. Investigate failed deployment

### Quick Rollback Commands

```bash
# Revert to previous commit
git revert HEAD

# Or checkout previous commit
git checkout previous-commit-hash

# Redeploy
git push origin main
```

---

## Useful Links

- [AWS Documentation](https://docs.aws.amazon.com/)
- [Sentry Documentation](https://docs.sentry.io/)
- [Laravel Deployment](https://laravel.com/docs/deployment)
- [GitHub Actions Documentation](https://docs.github.com/en/actions)
