Quick Dockerization for CaterPro (LAN / local development)

Prereqs
- Docker and Docker Compose v2 installed on your machine/server
- Copy `.env` from `.env.example` and set DB/APP keys

Build and run (from repo root)
```bash
# build images and start services
docker compose up --build -d

# view logs
docker compose logs -f web

# to expose the application to the internet via ngrok
# ensure NGROK_AUTHTOKEN is set in your .env
docker compose --profile tunnel up -d ngrok

# run migrations
docker compose exec app php artisan migrate --force

# create app key (if not set)
docker compose exec app php artisan key:generate --ansi

# build frontend (if you want to copy dist into app manually)
# Option 1: use frontend-builder image to build
docker compose build frontend-builder
# copy build output to host volume
docker cp $(docker ps -aqf "name=caterpro_frontend-builder_1"):/dist ./public/frontend || true

# or run npm build locally in central-app/
cd central-app && npm ci && npm run build
```

Notes
- The provided Dockerfiles are a minimal starting point. In production you should: add secrets management, healthchecks, resource limits, non-root users, read-only filesystems, and persistent backups.
- The `frontend-builder` outputs to a named volume `frontend_dist` which you can copy into `public/` or mount into `app` for serving static assets.
- For multi-instance or HA, prefer Kubernetes or a Compose with external volumes and proper clustering for Redis/MySQL.
