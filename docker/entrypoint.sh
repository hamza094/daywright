#!/usr/bin/env sh
# ==============================================================================
# DayWright PHP-FPM Container Entrypoint
#
# Runs ONCE before PHP-FPM starts. Responsibilities:
#   1. Wait for Redis to be reachable (Horizon, cache, sessions all need it).
#   2. Warm Laravel's config / route / view caches (requires real env vars).
#   3. Hand off to PHP-FPM via exec (replaces this shell process).
#
# Intentionally does NOT run:
#   - php artisan migrate  → manual step during deployment
#   - php artisan db:seed  → destructive, never automatic
#   - php artisan horizon  → started by the separate 'horizon' service
# ==============================================================================

set -e

# ------------------------------------------------------------------------------
# 1. Redis readiness check
#
# Horizon, cache and sessions all depend on Redis being up. Fail fast with a
# clear error rather than letting PHP-FPM start and generate cryptic connection
# errors during the first request.
# ------------------------------------------------------------------------------
REDIS_HOST="${REDIS_HOST:-redis}"
REDIS_PORT="${REDIS_PORT:-6379}"
REDIS_RETRIES=30
REDIS_WAIT=2

echo "[entrypoint] Waiting for Redis at ${REDIS_HOST}:${REDIS_PORT} ..."
i=0
while ! php -r "
    \$r = @fsockopen('${REDIS_HOST}', ${REDIS_PORT}, \$errno, \$errstr, 2);
    if (!\$r) exit(1);
    fwrite(\$r, \"PING\r\n\");
    \$resp = fgets(\$r, 128);
    fclose(\$r);
    exit(strpos(\$resp, '+PONG') === false ? 1 : 0);
" 2>/dev/null; do
    i=$((i + 1))
    if [ "$i" -ge "$REDIS_RETRIES" ]; then
        echo "[entrypoint] ERROR: Redis not reachable after $((REDIS_RETRIES * REDIS_WAIT))s. Aborting."
        exit 1
    fi
    echo "[entrypoint] Redis not ready (attempt ${i}/${REDIS_RETRIES}), retrying in ${REDIS_WAIT}s ..."
    sleep "$REDIS_WAIT"
done
echo "[entrypoint] Redis is ready."

# ------------------------------------------------------------------------------
# 2. Laravel bootstrap caches
#
# Config cache bakes all env() calls so PHP-FPM workers never read the
# filesystem for configuration after boot. Route and view caches cut the
# first-request overhead significantly.
#
# These run AFTER the env vars are injected (via env_file in Compose), so the
# cached values are production-correct.
#
# Note: if any cache command fails (e.g. a misconfigured package throws during
# boot), we log the error but continue starting PHP-FPM. The app will still
# serve requests using runtime env() calls instead of cached values — a
# degraded but functional mode. Fix the underlying config error and redeploy.
# ------------------------------------------------------------------------------
echo "[entrypoint] Warming Laravel caches ..."
php artisan config:cache && \
php artisan route:cache && \
php artisan view:cache && \
echo "[entrypoint] Laravel caches warmed." || \
echo "[entrypoint] WARNING: Cache warm-up failed. PHP-FPM will start without cached config. Check logs."

# Ensure any compiled views or boot logs created above belong to www-data so
# php-fpm workers, horizon, and scheduler can read/write shared volumes cleanly.
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# ------------------------------------------------------------------------------
# 3. Hand off to PHP-FPM
#
# 'exec' replaces this shell process so PHP-FPM receives signals (SIGTERM,
# SIGQUIT) correctly from Docker stop / Compose graceful shutdown.
# ------------------------------------------------------------------------------
echo "[entrypoint] Starting PHP-FPM ..."
exec php-fpm -F
