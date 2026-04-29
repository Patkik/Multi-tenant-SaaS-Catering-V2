CaterPro Docker Production Guide

This file contains production-specific instructions for running CaterPro with `docker-compose.prod.yml`.

Prerequisites
- Docker Engine and Docker Compose v2
- Replace secrets in `docker/secrets/*` with strong values
- Provide SSL cert/key at `docker/nginx/ssl/caterpro.local.crt` and `.key` (or mount `/etc/nginx/ssl`)
- Ensure DNS resolves `*.caterpro.local` to your server IP

Start production stack
```bash
# pull images and build local ones
docker compose -f docker-compose.prod.yml pull
docker compose -f docker-compose.prod.yml up --build -d

# run migrations
docker compose -f docker-compose.prod.yml exec app php artisan migrate --force

# generate APP_KEY if not set
docker compose -f docker-compose.prod.yml exec app php artisan key:generate --ansi

# tail nginx logs
docker compose -f docker-compose.prod.yml logs -f web
```

Secrets management
- The compose file references secrets files in `docker/secrets/`. Replace their contents and secure filesystem permissions:
```bash
chmod 600 docker/secrets/*
```

SSL
- For LAN: generate self-signed wildcard cert and mount at `docker/nginx/ssl/`. Import CA into client machines as described in `LAN_DEPLOYMENT_GUIDE.md`.

Hardening applied
- `docker-compose.prod.yml` now includes healthchecks, logging limits, resource reservations, non-root user settings (`www-data` for `app`, `nginx` for `web`), read-only root filesystems with `tmpfs` mounts for writable runtime directories, dropped capabilities and `no-new-privileges` security option.

Verification checklist after bring-up
```bash
# check service health
docker compose -f docker-compose.prod.yml ps
docker compose -f docker-compose.prod.yml ls
docker compose -f docker-compose.prod.yml ps --filter health=starting

# inspect logs
docker compose -f docker-compose.prod.yml logs -f web
docker compose -f docker-compose.prod.yml logs -f app

# exec into app to verify file permissions and runtime
docker compose -f docker-compose.prod.yml exec --user www-data app bash -lc "php artisan --version && ls -la storage"
```

Scaling and hardening suggestions
- Add `deploy.resources` (limits/reservations), `healthcheck`, read-only file systems, and non-root users per service (see `.agents/skills/docker-compose-production/SKILL.md`).
- Use an external managed DB and Redis for HA in production, or run orchestrator/K8s for real multi-node deployments.

Backup and restore
- Ensure `db_data` is backed up regularly (mysqldump) and `uploads` volume is snapshot or replicated.

Rollbacks
- Keep image tags for releases (e.g., `myorg/caterpro:20260428`) and deploy by tag to roll forward/back.

Contact
- See `LAN_DEPLOYMENT_GUIDE.md` for LAN-specific DNS, cert, and NFS guidance.
