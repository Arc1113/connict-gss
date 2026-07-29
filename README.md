# Graduation Stage System

Status: **Phase 4 — Ceremony and Graduate Administration implemented**

The Graduation Stage System is a local-network-only application for reliable,
QR-driven graduation presentation. Phase 3 established the complete operational
path:

```text
USB QR scanner → /scanner → POST /scans → PostgreSQL transaction
    → post-commit StudentPresented event → Reverb → Echo → /stage
    → local audio, with GET /stage/state recovery
```

PostgreSQL is authoritative. WebSockets announce committed changes; they do not
replace HTTP commands or state retrieval.

Phase 4 adds authenticated ceremony and graduate preparation without changing
that path: role-scoped CRUD, search/filter/pagination, safe ceremony lifecycle
rules, local photo/audio upload and preview, protected deletion, and redacted
administrative audit history.

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

## Phase 4 administration behavior

- Administrators can create, review, edit, activate/pause/complete/archive, and
  safely delete unused ceremonies; manage graduate records and media; and review
  administrative audit history.
- Data encoders can view ceremonies and view/create/edit graduate preparation
  data and local media. They cannot manage ceremonies, delete graduate records,
  view audit history, or gain scanner/stage access.
- Other operational roles cannot access administration routes.
- Ceremony activation uses a PostgreSQL transaction-scoped advisory lock plus row
  locks. A second active ceremony is rejected; the system never silently
  deactivates another ceremony or activates by date.
- Ceremony changes are blocked while a graduate is presenting. Operational
  history and stage state protect ceremonies from deletion.
- Graduate deletion is blocked by scan, queue, or presentation history.
- QR tokens are normalized and validated but never returned by list/detail/edit
  page props. Editing uses an optional replacement value.
- Uploaded JPEG/PNG/WebP photos (maximum 5 MB) and MP3/WAV/OGG audio (maximum
  15 MB) are stored under `storage/app/public/graduates` with generated names.
  Replacement commits the database change before removing the former managed
  file.
- For reviewed announcement clips, the current preparation recommendation is
  2–10 seconds, trimmed of excess silence, near −16 LUFS integrated loudness,
  and no peak above −1 dBFS. This is guidance rather than an approval flag or
  server-enforced rule; final files still require venue-speaker review.
- Audit records store actor, action, entity, time, IP, and allowlisted safe
  changes. Raw QR tokens, original filenames, media paths/content, passwords, and
  unnecessary identity values are excluded.

## Implemented routes

| Route | Purpose | Access |
| --- | --- | --- |
| `GET /scanner` | Keyboard-wedge and manual scan UI | Scanner operator, administrator |
| `POST /scans` | Validate and commit one scan attempt | Scanner operator, administrator |
| `GET /stage` | Audience presentation display | Stage operator, administrator |
| `GET /stage/state` | Authoritative safe stage snapshot | Stage operator, administrator |
| `POST /stage/complete-current` | Minimal completion/clear command | Stage operator, administrator |
| `GET\|POST /broadcasting/auth` | Private Echo channel authorization | Authorized active session |
| `GET /admin` | Ceremony readiness and preparation tasks | Administrator, data encoder |
| `GET /admin/ceremonies` | Search/filter/paginated ceremony list | Administrator, data encoder |
| `GET /admin/ceremonies/create` | Ceremony form | Administrator |
| `GET /admin/ceremonies/{ceremony}` | Ceremony detail/history protection | Administrator, data encoder |
| `GET /admin/ceremonies/{ceremony}/edit` | Ceremony edit/lifecycle form | Administrator |
| `GET /admin/students` | Search/filter/sort/paginated graduate list | Administrator, data encoder |
| `GET /admin/students/create` | Graduate and media form | Administrator, data encoder |
| `GET /admin/students/{student}` | Graduate detail and media preview | Administrator, data encoder |
| `GET /admin/students/{student}/edit` | Graduate and media edit form | Administrator, data encoder |
| `GET /admin/audit-logs` | Safe administrative audit history | Administrator |

The corresponding `POST`, `PUT/PATCH`, and `DELETE` resource actions are
server-authorized. Separate `DELETE /admin/students/{student}/photo` and
`DELETE /admin/students/{student}/audio` actions remove managed media safely.

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
   docker compose exec -T app php artisan storage:link
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
| Ana Marie dela Cruz | `GSS-PHASE3-A7K9M2Q4` | `student-01.wav` |
| Christopher Emmanuel Villanueva-Santos | `GSS-PHASE3-B8L3N5R7` | `student-02.wav` |
| Ma. Lourdes P. Reyes | `GSS-PHASE3-C4P6S8V2` | `student-03.wav` |
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

### Manual administration check

1. Sign in as `admin@example.test`, open `/admin`, and review the active-ceremony
   and data-readiness facts.
2. Open `/admin/ceremonies`; test search/status/year filters, validation errors,
   an activation conflict, and a protected delete.
3. Open `/admin/students`; test realistic long names/programs, all filters,
   sequence/name sorting, pagination, and no-results recovery.
4. Add/edit a graduate; confirm duplicate student/sequence/token errors identify
   the field without exposing an existing token.
5. Upload and replace a local photo and audio file, play the browser preview,
   remove each file, and confirm the missing-media state.
6. Review `/admin/audit-logs`; confirm important actions exist without raw QR
   values, original filenames, or media paths.
7. Sign in as `encoder@example.test`; confirm ceremony pages are read-only,
   graduate create/edit/media work, destructive actions are absent/forbidden,
   and `/admin/audit-logs` returns 403.
8. Re-run the Phase 3 scanner/stage flow using an eligible edited graduate and
   confirm uploaded media resolves from the persisted stage payload.

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

Phase 4 adds policy/route matrices, ceremony and graduate CRUD/validation,
search/filter/sort/pagination, activation and concurrent-activation protection,
presenting-state and deletion safeguards, upload validation and cleanup, audio
preview paths, audit redaction, and stage-media compatibility tests.

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
- **Uploaded media returns 404:** run
  `docker compose exec -T app php artisan storage:link`, verify
  `storage/app/public` is writable, and keep the generated `storage/...` path in
  PostgreSQL.
- **Ceremony activation is blocked:** finish the current presentation, then
  pause/complete the sole active ceremony before activating another. Activation
  never occurs automatically.

## Phase boundary

Phase 4 does not add CSV import, QR generation/printing, camera scanning, device
credentials, a general queue, present-next/selected, reorder, skip, return,
replay/mute controls, operational scan/stage reports, backups, deployment
automation, cloud media, or text-to-speech. Those features remain deferred to
their approved phases, especially CSV preparation in Phase 5 and the full queue
and mode behavior in Phase 7.

See [requirements](docs/requirements.md) and
[architecture](docs/architecture.md) for the normative Phase 3 and Phase 4
contracts. Do not begin Phase 5 until Phase 4 receives explicit human approval.
