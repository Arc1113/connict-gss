# Graduation Stage System

Status: **Phase 3 — Critical Vertical Slice implemented; awaiting human approval**

The Graduation Stage System is a local-network-only application for reliable,
QR-driven graduation presentation. Phase 3 proves one complete path:

```text
USB QR scanner → /scanner → POST /scans → PostgreSQL transaction
    → post-commit StudentPresented event → Reverb → Echo → /stage
    → local audio, with GET /stage/state recovery
```

PostgreSQL is authoritative. WebSockets announce committed changes; they do not
replace HTTP commands or state retrieval.

## Approved architecture

- Laravel 13 backend
- React 19 frontend in the same application
- Inertia.js 3 integration
- Vite 8 asset bundler
- PostgreSQL 16 database
- Laravel Reverb 1.11 WebSocket server
- Laravel Echo 2.4 and `pusher-js` 8.6 browser client
- Session authentication and private broadcast channels
- HTTP for commands, validation, and authoritative reads
- Local files for presentation media
- Server, scanner, and stage laptops on a dedicated local network
- USB 2D keyboard-wedge QR scanner as the primary scanner

There is no SQLite fallback, polling replacement, cloud runtime service, hosted
font, CDN, browser speech synthesis, or internet runtime dependency.

## Phase 3 behavior

The temporary vertical-slice policy is intentionally small:

1. Exactly one ceremony must have PostgreSQL status `active`.
2. A valid, eligible scan immediately becomes `presenting` only when the stage is
   idle.
3. A scan never preempts the current graduate.
4. While a graduate is presenting, another graduate receives `stage_busy`.
5. A repeat of the active graduate receives `duplicate_active`.
6. A completed graduate receives `already_completed`.
7. A stage operator or administrator may complete the current presentation. This
   marks its queue row `completed`, clears stage state, increments the state
   version, records an event, and broadcasts the clear after commit.

Queue ordering, present-next, selected presentation, skip, return, replay controls,
mode automation, and advanced recovery remain Phase 7 work.

## Implemented routes

| Route | Purpose | Access |
| --- | --- | --- |
| `GET /scanner` | Keyboard-wedge and manual scan UI | Scanner operator, administrator |
| `POST /scans` | Validate and commit one scan attempt | Scanner operator, administrator |
| `GET /stage` | Audience presentation display | Stage operator, administrator |
| `GET /stage/state` | Authoritative safe stage snapshot | Stage operator, administrator |
| `POST /stage/complete-current` | Minimal completion/clear command | Stage operator, administrator |
| `GET\|POST /broadcasting/auth` | Private Echo channel authorization | Authorized active session |

The private Echo channel is `private-stage.{ceremonyId}`. It is authorized only
for stage operators and administrators when exactly one active ceremony exists
and the channel ID matches it.

`POST /scans` returns one of:

- `accepted` (`201`)
- `invalid_token` (`422`)
- `ceremony_inactive` (`409`)
- `student_ineligible` (`422`)
- `duplicate_active` (`409`)
- `already_completed` (`409`)
- `stage_busy` (`409`)
- `unauthorized` (`403`)

Every safely processable scan attempt stores a SHA-256 token hash, not the raw
token. Success events expose only event ID, state version, graduate ID, display
name, program, major, honors, local photo/audio URL, and presentation time.

## Runtime processes and ports

| Service | Default host port | Exposure |
| --- | ---: | --- |
| Laravel HTTP | `8000` | Loopback by default; LAN when explicitly enabled |
| Reverb WebSocket | `8080` | Loopback by default; LAN when explicitly enabled |
| PostgreSQL | `54329` | Loopback only |
| Vite dev server | Vite default | Development only; not required for compiled runtime |

The required runtime processes are Laravel, PostgreSQL, and Reverb. Phase 3 events
implement `ShouldBroadcastNow` and are dispatched only after the transaction
returns, so no queue worker is required.

## First-time Docker setup

Requirements: Docker with Compose, Composer, Node.js/npm, and a browser. Package
downloads require internet during setup only.

1. Create local environment configuration:

   ```bash
   cp .env.example .env
   ```

2. Replace all example secrets in `.env`:

   - `DB_PASSWORD`
   - `GSS_SEED_PASSWORD` with at least 12 characters
   - `REVERB_APP_KEY`
   - `REVERB_APP_SECRET`

3. Install the locked dependencies:

   ```bash
   composer install
   npm ci
   ```

4. Generate the Laravel key and build services:

   ```bash
   php artisan key:generate
   docker compose build
   ```

5. Start PostgreSQL and Laravel:

   ```bash
   docker compose up -d database app
   ```

6. Create a clean development database with Phase 3 sample data:

   ```bash
   docker compose exec -T app php artisan migrate:fresh --seed
   ```

7. Compile local production assets and start Reverb:

   ```bash
   npm run build
   docker compose up -d reverb
   docker compose ps
   ```

Open `http://localhost:8000`. Reverb should be healthy on port `8080`.

Run destructive `migrate:fresh` commands before starting Reverb, or restart
Reverb afterward. Reverb observes a database-backed restart marker, so replacing
the cache table while it is running can intentionally terminate the old process.

## Network configuration

Reverb has three distinct address concepts:

- `REVERB_SERVER_HOST` / `REVERB_SERVER_PORT`: where the Reverb process listens
  inside its container.
- `REVERB_HOST` / `REVERB_PORT`: where Laravel publishes events. Docker uses
  `reverb:8080`.
- `VITE_REVERB_HOST` / `VITE_REVERB_PORT`: where the browser connects.
  `VITE_REVERB_HOST` is empty by default so the client uses
  `window.location.hostname`; this avoids compiling `localhost` into LAN clients.

### Single-laptop development

Use:

```dotenv
APP_URL=http://localhost:8000
APP_BIND_HOST=127.0.0.1
REVERB_ALLOWED_ORIGINS=localhost,127.0.0.1
VITE_REVERB_HOST=
```

### Three-laptop local network

Assign the server laptop a fixed address. For example, if it is
`192.168.50.10`:

```dotenv
APP_URL=http://192.168.50.10:8000
APP_BIND_HOST=0.0.0.0
REVERB_ALLOWED_ORIGINS=localhost,127.0.0.1,192.168.50.10
VITE_REVERB_HOST=
VITE_REVERB_PORT=8080
VITE_REVERB_SCHEME=http
```

Then recreate the app and Reverb containers:

```bash
docker compose up -d --force-recreate app reverb
```

From both client laptops, verify:

```text
http://192.168.50.10:8000/up
http://192.168.50.10:8000/login
```

Allow inbound TCP `8000` and `8080` only on the dedicated local network. Do not
expose PostgreSQL. Add the actual server hostname/IP to
`REVERB_ALLOWED_ORIGINS`; entries are comma-separated hostnames without schemes or
ports. Rebuild assets only if a `VITE_*` value changes.

The address detected during Phase 3 development was `192.168.223.184`; it is an
example, not a committed deployment address.

## Development accounts and sample tokens

`php artisan migrate:fresh --seed` creates active local-only accounts:

| Email | Role |
| --- | --- |
| `admin@example.test` | Administrator |
| `encoder@example.test` | Data encoder |
| `scanner@example.test` | Scanner operator |
| `stage@example.test` | Stage operator |
| `technical@example.test` | Technical operator |

All use the untracked `GSS_SEED_PASSWORD`; no password is stored in this
repository.

The active sample ceremony contains:

| Graduate | Manual Phase 3 token | Audio fixture |
| --- | --- | --- |
| Amara Santos | `GSS-PHASE3-A7K9M2Q4` | `student-01.wav` |
| Benicio Reyes | `GSS-PHASE3-B8L3N5R7` | `student-02.wav` |
| Clarisse Mendoza | `GSS-PHASE3-C4P6S8V2` | `student-03.wav` |
| Dario Navarro | `GSS-PHASE3-D9T5W7Y3` | `student-04.wav` |
| Elena Alexandria-Cassandra Villanueva | `GSS-PHASE3-E2H6J8X4` | Intentionally missing |

The four WAVs in `public/media/phase3/audio` are short local test tones, not final
pronunciation audio. The fifth record verifies that missing audio does not crash
or expose an error on the audience display.

## Manual vertical-slice check

Use separate browser profiles so scanner and stage sessions do not replace each
other.

1. Sign in as `stage@example.test`, open `/stage`, and select **Enable ceremony
   audio** once.
2. Sign in as `scanner@example.test` in another profile and open `/scanner`.
3. Scan or enter the first sample token and press Enter.
4. Confirm immediate scanner acceptance and live stage presentation.
5. Refresh `/stage`; the same committed graduate must return through
   `GET /stage/state`.
6. From the stage operator dashboard, complete the current presentation.
7. Scan the second token and confirm the stage changes only after completion.
8. Re-scan the active token and verify `duplicate_active`.
9. Scan a different token while busy and verify `stage_busy`.
10. Stop Reverb with `docker compose stop reverb`, complete or scan as applicable,
    and confirm PostgreSQL state still commits. Start it again with
    `docker compose start reverb`; the stage must reconnect and reconcile through
    HTTP.

Repeat on the physical three-laptop setup before ceremony use. This development
environment cannot certify the USB scanner, projector/LED wall, venue sound
system, firewall, or three separate physical devices.

## Automated verification

Run database tests inside the PHP container because the host PHP installation may
not include `pdo_pgsql`:

```bash
docker compose exec -T app php artisan test
vendor/bin/pint --test
npm run lint
npm run build
composer audit
npm audit
docker compose config --quiet
```

The Phase 3 tests include HTTP/access checks, all scan outcomes, scan-log and
payload privacy checks, post-transaction event ordering, rollback behavior,
private-channel authorization, stage refresh/completion, and separate-process
PostgreSQL races.

An optional live Chromium check exercises two authenticated browser profiles,
private-channel delivery, local audio playback events, refresh recovery, and a
deliberate Reverb stop/restart:

```bash
docker compose stop reverb
docker compose exec -T app php artisan migrate:fresh --seed --force
docker compose start reverb
node tests/Support/phase3-browser-check.mjs
```

This check requires Google Chrome at `/usr/bin/google-chrome`, resets the local
development database, temporarily stops Reverb, completes its sample
presentations, and writes review screenshots to `/tmp`. Never run it against a
ceremony database or during live operations.

## Troubleshooting

- **WebSocket stays disconnected:** verify `reverb` is healthy, TCP `8080` is
  reachable, the page hostname is in `REVERB_ALLOWED_ORIGINS`, and the browser is
  not using a build containing an old `VITE_*` value.
- **Laravel commits but the stage does not change:** retrieve `/stage/state`,
  restart Reverb, then refresh/reconnect the stage. A broadcast failure does not
  roll back committed business state.
- **Channel authorization returns 403:** use a stage-operator or administrator
  session, confirm the account is active, and confirm exactly one active ceremony
  exists.
- **Every scan reports ceremony unavailable:** query/fix ceremony status; zero or
  multiple active ceremonies are deliberately rejected.
- **A scan reports stage busy:** complete the current presentation from the
  authorized dashboard. Phase 3 never preempts.
- **No sound:** click **Enable ceremony audio**, confirm the selected record has a
  local audio path, and check the stage laptop/output device. Missing audio is a
  safe visual-only presentation.
- **Docker still binds to loopback after `.env` changes:** recreate the app and
  Reverb containers.
- **Host tests report `could not find driver`:** run PHPUnit in the Docker app
  container.

## Phase boundary

Phase 3 does not add ceremony/student CRUD, CSV import, QR generation, activation
UI, device credentials, a general queue, present-next/selected, reorder, skip,
return, replay/mute controls, reporting, audit UI, backups, deployment automation,
or final media preparation. Those features remain deferred to their approved
phases, especially the full queue and mode behavior in Phase 7.

See [requirements](docs/requirements.md) and
[architecture](docs/architecture.md) for the normative Phase 3 contract. Do not
begin Phase 4 until Phase 3 receives explicit human approval.
