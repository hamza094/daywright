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

- **PHP**: 8.2 or higher
- **Database**: MySQL 8.0+ or PostgreSQL 14+
- **Redis**: 6.0+ (required for cache, queue locks, scheduler locks, rate limiting)
- **Composer**: 2.x
- **Node.js**: 18+ and npm 9+
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

# Queue - IMPORTANT: Do not use 'sync' in production
QUEUE_CONNECTION=database
# Alternative: QUEUE_CONNECTION=redis

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
VONAGE_API_KEY=your-api-key
VONAGE_API_SECRET=your-api-secret
VONAGE_SMS_FROM=your-phone-number

# Zoom Integration
ZOOM_API_KEY=your-zoom-api-key
ZOOM_API_SECRET=your-zoom-api-secret

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
0 2 * * * mysqldump -u $DB_USER -p$DB_PASSWORD $DB_DATABASE > /backups/daywright_$(date +\%Y\%m\%d).sql
```

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
```

This saves the dataset to disk if:

- At least 1 key changed in 900 seconds
- At least 10 keys changed in 300 seconds
- At least 10000 keys changed in 60 seconds

## Queue Workers

### Queue Architecture

DayWright uses multiple queues for different priorities:

| Queue      | Purpose                                                 | Priority |
| ---------- | ------------------------------------------------------- | -------- |
| `critical` | Password resets, email verification, auth notifications | Highest  |
| `default`  | Messages, notifications, Zoom operations                | Normal   |
| `metrics`  | Analytics and reporting                                 | Lower    |
| `webhooks` | Zoom webhook processing                                 | Normal   |

### Worker Commands

Run these workers using Supervisor (see Supervisor Setup section):

```bash
# Critical and default queues
php artisan queue:work database --queue=critical,default --sleep=3 --tries=3 --timeout=120 --max-time=3600

# Metrics queue
php artisan queue:work database --queue=metrics --sleep=3 --tries=2 --timeout=120 --max-time=3600

# Webhooks queue
php artisan queue:work database --queue=webhooks --sleep=3 --tries=3 --timeout=120 --max-time=3600
```

### Worker Flags Explained

- `--queue`: Queue priority order (left = highest priority)
- `--sleep=3`: Wait 3 seconds between jobs if queue is empty
- `--tries=3`: Default retry count (jobs can override this)
- `--timeout=120`: Maximum execution time per job (must be < retry_after)
- `--max-time=3600`: Restart worker after 1 hour (prevents memory leaks)

### Important: Timeout vs Retry After

The queue `retry_after` configuration is set to 150 seconds. Worker `--timeout` must be **lower** than this to prevent jobs from being retried while still running:

```text
retry_after (150s) > worker timeout (120s) ✅ CORRECT - safe
retry_after (90s) < worker timeout (120s)  ❌ WRONG - causes duplicate executions
```

## Scheduler Configuration

### Cron Job

Add this to your crontab (`crontab -e`):

```bash
* * * * * cd /path/to/daywright && php artisan schedule:run >> /dev/null 2>&1
```

### Scheduled Tasks

The scheduler handles:

- **Scheduled message dispatching**: Sends messages at their scheduled delivery time
- **Failed job pruning**: Automatically removes failed jobs older than 7 days
- **Other periodic tasks**: Future scheduled tasks

### Scheduler Locks

The scheduler uses Redis locks to prevent duplicate executions. Ensure Redis is running before the scheduler starts.

## Supervisor Setup

Supervisor keeps queue workers running and restarts them if they crash.

### Install Supervisor

```bash
# Ubuntu/Debian
sudo apt-get install supervisor

# CentOS/RHEL
sudo yum install supervisor
```

### Configuration

Create `/etc/supervisor/conf.d/daywright-worker.conf`:

```ini
[program:daywright-worker-critical]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/daywright/artisan queue:work database --queue=critical,default --sleep=3 --tries=3 --timeout=120 --max-time=3600
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/daywright-worker-critical.log
stopwaitsecs=3600

[program:daywright-worker-metrics]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/daywright/artisan queue:work database --queue=metrics --sleep=3 --tries=2 --timeout=120 --max-time=3600
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/log/daywright-worker-metrics.log
stopwaitsecs=3600

[program:daywright-worker-webhooks]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/daywright/artisan queue:work database --queue=webhooks --sleep=3 --tries=3 --timeout=120 --max-time=3600
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/log/daywright-worker-webhooks.log
stopwaitsecs=3600
```

### Start Supervisor

```bash
# Read new configuration
sudo supervisorctl reread

# Update Supervisor
sudo supervisorctl update

# Start all workers
sudo supervisorctl start daywright-worker-*

# Check status
sudo supervisorctl status
```

## Deployment Process

### Standard Deployment

1. **Deploy code** to server (git pull, CI/CD, etc.)

2. **Install dependencies**:

   ```bash
   composer install --no-dev --optimize-autoloader
   npm ci --production
   npm run build
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

5. **Restart queue workers** (loads new code):

   ```bash
   php artisan queue:restart
   sudo supervisorctl restart daywright-worker-*
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

Failed jobs are automatically pruned after 7 days by the scheduler. To adjust this, modify the scheduler command in `app/Console/Kernel.php`.

### Queue Monitoring

Monitor queue depth:

```bash
# Check queue size (database queue)
php artisan tinker
>>> DB::table('jobs')->count();

# Check failed jobs count
>>> DB::table('failed_jobs')->count();
```

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
