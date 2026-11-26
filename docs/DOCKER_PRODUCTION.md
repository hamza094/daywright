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

# 5. Build immutable tagged images (and optionally tag :latest)
docker build --target runtime -t daywright-app:${TAG} -t daywright-app:latest .
docker build --target web     -t daywright-web:${TAG} -t daywright-web:latest .

# 6. Validate Compose config & start all services.
#    Nginx now occupies port 80 and serves ACME challenges from /var/www/certbot.
docker compose -f compose.production.yaml config --quiet
docker compose -f compose.production.yaml up -d

# 7. Switch renewal authenticator from standalone → webroot (Certbot 2.3.0+).
#
#    The initial --standalone issuance writes "authenticator = standalone" into
#    /etc/letsencrypt/renewal/yourdomain.com.conf.  Auto-renewal would then try
#    to bind port 80 — which Nginx already holds — and silently fail.
#
#    certbot reconfigure updates the saved renewal configuration to use the
#    webroot authenticator and validates the change against the Let's Encrypt
#    staging server (--staging) so no rate-limit is consumed.
#
sudo certbot reconfigure \
  --cert-name yourdomain.com \
  --authenticator webroot \
  --webroot-path /var/www/certbot \
  --staging

#    After reconfigure succeeds, verify that automatic renewal will work:
sudo certbot renew --dry-run

# 8. Verify RDS connectivity & run initial migrations manually
docker compose -f compose.production.yaml exec app php artisan db:monitor
docker compose -f compose.production.yaml exec app php artisan migrate --force
```

> [!NOTE]
> **Certbot version requirement:** `certbot reconfigure` was introduced in Certbot 2.3.0 (February 2023). Check your version with `certbot --version`. On Ubuntu 22.04+ the `snap`-installed Certbot is kept up to date automatically and will be ≥ 2.3.0. On older systems installed via `apt`, upgrade with `sudo snap install --classic certbot` or consult the [Certbot documentation](https://eff-certbot.readthedocs.io/) for the equivalent manual procedure.

> [!IMPORTANT]
> The `--staging` flag in `certbot reconfigure` tests against Let's Encrypt's staging server. This validates that Nginx correctly serves the ACME challenge from `/var/www/certbot` without consuming a production rate-limit. The certificate is not replaced — only the renewal configuration is updated and verified.

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

**Step B — Verify that automatic renewal will succeed** (safe, no rate-limit consumed):

```bash
sudo certbot renew --dry-run
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
git fetch origin && git checkout <target-branch-or-tag> && git pull --ff-only

export NEW_TAG=$(git rev-parse --short HEAD)

# 1. Build new immutable images (old tagged images remain intact)
docker build --target runtime -t daywright-app:${NEW_TAG} .
docker build --target web     -t daywright-web:${NEW_TAG} .

# 2. If this release includes database migrations, take an RDS snapshot FIRST!
#    Example via AWS CLI (or create a manual snapshot in the AWS RDS Console):
#    aws rds create-db-snapshot \
#      --db-instance-identifier daywright-prod \
#      --db-snapshot-identifier "daywright-pre-${NEW_TAG}-$(date +%Y%m%d%H%M)"

# 3. Update IMAGE_TAG in .env and recreate services
sed -i "s/^IMAGE_TAG=.*/IMAGE_TAG=${NEW_TAG}/" .env
docker compose -f compose.production.yaml up -d

# 4. Run database migrations explicitly (never automatic on boot)
docker compose -f compose.production.yaml exec app php artisan migrate --force

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

# 2. Set IMAGE_TAG in .env back to the previous known-good tag
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
   - For minor additive migrations with a `down()` method, you may run `docker compose -f compose.production.yaml exec app php artisan migrate:rollback --step=1 --force` _before_ switching back to the old code (verify the `down()` method is non-destructive first).
   - For destructive or irreversible schema/data migrations, **restore the pre-migration AWS RDS snapshot** (and update `DB_HOST` in `.env.production` if restoring to a new RDS instance endpoint), then roll `IMAGE_TAG` back to `<PREVIOUS_TAG>`.
