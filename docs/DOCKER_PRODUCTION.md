# DayWright — Production Docker Compose Deployment, TLS Renewal & Rollback Guide

This guide covers single-EC2 production deployment using `compose.production.yaml`, Let's Encrypt TLS provisioning and webroot renewal, immutable image releases, and rollback procedures.

---

## 1. Architecture Overview

- **Services in `compose.production.yaml`:**
  - `web` (`daywright-web:${IMAGE_TAG:-latest}`): Public Nginx reverse proxy & TLS terminator (`80`, `443`).
  - `app` (`daywright-app:${IMAGE_TAG:-latest}`): Internal PHP-FPM application (`9000/tcp`, never exposed).
  - `horizon` (`daywright-app:${IMAGE_TAG:-latest}`): Internal Laravel Horizon queue worker.
  - `scheduler` (`daywright-app:${IMAGE_TAG:-latest}`): Internal Laravel `schedule:work` runner.
  - `redis` (`redis:7-alpine`): Internal Redis with AOF + RDB persistence (`6379/tcp`, never exposed).
- **External AWS Services:** AWS RDS for MySQL and AWS S3 (configured via `.env.production`).

---

## 2. Environment Files

Two separate files control the deployment. Compose reads them for different purposes.

| File                  | Read by                                                             | Purpose                                                                                                                                                                                                                                                              |
| --------------------- | ------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `.env` (project root) | Docker Compose at YAML parse time                                   | Compose interpolation variables: `DOMAIN`, `IMAGE_TAG`, `LETSENCRYPT_DIR`, `CERTBOT_WEBROOT`. These substitute into the YAML itself — for example `image: daywright-web:${IMAGE_TAG:-latest}` and `DOMAIN: ${DOMAIN:?...}` in the `web` container environment block. |
| `.env.production`     | `app`, `horizon`, `scheduler` containers at startup via `env_file:` | Laravel runtime configuration and application secrets: `APP_KEY`, `DB_HOST`, `DB_PASSWORD`, `REDIS_HOST`, `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, and all other application environment variables.                                                             |

> [!IMPORTANT]
> Docker Compose does **not** read `.env.production` for YAML interpolation. If you put `DOMAIN` only in `.env.production` the Nginx container will start with a blank `${DOMAIN}` and fail to load TLS certificates. `DOMAIN` must be in the project-root `.env` file (or exported as a shell variable before running `docker compose`).

Both files are Git-ignored. `.env.production.example` and `.env.example` are committed as templates.

---

## 3. Initial EC2 Setup & TLS Issuance

Ensure EC2 Security Groups allow inbound `80/tcp` and `443/tcp` from the internet, and outbound `3306/tcp` to the RDS Security Group.

```bash
# 1. Clone repository
git clone <your-repo-url> /home/ubuntu/daywright
cd /home/ubuntu/daywright

# 2. Create shared persistent ACME webroot directory on host.
#    Nginx will serve ACME challenge files from here; Certbot writes to it.
sudo mkdir -p /var/www/certbot
sudo chmod 755 /var/www/certbot

# 3. Provision initial Let's Encrypt certificate.
#    Port 80 is free at this point (Nginx not yet started), so --standalone works.
sudo certbot certonly --standalone \
  -d yourdomain.com -d www.yourdomain.com \
  --non-interactive --agree-tos -m admin@yourdomain.com

# 4. Configure Compose interpolation variables (.env) and app secrets (.env.production).
#    See "Environment Files" section above for the distinction between these two files.
export TAG=$(git rev-parse --short HEAD)
cat > .env <<EOF
DOMAIN=yourdomain.com
IMAGE_TAG=${TAG}
LETSENCRYPT_DIR=/etc/letsencrypt
CERTBOT_WEBROOT=/var/www/certbot
EOF

cp .env.production.example .env.production
nano .env.production   # Fill in APP_KEY, DB_*, AWS_*, PADDLE_*, MAIL_*, etc.
chmod 600 .env .env.production

# 5. Build immutable tagged images
#    Pass public VITE_* build arguments to BOTH builds so the Vue asset bundle
#    and the Vite manifest stay in sync across app and web images.
export VITE_ARGS="--build-arg VITE_PUSHER_APP_KEY=your_key \
  --build-arg VITE_PUSHER_APP_CLUSTER=mt1 \
  --build-arg VITE_ZOOM_SDK_KEY=your_zoom_key \
  --build-arg VITE_SENTRY_DSN=your_sentry_dsn"

docker build --target runtime ${VITE_ARGS} -t daywright-app:${TAG} -t daywright-app:latest .
docker build --target web     ${VITE_ARGS} -t daywright-web:${TAG} -t daywright-web:latest .

# 6. Validate Compose config, start Redis, verify RDS connectivity, run initial migrations,
#    and ONLY THEN start all services (chained with && so any failure stops startup).
#    (Override entrypoint so Artisan runs instead of starting php-fpm.)
#    Nginx then occupies port 80 and serves ACME challenges from /var/www/certbot.
docker compose -f compose.production.yaml config --quiet && \
docker compose -f compose.production.yaml up -d --wait redis && \
docker compose -f compose.production.yaml run --rm --no-deps --user root \
  --entrypoint chown app -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache && \
docker compose -f compose.production.yaml run --rm --no-deps --user www-data \
  --entrypoint php app artisan db:monitor && \
docker compose -f compose.production.yaml run --rm --no-deps --user www-data \
  --entrypoint php app artisan migrate --force && \
docker compose -f compose.production.yaml up -d

# 7. Switch renewal authenticator from standalone → webroot (Certbot 2.3.0+).
#
#    The initial --standalone issuance writes "authenticator = standalone" into
#    /etc/letsencrypt/renewal/yourdomain.com.conf. Auto-renewal would then try
#    to bind port 80 — which Nginx already holds — and silently fail.
#
#    certbot reconfigure updates the saved renewal configuration to use the
#    webroot authenticator without touching an existing certificate.
#
sudo certbot reconfigure \
  --cert-name yourdomain.com \
  --authenticator webroot \
  --webroot-path /var/www/certbot
```

> [!NOTE]
> **Certbot version requirement:** `certbot reconfigure` was introduced in Certbot 2.3.0 (February 2023), and staging-test behavior was refined in 2.9.0. Check your version with `certbot --version`. On Ubuntu 22.04+ the `snap`-installed Certbot is kept up to date automatically. On older systems installed via `apt`, upgrade with `sudo snap install --classic certbot` or consult the [Certbot documentation](https://eff-certbot.readthedocs.io/) for the equivalent manual procedure.

---

## 4. Automatic TLS Renewal & Zero-Downtime Nginx Reload

After completing step 7 of the initial setup (`certbot reconfigure`), Certbot's saved renewal configuration uses the **webroot authenticator**. All future automatic renewals write challenge tokens into `/var/www/certbot` on the host; Nginx serves them from the matching container mount over HTTP without vacating port 80. HTTPS traffic is never interrupted.

`compose.production.yaml` mounts the complete `/etc/letsencrypt` directory read-only into the Nginx container so `live/<domain>/*.pem` symlinks to `../../archive/<domain>/*.pem` remain valid after every renewal. Certbot rotates the archive files and updates the symlinks; Nginx reads the new certificate on reload without a container restart.

**Step A — Create the Nginx reload deploy hook** (runs automatically only when a certificate is actually renewed):

```bash
sudo mkdir -p /etc/letsencrypt/renewal-hooks/deploy
sudo tee /etc/letsencrypt/renewal-hooks/deploy/reload-daywright-nginx.sh > /dev/null <<'EOF'
#!/usr/bin/env sh
set -eu
docker exec daywright-web nginx -t
docker exec daywright-web nginx -s reload
EOF
sudo chmod +x /etc/letsencrypt/renewal-hooks/deploy/reload-daywright-nginx.sh
```

**Step B — Verify that automatic renewal AND the deploy hook will succeed** (safe, no rate-limit consumed):

```bash
sudo certbot renew --dry-run --run-deploy-hooks
```

A successful dry-run confirms that:

- Certbot uses the webroot authenticator (not standalone).
- Nginx serves the ACME challenge from `/var/www/certbot` correctly.
- The deploy hook syntax is valid.

**Step C — Verify Certbot's systemd timer is active** so renewals fire automatically:

```bash
sudo systemctl status certbot.timer
```

---

## 5. Deploying a New Release

Always tag images with the Git commit SHA (or release tag) so the previous image remains on disk for immediate rollback.

```bash
cd /home/ubuntu/daywright
git fetch origin --tags
# Option A: deploying a branch
git checkout main && git pull --ff-only
# Option B: deploying an immutable release tag (do NOT run git pull on a tag)
# git checkout v1.2.3

export NEW_TAG=$(git rev-parse --short HEAD)

# 1. Build new immutable images (pass identical VITE_* args to both images)
export VITE_ARGS="--build-arg VITE_PUSHER_APP_KEY=your_key \
  --build-arg VITE_PUSHER_APP_CLUSTER=mt1 \
  --build-arg VITE_ZOOM_SDK_KEY=your_zoom_key \
  --build-arg VITE_SENTRY_DSN=your_sentry_dsn"

docker build --target runtime ${VITE_ARGS} -t daywright-app:${NEW_TAG} .
docker build --target web     ${VITE_ARGS} -t daywright-web:${NEW_TAG} .

# 2. If this release includes database migrations, take an RDS snapshot FIRST!
#    Example via AWS CLI (or create a manual snapshot in the AWS RDS Console):
#    aws rds create-db-snapshot \
#      --db-instance-identifier daywright-prod \
#      --db-snapshot-identifier "daywright-pre-${NEW_TAG}-$(date +%Y%m%d%H%M)"

# 3. Clear any stale exported IMAGE_TAG (shell exports override .env) and update .env
unset IMAGE_TAG
sed -i "s/^IMAGE_TAG=.*/IMAGE_TAG=${NEW_TAG}/" .env

# 4. Normalize shared volume ownership (protects upgrades from older root-run volumes),
#    then run additive (backward-compatible) migrations from the new image BEFORE starting
#    the new containers, so old containers stay healthy while the schema updates.
#    Only recreate services if migrations succeed; on failure, stop the release and
#    follow the recovery procedure in Section 6.
docker compose -f compose.production.yaml run --rm --no-deps --user root \
  --entrypoint chown app -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache && \
docker compose -f compose.production.yaml run --rm --no-deps --user www-data \
  --entrypoint php app artisan migrate --force && \
docker compose -f compose.production.yaml up -d

# 5. Verify healthchecks and logs
docker compose -f compose.production.yaml ps
docker compose -f compose.production.yaml logs --tail=50 app horizon web
```

---

## 6. Rollback Procedure & Database Recovery Considerations

### Image Rollback (Application & Web Containers)

If the new release fails healthchecks or introduces a runtime regression:

```bash
cd /home/ubuntu/daywright

# 1. List available local immutable image tags
docker images daywright-app --format "table {{.Tag}}\t{{.CreatedAt}}\t{{.Size}}"

# 2. Clear any stale exported IMAGE_TAG and set IMAGE_TAG in .env back to the previous known-good tag
unset IMAGE_TAG
sed -i "s/^IMAGE_TAG=.*/IMAGE_TAG=<PREVIOUS_TAG>/" .env

# 3. Recreate containers using the previous image
docker compose -f compose.production.yaml up -d
docker compose -f compose.production.yaml ps
```

### Critical Database Migration Warning

> **Restoring an older application image does NOT reverse database migrations.**
> `php artisan migrate --force` modifies the external AWS RDS schema in place.

Safe migration & recovery rules:

1. **Always take an RDS snapshot before running `php artisan migrate --force`** on any release that alters tables/columns.
2. **Prefer backward-compatible (expand-and-contract) migrations** — add nullable columns or new tables first, deploy code, and only drop old columns in a later release once rollback is no longer needed.
3. **If a migration fails or breaks the previous app version:**
   - **First, inspect migration status** using a one-off container of the release image that introduced the migration (`<NEW_TAG>`) — set `IMAGE_TAG=<NEW_TAG>` explicitly on the command so `.env` changes from the image rollback section above cannot select the older image, and do **not** use `exec app`, because on a failed release `app` is still running `<PREVIOUS_TAG>`:
     ```bash
     IMAGE_TAG=<NEW_TAG> docker compose -f compose.production.yaml run --rm --no-deps --user www-data \
       --entrypoint php app artisan migrate:status
     ```
   - For minor additive migrations where a new migration actually completed and has a verified non-destructive `down()` method, roll back using that same `IMAGE_TAG=<NEW_TAG>` one-off command _before_ switching back to `<PREVIOUS_TAG>`:
     ```bash
     IMAGE_TAG=<NEW_TAG> docker compose -f compose.production.yaml run --rm --no-deps --user www-data \
       --entrypoint php app artisan migrate:rollback --step=1 --force
     ```
   - For destructive or irreversible schema/data migrations, **stop writes first** (`docker compose -f compose.production.yaml stop web horizon scheduler` or enable maintenance mode), **restore the pre-migration AWS RDS snapshot** (and update `DB_HOST` in `.env.production` if restoring to a new RDS instance endpoint), and reconcile any critical post-snapshot writes if needed.
   - **Always restore `IMAGE_TAG` after an aborted release:** run `unset IMAGE_TAG && sed -i "s/^IMAGE_TAG=.*/IMAGE_TAG=<PREVIOUS_TAG>/" .env` so a subsequent `docker compose up -d` does not unintentionally start the failed new image, then restart/verify services as needed.
