# DayWright Production Deployment Guide

This guide provides detailed instructions for deploying DayWright to production environments.

## Table of Contents

- [Server Requirements](#server-requirements)
- [Environment Configuration](#environment-configuration)
- [Database Setup](#database-setup)
- [Redis Configuration](#redis-configuration)
- [Queue Workers](#queue-workers)
- [Scheduler Configuration](#scheduler-configuration)
- [Supervisor Setup](#supervisor-setup)
- [Deployment Process](#deployment-process)
- [Monitoring and Maintenance](#monitoring-and-maintenance)
- [Troubleshooting](#troubleshooting)

## Server Requirements

### Software Requirements

- **PHP**: 8.3 or higher
- **Database**: MySQL 8.0+ or PostgreSQL 14+
- **Redis**: 6.0+ (required for cache, queue locks, scheduler locks, rate limiting)
- **Composer**: 2.x
- **Node.js**: 20+ and npm 10+
- **Web Server**: Nginx or Apache with mod_rewrite
- **Supervisor**: For managing queue workers (recommended)

### PHP Extensions

Required PHP extensions:

- BCMath
- Ctype
- cURL
- DOM
- Fileinfo
- JSON
- Mbstring
- OpenSSL
- PCRE
- PDO
- Tokenizer
- XML

## Environment Configuration

### Production Environment Variables

Copy `.env.example` to `.env` and configure the following key variables:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=daywright
DB_USERNAME=your_db_user
DB_PASSWORD=your_db_password

# Queue - Use Redis with Laravel Horizon in production
QUEUE_CONNECTION=redis

# Cache and Session - Use Redis for production
CACHE_DRIVER=redis
SESSION_DRIVER=redis
SESSION_LIFETIME=120

# Redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Mail Configuration
MAIL_MAILER=smtp
MAIL_HOST=your-mail-server
MAIL_PORT=587
MAIL_USERNAME=your-mail-username
MAIL_PASSWORD=your-mail-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@your-domain.com
MAIL_FROM_NAME="${APP_NAME}"

# SMS Configuration (if using Vonage)
VONAGE_KEY=your-api-key
VONAGE_SECRET=your-api-secret
VONAGE_SMS_FROM=your-phone-number

# Zoom Integration
ZOOM_CLIENT_ID=your-zoom-client-id
ZOOM_CLIENT_SECRET=your-zoom-client-secret
ZOOM_WEBHOOK_SECRET_TOKEN=your-zoom-webhook-secret-token

# Real-time (Pusher / Laravel Echo)
PUSHER_APP_ID=your-pusher-app-id
PUSHER_APP_KEY=your-pusher-app-key
PUSHER_APP_SECRET=your-pusher-app-secret
PUSHER_APP_CLUSTER=mt1
VITE_PUSHER_APP_KEY="${PUSHER_APP_KEY}"
VITE_PUSHER_APP_CLUSTER="${PUSHER_APP_CLUSTER}"

# Frontend error monitoring (Sentry)
VITE_APP_ENV=production
VITE_SENTRY_DSN=your-sentry-dsn
# SENTRY_AUTH_TOKEN is a CI/CD secret only — used by vite build for source map upload, never in the app runtime env

# Backend monitoring (Laravel Nightwatch)
NIGHTWATCH_TOKEN=your-nightwatch-token
NIGHTWATCH_REQUEST_SAMPLE_RATE=0.1

# Admin Emails (comma-separated)
ADMIN_EMAILS=admin@example.com,security@example.com
```

### Security Considerations

- Never commit `.env` to version control
- Use strong, unique passwords for database and Redis
- Set `APP_DEBUG=false` in production
- Use HTTPS for all production URLs
- Configure proper file permissions (755 for directories, 644 for files)

## Database Setup

### Initial Setup

```bash
# Run migrations
php artisan migrate --force

# Seed production data if needed
php artisan db:seed --force
```

### Database Backup Strategy

Set up automated backups:

```bash
# Daily backup example (add to cron)
# Use environment variables or a MySQL defaults file for credentials
0 2 * * * mysqldump --defaults-extra-file=/etc/daywright/mysql-backup.cnf daywright > "/backups/daywright_$(date +\%Y\%m\%d).sql"
```

Replace `daywright` with the database name used by your deployment. Store credentials in the referenced defaults file with owner-only permissions (for example, mode `600`); do not place the password in the command line.

## Redis Configuration

### Why Redis is Required

Redis is **required** for production deployments because it powers:

- Cache driver
- Session storage
- Queue locks (for `withoutOverlapping` middleware)
- Scheduler locks (prevents duplicate scheduled task runs)
- Rate limiting
- Job overlap prevention

### Redis Configuration

Ensure Redis is running and accessible:

```bash
# Check Redis status
redis-cli ping
# Should return: PONG

# Test connection
redis-cli -h 127.0.0.1 -p 6379
```

### Redis Persistence

Configure Redis persistence in `redis.conf`:

```ini
save 900 1
save 300 10
save 60 10000

# Optional: For stronger queue durability with minimal job loss on unexpected restarts
appendonly yes
appendfsync everysec
```

This saves the dataset to disk via RDB snapshots if:

- At least 1 key changed in 900 seconds
- At least 10 keys changed in 300 seconds
- At least 10000 keys changed in 60 seconds

> **Note**: Standard RDB snapshots are sufficient for DayWright because scheduled and recovery tasks are re-dispatchable and idempotent. For stronger durability with a minimal loss window (up to ~1 second on abrupt failure), enable AOF (`appendonly yes` with `appendfsync everysec`).

### Redis Maintenance & Queue Quiescing

When restarting Redis or performing maintenance on the Redis server in production, **quiesce** producers and workers first to avoid losing in-flight jobs or notification dispatches:

1. **Quiesce job producers (Enter Maintenance Mode)**:
   Prevent web traffic and scheduler commands from enqueueing new jobs while Redis is serviced:

   > **Note**: In multi-server deployments behind a load balancer, run `php artisan down` on **every web node** (or configure your load balancer to redirect traffic to a maintenance page) because Laravel's file-based maintenance mode affects only the local node.

   ```bash
   php artisan down --retry=60
   ```

2. **Pause Horizon queue workers on all worker nodes**:

   > **Note**: `horizon:pause` signals master supervisors only on the local host. Run this command on **every** Horizon worker host or container in multi-node deployments.

   ```bash
   php artisan horizon:pause
   ```

3. **Wait for active in-flight jobs to finish**:
   Verify currently active/in-flight jobs have finished via the Horizon UI dashboard (`/horizon/dashboard`) or check that active workers have drained to `0`.

   > **Note**: Once workers are paused, pending jobs remain safely preserved in Redis and will resume when workers continue. Do not wait for pending queue lists to reach zero while workers are paused.

   If you need to inspect queue lengths directly in Redis, check Laravel's actual queue keys across all four configured queues (accounting for any configured Redis client prefix, e.g. `daywright_database_`):

   ```bash
   # Inspect pending job lists across all 4 configured queues (append client prefix if configured)
   redis-cli llen queues:critical
   redis-cli llen queues:default
   redis-cli llen queues:webhooks
   redis-cli llen queues:metrics
   ```

4. **Trigger background snapshot and verify success**:
   `BGSAVE` returns immediately while the dump finishes in the background. Wait until `rdb_bgsave_in_progress` returns `0` and verify that `rdb_last_bgsave_status` reports `ok` before restarting:

   ```bash
   redis-cli bgsave
   # Poll until background save completes
   while [ $(redis-cli info persistence | grep -c "rdb_bgsave_in_progress:1") -eq 1 ]; do sleep 1; done
   # Confirm snapshot succeeded without errors
   redis-cli info persistence | grep "rdb_last_bgsave_status:ok"
   ```

5. **Perform Redis maintenance or restart**:

   ```bash
   sudo systemctl restart redis
   ```

6. **Resume queue workers on all nodes and bring site back up**:

   ```bash
   # Resume workers across all worker hosts
   php artisan horizon:continue

   # Bring web nodes back online across all web hosts
   php artisan up
   ```

## Queue Workers (Laravel Horizon)

### Queue Architecture & Supervisor Isolation

DayWright uses **Laravel Horizon** over Redis with four dedicated supervisors for capacity isolation:

| Queue      | Supervisor            | Purpose                                                                | Sizing Strategy                                |
| :--------- | :-------------------- | :--------------------------------------------------------------------- | :--------------------------------------------- |
| `critical` | `supervisor-auth`     | Password resets, email verification, security notices                  | 1 dedicated process (zero waiting)             |
| `default`  | `supervisor-default`  | Team messages, notifications, Zoom cancellations & recoveries          | 2-4 processes (auto-scaling on wait time)      |
| `webhooks` | `supervisor-webhooks` | Zoom webhook event processing (`ProcessZoomWebhookInbox`)              | 1 dedicated process                            |
| `metrics`  | `supervisor-metrics`  | Nightly project health score calculations (`RecalculateProjectHealth`) | 1 fixed process (hard-capped to protect MySQL) |

Horizon worker configuration is centralized in `config/horizon.php`.

### Running Horizon

In development (via Laravel Sail):

```bash
./vendor/bin/sail artisan horizon
```

In production (managed via Linux Supervisor or Docker):

```bash
php artisan horizon
```

### PCNTL Requirement & Job Timeout Precedence

- **PCNTL Extension Required**: The production PHP CLI runtime running queue workers MUST have the `pcntl` extension enabled. PCNTL is required for Laravel to handle signals (`SIGALRM`) and enforce timeouts. Without PCNTL, job timeouts are not enforced.
- **Job Timeout Precedence**: Individual jobs can define a `$timeout` property that overrides the worker CLI `--timeout=120`. Specifically, `RecoverZoomMeetingOperationJob` defines `public int $timeout = 90;`, ensuring it terminates after 90 seconds even though the worker allows up to 120 seconds.
- **Provider Request Timeout**: Zoom's HTTP client uses a 30-second request timeout. Because blocking network I/O may delay signal delivery, low client timeouts prevent workers from blocking past their deadlines.
- **Supervisor Role**: Supervisor’s role is strictly process supervision and restarting workers when Laravel exits on timeout (`autorestart=true`). Do not treat `stopwaitsecs` as a per-job timeout; the configured 3600 seconds is shutdown grace.
- **Provider Fencing Boundary**: This timing hierarchy (Zoom HTTP timeout 30s < job timeout 90s < worker timeout 120s < cache lock 120s < retry_after 150s < lease 300s) provides a bounded operational guarantee against stale workers. It is not absolute provider fencing, as Zoom does not receive or validate local claim tokens.

### Important: Timeout vs Retry After

The queue `retry_after` configuration is set to 150 seconds. Worker `--timeout` must be **lower** than this to prevent jobs from being retried while still running:

```text
retry_after (150s) > worker timeout (120s) > recovery job timeout (90s) ✅ CORRECT - safe
retry_after (90s) < worker timeout (120s)  ❌ WRONG - causes duplicate executions
```

All queue drivers (including Beanstalkd and SQS if used) must have retry / visibility timeouts configured above 120 seconds.

## Scheduler Configuration

### Cron Job

Add this to your crontab (`crontab -e`):

```bash
* * * * * cd /path/to/daywright && php artisan schedule:run >> /dev/null 2>&1
```

### Scheduled Tasks

The scheduler (`app/Console/Kernel.php`) handles the following, all guarded by `onOneServer()` and `withoutOverlapping()`:

**Every minute / few minutes:**

- `schedule:message` — dispatches scheduled team messages (only when messages are pending)
- `tasks:notify` — sends due task notifications (every 2 minutes, only when due)
- `webhooks:recover-pending` — retries incomplete webhook inbox events (`withoutOverlapping(5)`)
- `meetings:recover-ambiguous --limit=25` — reconciles Zoom meetings with uncertain creation state (`withoutOverlapping(15)`, runs in background)
- `meetings:recover-pending --limit=25` — retries pending Zoom meeting operations (`withoutOverlapping(5)`, background)
- `subscriptions:recover-operations --limit=25` — retries pending Paddle subscription operations (`withoutOverlapping(5)`, background)

**Every 30 minutes:**

- `meetings:check-unsent-notifications` — recovers meeting notifications that were not marked sent

**Daily:**

- `remove:abandon` — removes abandoned records
- `queue:prune-batches --hours=48 --unfinished=72` and `queue:prune-failed --hours=720` — prunes old queue batches and failed jobs (failed jobs kept 30 days)
- `backup:clean` (01:00) and `backup:run` (01:30) — database backup cleanup and creation
- `telescope:prune --hours=10` — prunes Telescope entries
- `user:profile-delete` — deletes user profiles scheduled for removal
- `projects:recalculate-health --queue=metrics` (02:00) — nightly project health score recalculation on the `metrics` queue

### Scheduler Locks & Incident Recovery

The scheduler uses Redis locks (`onOneServer()`) to prevent duplicate executions across nodes. Shared Redis is required for production cache and locks; file-based cache is not suitable for multi-node deployments.

During incident recovery, if an abandoned lock blocks scheduled commands after an abnormal termination, clear scheduler locks using:

```bash
php artisan schedule:clear-cache
```

## Supervisor Setup (Keeping Horizon Alive)

In production, use Linux Supervisor (or a Docker container restart policy) to manage the single Horizon master process. Horizon internally manages all 4 worker supervisors and pools.

### Install Supervisor

```bash
# Ubuntu/Debian
sudo apt-get install supervisor

# CentOS/RHEL
sudo yum install supervisor
```

### Configuration

Create `/etc/supervisor/conf.d/daywright-horizon.conf`:

```ini
[program:daywright-horizon]
process_name=%(program_name)s
command=php /path/to/daywright/artisan horizon
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/daywright-horizon.log
stopwaitsecs=3600
```

### Start Supervisor

```bash
# Read new configuration
sudo supervisorctl reread

# Update Supervisor
sudo supervisorctl update

# Start Horizon
sudo supervisorctl start daywright-horizon

# Check status
sudo supervisorctl status
```

## Deployment Process

### Database to Redis Queue Cutover (One-Time Migration)

If migrating an existing deployment from `QUEUE_CONNECTION=database` to Redis/Horizon:

1. **Drain pending database queue jobs** across all queues before switching:
   ```bash
   php artisan queue:work database --queue=critical,default,webhooks,metrics --stop-when-empty
   ```
2. Verify the `jobs` table is completely empty.
3. Switch `.env` to `QUEUE_CONNECTION=redis` and proceed with standard deployment.

### Standard Deployment

1. **Deploy code** to server (git pull, CI/CD, etc.)

2. **Install dependencies**:

   ```bash
   composer install --no-dev --optimize-autoloader
   npm ci
   npm run build
   npm prune --omit=dev
   ```

3. **Run migrations**:

   ```bash
   php artisan migrate --force
   ```

4. **Cache configuration and routes**:

   ```bash
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```

5. **Restart queue workers** (gracefully reloads Horizon with new code):

   ```bash
   php artisan horizon:terminate
   ```

6. **Clear any running scheduler tasks** (if needed):
   ```bash
   php artisan schedule:interrupt
   ```

### Zero-Downtime Deployment

For zero-downtime deployments:

1. Deploy to a new directory
2. Switch symlink to new directory
3. Restart workers after symlink switch
4. Old directory can be removed after verification

## E2E Smoke Tests (Staging)

The Playwright suite (`tests/e2e`) requires deterministic accounts that do NOT
exist in the faker-generated `UserSeeder` output. After deploying to staging:

```bash
# 1. Seed the dedicated E2E accounts (idempotent, safe to re-run)
php artisan db:seed --class=E2eUserSeeder

# 2. Run the suite against the staging URL
PLAYWRIGHT_BASE_URL="https://<staging-domain>" \
E2E_USER_EMAIL="e2e-user-a@daywright.test" \
E2E_USER_B_EMAIL="e2e-user-b@daywright.test" \
E2E_USER_PASSWORD="<password>" \
npx playwright test tests/e2e --project=chromium
```

`E2eUserSeeder` is intentionally excluded from `DatabaseSeeder` so known
credentials are never created by a routine `db:seed`. It creates `e2e-user-a`
(with fixture projects) and `e2e-user-b` (a separate tenant for the
isolation spec). Default credentials are `e2e-user-a@daywright.test` /
`e2e-user-b@daywright.test` with password `Berry@999`; override via the env
vars above on both the seeder and the test runner.

## Monitoring and Maintenance

### Failed Job Monitoring

Monitor failed jobs regularly:

```bash
# View failed jobs
php artisan queue:failed

# View specific failed job details
php artisan queue:failed [id]

# Retry a specific failed job
php artisan queue:retry [id]

# Retry all failed jobs
php artisan queue:retry all

# Flush all failed jobs
php artisan queue:flush
```

### Failed Job Retention

Failed jobs are automatically pruned after 30 days (`queue:prune-failed --hours=720`) by the scheduler. To adjust this, modify the scheduler command in `app/Console/Kernel.php`.

### Horizon Dashboard & Queue Monitoring

- **Horizon Dashboard**: Access real-time throughput, supervisor status, queue wait times, and job metrics at:
  `https://your-domain.com/horizon`
  _(Access is protected by `ADMIN_EMAILS` in production)_
- **CLI Horizon Status**:
  ```bash
  php artisan horizon:status
  ```
- **Failed Jobs**:
  Failed jobs appear directly in the Horizon UI and are recorded in the `failed_jobs` database table.

### Log Monitoring

Important log locations:

- Application logs: `storage/logs/laravel.log`
- Queue worker logs: `/var/log/daywright-worker-*.log`
- Zoom integration logs: `storage/logs/zoom.log` (if configured)

### Health Checks

Set up health checks for:

- Database connectivity
- Redis connectivity
- Queue worker status
- Scheduler running status

## Troubleshooting

### Jobs Not Processing

**Symptoms**: Jobs queued but not executing

**Solutions**:

1. Check if workers are running: `sudo supervisorctl status`
2. Check worker logs for errors
3. Verify Redis is running: `redis-cli ping`
4. Check queue connection in `.env`

### Scheduler Not Running

**Symptoms**: Scheduled messages not sending

**Solutions**:

1. Check crontab: `crontab -l`
2. Verify cron is running: `systemctl status cron`
3. Check scheduler logs: `storage/logs/scheduler.log`
4. Ensure Redis is running for locks

### Redis Connection Issues

**Symptoms**: Cache/queue errors, locks not working

**Solutions**:

1. Check Redis status: `systemctl status redis`
2. Test connection: `redis-cli ping`
3. Verify Redis configuration in `.env`
4. Check Redis logs: `/var/log/redis/redis-server.log`

### High Memory Usage

**Symptoms**: Workers consuming too much memory

**Solutions**:

1. Reduce `numprocs` in Supervisor config
2. Lower `--max-time` to restart workers more frequently
3. Check for memory leaks in custom jobs
4. Use `--memory` flag to limit memory per worker

### Jobs Timing Out

**Symptoms**: Jobs failing with timeout errors

**Solutions**:

1. Increase `--timeout` in worker command (but keep < retry_after)
2. Check if job is actually slow or stuck
3. Add logging to identify slow operations
4. Consider breaking large jobs into smaller chunks

## Additional Resources

- [Laravel Queue Documentation](https://laravel.com/docs/queues)
- [Laravel Scheduler Documentation](https://laravel.com/docs/scheduling)
- [Supervisor Documentation](http://supervisord.org/)
- [Redis Documentation](https://redis.io/documentation)
