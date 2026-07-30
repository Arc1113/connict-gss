# Local Deployment Runbook

Status: Phase 10 implemented

This runbook prepares the Graduation Stage System for an offline local ceremony
deployment. It uses one Laravel application with React/Inertia production
assets, PostgreSQL, Reverb, local media, and local backups.

Do not use this runbook for public internet hosting, cloud databases, public DNS,
or remote storage.

## Deployment architecture

```text
Server laptop
├── Docker Compose
├── Laravel HTTP app       TCP 8000, ceremony LAN only
├── Laravel Reverb         TCP 8080, ceremony LAN only
├── PostgreSQL             Docker network + server loopback only
├── Local media            Docker volume / storage
└── Local backups          storage/app/gss-backups

Scanner laptop
└── Chromium browser       http://SERVER_LAN_IP:8000/scanner

Stage laptop
└── Chromium browser       http://SERVER_LAN_IP:8000/stage
```

The browser never needs the Vite development server during the ceremony. Runtime
assets are served from `public/build`.

## Primary deployment files

- `compose.yaml`: shared service definitions and safe development defaults.
- `compose.deploy.yaml`: production-oriented override with restart policies,
  app/Reverb health checks, deployment image, persistent app storage, and no
  Vite service.
- `docker/php/Dockerfile.deploy`: release image for the Laravel app and Reverb.
- `.env.deployment.example`: example only; copy to an untracked `.env.production`
  or `.env` and replace all secrets.
- `scripts/gss-start.sh`: validated startup.
- `scripts/gss-stop.sh`: safe shutdown.
- `scripts/gss-health-check.sh`: readiness and recovery checks.
- `scripts/gss-build-release.sh`: release-preparation checks.

## Environment configuration

Create an untracked deployment env file:

```bash
cp .env.deployment.example .env.production
php artisan key:generate --show
```

Paste the generated key into `APP_KEY`. Replace the database and Reverb example
secrets. Do not commit `.env.production`.

Required deployment values:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=http://SERVER_LAN_IP:8000
APP_BIND_HOST=0.0.0.0
APP_FORWARD_PORT=8000

DB_CONNECTION=pgsql
DB_HOST=database
DB_PORT=5432
DB_FORWARD_PORT=54329

REVERB_SERVER_HOST=0.0.0.0
REVERB_SERVER_PORT=8080
REVERB_HOST=reverb
REVERB_PORT=8080
REVERB_SCHEME=http
REVERB_FORWARD_PORT=8080
REVERB_ALLOWED_ORIGINS=SERVER_LAN_IP

VITE_REVERB_HOST=
VITE_REVERB_PORT=8080
VITE_REVERB_SCHEME=http
GSS_BACKUP_PATH=storage/app/gss-backups
```

`VITE_REVERB_HOST` should normally remain empty so scanner and stage browsers
connect to the same hostname they used to load the page. Do not compile
`localhost` into browser assets for a three-device deployment.

## Required ports

| Port | Purpose | Exposure |
| ---: | --- | --- |
| TCP `8000` | Laravel HTTP | Ceremony LAN only |
| TCP `8080` | Reverb WebSocket | Ceremony LAN only |
| TCP `54329` → container `5432` | PostgreSQL maintenance from server | `127.0.0.1` only |

The scanner and stage laptops must not reach PostgreSQL.

## Build production assets

Before disconnecting from the internet:

```bash
composer install
npm ci
npm run build
```

Confirm:

```bash
test -f public/build/manifest.json
test ! -f public/hot
```

The current Vite build may warn about a JavaScript chunk larger than 500 kB.
That warning is documented and accepted for Phase 10 because removing it safely
would require broader route-level code splitting that is not necessary for the
local ceremony deployment.

## Release preparation

Run:

```bash
bash scripts/gss-build-release.sh --env-file .env.production
```

During active development, use `--allow-dirty` only when the operator has
reviewed the working tree:

```bash
bash scripts/gss-build-release.sh --env-file .env.production --allow-dirty
```

The release script checks lockfiles, tests, linting, production build, security
audits, Compose deployment config, ignored secret files, ignored backups, and
ignored screenshot evidence.

## Startup workflow

Normal event startup:

```bash
bash scripts/gss-start.sh --env-file .env.production --build --migrate
```

For an already-migrated server:

```bash
bash scripts/gss-start.sh --env-file .env.production --skip-migrate
```

Startup policy:

- validate deployment env;
- require `APP_DEBUG=false`;
- require a LAN `APP_URL`;
- require non-placeholder secrets;
- require `public/build/manifest.json`;
- start PostgreSQL first;
- wait for PostgreSQL health;
- start Laravel and Reverb;
- run `php artisan migrate --force` only when `--migrate` is selected;
- never run `migrate:fresh`;
- never run seeders automatically;
- print scanner, stage, and stage-control URLs without secrets.

## Migration deployment policy

Before applying migrations on the event server:

1. Create or verify a recent backup.
2. Confirm no ceremony is in active presentation.
3. Confirm the deployment release and commit.
4. Run:

   ```bash
   docker compose --env-file .env.production -f compose.yaml -f compose.deploy.yaml exec -T app php artisan migrate --force
   ```

5. Record the result in the ceremony technical log.
6. Run `scripts/gss-health-check.sh`.

Do not use `migrate:fresh` on a ceremony server. Do not automatically roll back
unknown data migrations; escalate to the administrator and technical operator.

## Health check workflow

Run:

```bash
bash scripts/gss-health-check.sh --env-file .env.production
```

The health check verifies Docker, Compose, production asset manifest, no Vite hot
file, `APP_DEBUG=false`, app `/up`, Reverb socket, database health, migration
status, database connection, writable media and backup directories, disk space,
PostgreSQL loopback exposure, and backup recency.

It returns non-zero for critical failures and does not print secrets.

## Shutdown workflow

Preferred shutdown after the event:

```bash
bash scripts/gss-stop.sh --env-file .env.production --backup
```

Emergency or already-backed-up shutdown:

```bash
bash scripts/gss-stop.sh --env-file .env.production --no-backup
```

Shutdown preserves containers, images, named volumes, media, logs, and backups.
Do not use `docker compose down -v` for normal shutdown.

## Disk-space safeguards

Recommended free disk space before ceremony: at least 20 GiB. Prefer 50 GiB when
large audio/photo sets or multiple backups are expected.

Do not automatically delete backups, media, database volumes, or logs. If space
is low, copy old backups to approved offline storage and remove them manually
only after a human verifies the copy.

## Firewall guidance

Review these Ubuntu `ufw` examples before applying them. Replace
`192.168.50.0/24` with the actual ceremony subnet:

```bash
sudo ufw allow from 192.168.50.0/24 to any port 8000 proto tcp
sudo ufw allow from 192.168.50.0/24 to any port 8080 proto tcp
sudo ufw deny 5432/tcp
sudo ufw deny 54329/tcp
sudo ufw status verbose
```

Do not expose PostgreSQL to scanner or stage devices.

## Service recovery

```bash
bash scripts/gss-health-check.sh --env-file .env.production
docker compose --env-file .env.production -f compose.yaml -f compose.deploy.yaml restart reverb
docker compose --env-file .env.production -f compose.yaml -f compose.deploy.yaml restart app
docker compose --env-file .env.production -f compose.yaml -f compose.deploy.yaml restart database
```

After a service restart, refresh scanner, stage-control, and stage browsers.
Authoritative recovery reads come from HTTP/PostgreSQL.

## Security notes

This deployment uses HTTP on a trusted local network unless a separate approved
local TLS setup is implemented later. Treat the ceremony LAN as private:

- use a dedicated router;
- do not allow guest users;
- suppress desktop notifications;
- avoid projecting admin screens;
- keep terminals off the audience display;
- keep `.env`, backup archives, and database ports off client devices.

## Phase 11 boundary

Phase 10 prepares deployment and device setup. Full ceremony rehearsal, venue
certification, and sign-off belong to Phase 11.
