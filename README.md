# Graduation Stage System

Status: **Phase 8 — Announcement Audio Reliability implemented**

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

Phase 5 adds an owner-bound, create-only CSV preparation workflow. Upload,
mapping, server-normalized preview, and explicit confirmation are separate HTTP
steps. PostgreSQL rechecks the complete file and creates all graduates in one
transaction or creates none.

Phase 6 adds protected, deterministic SVG QR output, individual and bounded batch
print sheets, administrator-only token replacement, readiness review, and a
controlled scanner lookup fallback. It preserves the Phase 3 scan transaction,
post-commit Reverb event, Echo stage update, and HTTP recovery path.

Phase 7 replaces the temporary immediate-presentation rule with a durable
PostgreSQL queue and a dedicated stage-control workspace. Scans now create
ordered queue entries; controlled mode waits for an operator, while automatic
mode presents only the first eligible scan when the stage is idle and the queue
has no earlier graduate. Every command remains HTTP-first, transactionally
persisted, broadcast after commit, and recoverable through HTTP.

Phase 8 adds an explicit announcement-audio readiness workflow. Local audio can
be uploaded, reviewed, approved, rejected, replaced, and removed without changing
the queue or scan architecture. The stage plays or replays only approved,
readable same-origin audio; every other state uses the documented live-announcer
fallback while keeping the audience screen clean.

## Approved architecture

- Laravel 13 backend
- React 19 frontend in the same application
- Inertia.js 3 integration
- Vite 8 asset bundler
- PostgreSQL 16 database
- Laravel Reverb 1.11 WebSocket server
- Laravel Echo 2.4 and `pusher-js` 8.6 browser client
- BaconQrCode 3.1.1 for local deterministic SVG QR rendering
- Session authentication and private broadcast channels
- HTTP for commands, validation, and authoritative reads
- Local files for presentation media
- Server, scanner, and stage laptops on a dedicated local network
- USB 2D keyboard-wedge QR scanner as the primary scanner

There is no SQLite fallback, polling replacement, cloud runtime service, hosted
font, CDN, browser speech synthesis, or internet runtime dependency.

## Phase 8 audio readiness and playback behavior

Supported upload formats are MP3, WAV, and OGG, up to 15 MB per graduate. The
server validates local file type and generated storage names; no audio binary,
raw waveform, cloud identifier, or original path is stored in PostgreSQL.

Readiness states are:

- `missing`: no usable announcement audio exists.
- `uploaded`: a file was uploaded and awaits review.
- `needs_review`: an operator/reviewer flagged the file for pronunciation,
  content, volume, or quality review.
- `approved`: an administrator approved the readable local file for stage
  playback.
- `rejected`: the current file must not be used and requires replacement.

Data encoders can upload/replace audio, preview it, and mark it needs review.
Administrators can approve or reject audio. Stage operators cannot modify files
or review history; they only see whether the current graduate requires a live
announcer or has approved audio for replay.

Replacement invalidates prior approval. Failed replacements preserve the former
file and metadata. Removal resets readiness to `missing`. Approval records the
approver, timestamp, and safe notes; rejection preserves the file for authorized
review and blocks playback. Audit records store safe status changes and notes,
not media paths or audio content.

The stage playback rule is:

```text
approved and readable audio -> preload and play/replay
uploaded, needs_review, rejected, missing, or unreadable -> no automatic playback
```

The audience screen never shows audio technical status. The stage-control screen
shows safe fallback guidance, normally **Live announcer required**. The human
announcer reads the approved announcement name when no approved audio is
available. Duration and loudness are manual review items: prepare clips around
2-10 seconds, near -16 LUFS integrated loudness, and true peak at or below about
-1 dBFS, then verify on the venue sound system.

## Phase 7 queue and presentation behavior

1. Exactly one ceremony must have PostgreSQL status `active`.
2. Every valid eligible scan first creates one durable `waiting` queue row and a
   monotonically increasing ceremony-local position.
3. Controlled mode leaves the row waiting until **Present next** or
   **Present selected** succeeds.
4. Automatic mode may promote that new row to `presenting` in the same
   transaction only when the stage is idle, automation is not paused, and no
   earlier waiting row exists. It never preempts and never auto-advances after
   completion.
5. Completion marks the current row `completed`, clears the audience state, and
   leaves later graduates waiting.
6. Skip removes a waiting row from ordered positions while preserving history;
   return appends it to the current tail with a new position.
7. Replay versions and records an announcement request without changing queue
   status. Missing audio is rejected safely.
8. Holding temporarily replaces the audience content without completing or
   losing the current graduate; restore retrieves the persisted current state.
9. Repeated scans report precise `already_queued`, `already_presenting`,
   `already_completed`, or `skipped_requires_stage_action` outcomes.
10. PostgreSQL remains authoritative. Queue and stage browsers refetch after
    reconnect, refresh, a version gap, and compact control-channel events.

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
- QR tokens are server-owned and never accepted by the normal graduate form.
  They are never returned by list/detail/edit page props.
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

## Phase 5 CSV preparation behavior

- Administrators and data encoders can download a local UTF-8 template and
  import into a draft, prepared, active, or paused ceremony. Other roles are
  denied by server policy.
- CSV input is limited to 2 MiB, 2,000 graduate rows, 40 columns, and 2,000
  characters per cell. Comma, semicolon, and tab delimiters are detected
  deterministically; binary/control data, non-UTF-8 input, duplicate/empty
  headers, ambiguous delimiters, and headers without rows are rejected.
- Exact, unambiguous header aliases receive mapping suggestions. The operator
  must map student number, first name, last name, program, sequence number, and
  eligibility exactly once. Other approved fields are optional; unmapped
  columns are visibly ignored.
- Preview is rebuilt on the server from encrypted temporary content and
  persisted mapping. It displays 50 rows per page with explicit ready, warning,
  and blocked states, generated-name warnings, structural errors, duplicate
  identifiers, and current PostgreSQL conflicts.
- This phase creates new records only. It never updates or synchronizes an
  existing graduate. The confirm control is absent while any row is blocked.
- Confirmation obtains a PostgreSQL transaction advisory lock, locks the import,
  ceremony, and current ceremony graduates, rebuilds the entire preview, then
  creates every row and its safe audit entry atomically.
- Every imported graduate receives a server-generated, collision-resistant QR
  token. The CSV template, mapping, preview, summary, and audit history never
  expose raw QR values.
- Temporary CSV content is encrypted through the application key, owner-bound,
  stored only in PostgreSQL, expires after 30 minutes, and is cleared after a
  successful import. No public or retained temporary upload file is created.

## Phase 6 QR preparation and scanner behavior

- Laravel generates every new graduate token as `GSS-` plus 48 random uppercase
  alphanumeric characters. Existing tokens remain valid until an administrator
  explicitly replaces one.
- BaconQrCode 3.1.1 renders SVG locally and dynamically from the PostgreSQL token.
  Output uses error-correction level Q, an opaque white background, and a
  four-module quiet zone. No QR file, token, personal data, or external rendering
  request is stored.
- Administrators and data encoders can review readiness, open individual QR
  output, and print batches. Only administrators can replace a token or see its
  replacement history. Scanner, stage, and technical operators cannot access QR
  administration.
- Individual printing uses A4 portrait, one 65 mm QR card. Batch printing uses
  A4 portrait with 12 mm margins, a two-column grid, up to four 55 mm QR cards
  per page, deterministic sequence/name order, and a maximum of 100 graduates.
  Print at 100% scale with **Fit to page** and browser headers/footers disabled.
- Only eligible graduates with a current token and no completed presentation can
  be printed. A regenerated token is marked **Regenerated — reprint**.
- Token replacement locks the graduate in a PostgreSQL transaction, verifies an
  opaque HMAC version, blocks presenting/completed graduates, writes a redacted
  audit row, and immediately makes every old printed card invalid.
- `/scanner` retains its synchronous submission lock, Enter handling, input
  clearing, and focus recovery. A bounded active-ceremony lookup can select one
  verified graduate when a printed QR will not decode; it runs the same
  transaction and never exposes a token.
- Physical USB scanner and paper-output certification remain pending because no
  scanner was attached and configured printers were offline during Phase 6
  development. Exact rehearsal steps are documented below.

## Phase 7 stage-control behavior

- `/stage-control` makes the current graduate and next safe action dominant,
  with the ordered queue, skipped graduates, recent actions, connection state,
  and audio readiness kept secondary.
- Queue positions are never renumbered during the ceremony. New scans and
  returned graduates receive `MAX(queue_position) + 1` while the ceremony and
  stage state are locked. A PostgreSQL partial unique index rejects duplicate
  non-null positions.
- Present-next and present-selected lock authoritative state, reject
  busy/stale/cross-ceremony commands, record the action, and publish only after
  commit.
- Controlled scans remain waiting. Automatic scans can present only the first
  idle, unpaused item and do not bypass an existing queue. Automatic pause is
  persisted; resuming does not silently present a backlog item.
- Completion is explicit in both modes and never auto-advances.
- Skip and return preserve the same queue item and its complete history. A
  returned graduate joins the tail rather than reclaiming an old position.
- Replay is available only when the current graduate has approved readable local
  audio.
  It versions and records the request without changing queue status.
- Holding mode preserves the current pointer and shows only neutral ceremony
  identity to the audience. Restoring returns to the persisted graduate or idle
  state.
- Buttons use a single pending-command lock and risky actions request
  confirmation. Both operator and audience views reconcile from HTTP after
  refresh, a version gap, or Reverb reconnection.
- The audience display receives no queue positions, tokens, student numbers,
  operator history, commands, or technical diagnostics.

## Implemented routes

| Route | Purpose | Access |
| --- | --- | --- |
| `GET /scanner` | Keyboard-wedge and manual scan UI | Scanner operator, administrator |
| `POST /scanner/lookup` | Bounded active-ceremony fallback search | Scanner operator, administrator |
| `POST /scans` | Validate and commit one scan attempt | Scanner operator, administrator |
| `POST /scans/manual` | Process one verified fallback selection through the scan transaction | Scanner operator, administrator |
| `GET /stage` | Audience presentation display | Stage operator, administrator |
| `GET /stage/state` | Authoritative safe stage snapshot | Stage operator, administrator |
| `GET /stage-control` | Durable queue and current-presentation workspace | Stage operator, administrator |
| `GET /stage/queue` | Authoritative safe queue/control snapshot | Stage operator, administrator |
| `POST /stage/present-next` | Present the earliest waiting graduate | Stage operator, administrator |
| `POST /stage/present/{queueItem}` | Present one selected waiting graduate | Stage operator, administrator |
| `POST /stage/complete-current` | Complete current and clear the stage | Stage operator, administrator |
| `POST /stage/skip/{queueItem}` | Preserve history and skip a waiting graduate | Stage operator, administrator |
| `POST /stage/return/{queueItem}` | Append a skipped graduate to the queue tail | Stage operator, administrator |
| `POST /stage/replay` | Replay approved local audio without changing queue state | Stage operator, administrator |
| `POST /stage/holding/activate` | Show the neutral audience holding screen | Stage operator, administrator |
| `POST /stage/holding/deactivate` | Restore the persisted live audience state | Stage operator, administrator |
| `POST /stage/automatic/pause` | Persistently pause automatic initial presentation | Stage operator, administrator |
| `POST /stage/automatic/resume` | Resume automatic initial presentation | Stage operator, administrator |
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
| `GET /admin/students/import` | Select ceremony and inspect local CSV | Administrator, data encoder |
| `GET /admin/students/import/template` | Download local UTF-8 CSV template | Administrator, data encoder |
| `POST /admin/students/import/inspect` | Validate and store encrypted temporary import | Administrator, data encoder |
| `GET /admin/students/import/{import}/mapping` | Review source samples and map columns | Owning administrator/data encoder |
| `POST /admin/students/import/{import}/preview` | Validate and persist mapping | Owning administrator/data encoder |
| `GET /admin/students/import/{import}/preview` | Review server-normalized row results | Owning administrator/data encoder |
| `POST /admin/students/import/{import}/confirm` | Revalidate and atomically create records | Owning administrator/data encoder |
| `GET /admin/students/import/{import}/summary` | Review safe committed counts | Owning administrator/data encoder |
| `GET /admin/students/qr-codes` | Filter QR readiness and select a print batch | Administrator, data encoder |
| `POST /admin/students/qr-codes/batch` | Validate and render a bounded batch print sheet | Administrator, data encoder |
| `GET /admin/students/{student}/qr-code` | Review one protected QR card | Administrator, data encoder |
| `GET /admin/students/{student}/qr-code/image` | Return protected no-store SVG output | Administrator, data encoder |
| `GET /admin/students/{student}/qr-code/print` | Render one print sheet | Administrator, data encoder |
| `POST /admin/students/{student}/qr-code/regenerate` | Replace one token | Administrator |
| `GET /admin/audit-logs` | Safe administrative audit history | Administrator |

The corresponding `POST`, `PUT/PATCH`, and `DELETE` resource actions are
server-authorized. Separate `DELETE /admin/students/{student}/photo` and
`DELETE /admin/students/{student}/audio` actions remove managed media safely.

The audience Echo channel is `private-stage.{ceremonyId}`. The operator channel
is `private-stage-control.{ceremonyId}`. Both are authorized only for stage
operators and administrators when exactly one active ceremony exists and the
channel ID matches it. Audience events contain presentation-safe fields only;
compact control events trigger an authoritative queue/state refetch.

`POST /scans` returns one of:

- `queued` (`201`)
- `invalid_token` (`422`)
- `ceremony_inactive` (`409`)
- `student_ineligible` (`422`)
- `already_queued` (`409`)
- `already_presenting` (`409`)
- `already_completed` (`409`)
- `skipped_requires_stage_action` (`409`)
- `unauthorized` (`403`)
- `request_failed` (`500`, safe unexpected-failure response)

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

Use separate scanner, stage-control, and audience browser profiles.

1. Sign in as `stage@example.test` in two profiles. Open `/stage-control` in one
   and `/stage` in the other; select **Enable ceremony audio** once on `/stage`.
2. Sign in as `scanner@example.test` in a third profile and open `/scanner`.
3. Scan the first three sample tokens. Confirm distinct queue positions and no
   audience preemption in controlled mode.
4. Select **Present next graduate** and verify the earliest row appears on
   `/stage` with local audio. Refresh both stage pages and confirm HTTP recovery.
5. Scan a fourth graduate while presenting. Confirm the row waits and the
   audience graduate does not change.
6. Replay, then complete the current presentation. Confirm the stage clears and
   does not automatically advance.
7. Present one selected graduate out of order, skip another, return the skipped
   graduate, and confirm it joins the queue tail.
8. Show and restore the holding screen. Confirm the current graduate is
   preserved and no operator/technical detail appears to the audience.
9. Re-scan waiting, presenting, completed, and skipped graduates and verify the
   precise status/recovery guidance.
10. Stop Reverb with `docker compose stop reverb`, scan while it is unavailable,
    and confirm PostgreSQL still commits. Start it with
    `docker compose start reverb`; both views must reconnect and reconcile via
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
4. Add/edit a graduate; confirm duplicate student/sequence errors identify the
   field and that the server assigns the QR token without an operator field.
5. Upload and replace a local photo and audio file, play the browser preview,
   remove each file, and confirm the missing-media state.
6. Review `/admin/audit-logs`; confirm important actions exist without raw QR
   values, original filenames, or media paths.
7. Sign in as `encoder@example.test`; confirm ceremony pages are read-only,
   graduate create/edit/media work, destructive actions are absent/forbidden,
   and `/admin/audit-logs` returns 403.
8. Re-run the Phase 3 scanner/stage flow using an eligible edited graduate and
   confirm uploaded media resolves from the persisted stage payload.

### Manual CSV import check

1. Sign in as `encoder@example.test`, open `/admin/students/import`, and download
   the template. Confirm it is local, UTF-8 with a BOM, and contains no QR/media
   fields.
2. Upload a realistic create-only CSV to the intended ceremony. Review the safe
   filename, row count, samples, suggestions, required mappings, and ignored
   columns.
3. Preview long names/programs, generated display/announcement names, no-honors
   data, multiple honors, normalized eligibility aliases, and 50-row pagination.
4. Verify duplicates, invalid eligibility, malformed rows, and conflicts with
   existing student/sequence numbers are blocked with recovery instructions.
5. Confirm a valid import only after checking the acknowledgment. Review the
   completion summary, ceremony-filtered graduate list, and administrator audit.
6. Confirm no page or audit output exposes a raw QR token or temporary CSV
   content, and that a second confirmation is forbidden.

### Manual QR printing and physical scanner check

Use ordinary white A4 stock and the intended ceremony printer. Do not use a
browser or printer option that rescales the sheet.

1. Sign in as `encoder@example.test`, open `/admin/students/qr-codes`, choose one
   ceremony, and review eligible, ineligible, regenerated, long-name, and
   long-program rows.
2. Open one individual card. Confirm the graduate identity, select **Open print
   sheet**, and verify A4 portrait, one 65 mm QR, 100% scale, no **Fit to page**,
   and no browser headers/footers.
3. Select representative eligible graduates and open the batch sheet. Confirm
   deterministic sequence order, at most four 55 mm cards per page, 12 mm page
   margins, intact borders/quiet zones, and no split card.
4. Print individual and batch samples in normal and grayscale modes. Confirm
   black modules, an opaque white quiet zone, readable matching text, and no
   clipping. Do not reduce the documented QR size.
5. Configure the USB 2D scanner for **USB HID keyboard** mode, the scanner
   laptop’s keyboard layout, QR/2D symbology enabled, no prefix, and one Enter or
   carriage-return suffix. Keep the device beep enabled so it confirms a local
   decode; the web page confirms server acceptance separately.
6. Open `/scanner`, click outside and return to the window, then confirm the QR
   input regains focus. Scan each printed sample once. Exactly one HTTP request
   and one accepted/rejected result must appear, the field must clear, and focus
   must return.
7. Scan rapidly, hold the trigger, press Enter twice, retry a waiting graduate,
   retry the current graduate, and try a completed graduate. Confirm
   `already_queued`, `already_presenting`, and `already_completed` remain
   specific and no duplicate queue row is created.
8. As an administrator, replace one non-presenting/non-completed test graduate’s
   token. Confirm the old printed card returns **QR code not recognized**, print
   the replacement, and confirm the replacement is accepted.
9. If a printed code does not decode, clean/flatten it and retry. Then expand
   **QR will not scan? Use controlled lookup**, search the active ceremony,
   verify the name/program/sequence aloud, and process one selection.
10. Repeat on the actual scanner laptop, printer, paper, local network, and venue
    operating distance before ceremony use.

Phase 6 browser automation verified generated SVGs, Chromium print media/PDF,
server acceptance, focus, long content, and fallback search. It did **not**
physically certify paper, the USB scanner, trigger timing, scanner beep/suffix
configuration, or an offline printer because those devices were unavailable.

## Automated verification

Run database tests inside the PHP container because the host PHP installation may
not include `pdo_pgsql`:

```bash
docker compose exec -T app php artisan test
docker compose exec -T app vendor/bin/pint --test
npm run lint
npm run build
docker compose exec -T app composer audit
npm audit
docker compose config --quiet
```

The completed Phase 7 verification passes 148 PostgreSQL tests with 1,217
assertions across authentication/domain behavior and Phases 3–7. The suite
includes the original critical path, administration, CSV preparation, QR
preparation/scanner fallback, the durable queue, and all independent-process
race coverage.

The Phase 3 tests include HTTP/access checks, all scan outcomes, scan-log and
payload privacy checks, post-transaction event ordering, rollback behavior,
private-channel authorization, stage refresh/completion, and separate-process
PostgreSQL races.

Phase 4 adds policy/route matrices, ceremony and graduate CRUD/validation,
search/filter/sort/pagination, activation and concurrent-activation protection,
presenting-state and deletion safeguards, upload validation and cleanup, audio
preview paths, audit redaction, and stage-media compatibility tests.

Phase 5 adds upload/role/ownership matrices, parser and hard-limit coverage,
mapping validation, normalization and row classification, current-data
conflicts, pagination, expiry, template privacy, transaction rollback,
confirmation revalidation, generated-token privacy, and safe import audits.

Phase 6 adds protected SVG/print authorization, payload and audit privacy,
readiness filters, deterministic/deduplicated/bounded batches, administrator-only
replacement, HMAC stale-request protection, transaction rollback, old/new-token
scan behavior, scanner fallback scoping, and manual-selection transaction tests.

Phase 7 adds queue transition, position, authorization, safe-payload,
controlled/automatic mode, replay, holding, recovery, and PostgreSQL uniqueness
tests. Independent-process races cover duplicate scans, two present-next
commands, next versus selected, skip versus present, duplicate return,
completion versus replay, and pause versus an automatic scan.

An optional live Chromium check exercises two authenticated browser profiles,
private-channel delivery, local audio playback events, refresh recovery, and a
deliberate Reverb stop/restart:

```bash
docker compose exec -T app php artisan migrate:fresh --seed --force
docker compose up -d --wait reverb
node tests/Support/phase3-browser-check.mjs
docker compose exec -T app php artisan migrate:fresh --seed --force
node tests/Support/phase4-browser-check.mjs
docker compose exec -T app php artisan migrate:fresh --seed --force
node tests/Support/phase5-browser-check.mjs
docker compose exec -T app php artisan migrate:fresh --seed --force
node tests/Support/phase6-browser-check.mjs
docker compose exec -T app php artisan migrate:fresh --seed --force
GSS_APP_URL=http://127.0.0.1:8001 node tests/Support/phase7-browser-check.mjs
```

These checks require Google Chrome at `/usr/bin/google-chrome` and must run only
against disposable seeded development data. The Phase 3 harness temporarily
stops Reverb and completes sample presentations. The Phase 5 harness creates
clearly prefixed sample graduates and writes review screenshots under
`docs/evidence/phase-5/`. Never run any browser harness against ceremony
production data or during live operations. The Phase 6 harness creates and
removes only clearly prefixed temporary graduates, including one through the
actual Phase 5 confirmation action, and records QR/print/scanner evidence under
`docs/evidence/phase-6/`. It also verifies data-encoder restrictions, old/new
replacement scans, repeated Enter locking, and controlled manual acceptance.
The Phase 7 harness uses three authenticated Chromium profiles, exercises
controlled and automatic presentation, pause/resume, selected presentation,
completion, skip/return, holding, replay and missing audio, refresh recovery, a
deliberate Reverb outage, role denial, long content, focus, reduced motion, and
the approved control/audience viewports. Its PNG evidence is written under
`docs/evidence/phase-7/` and remains ignored by Git.
Set `GSS_APP_URL` when the local app uses a non-default host port.

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
- **CSV cannot be inspected:** use UTF-8 with one unique header row, a consistent
  comma/semicolon/tab delimiter, and the documented size/row/column limits.
- **Import confirmation is unavailable:** return to mapping or correct the CSV
  until every required target is mapped once and no preview row is blocked.
- **Import preview expired:** upload the source again. Temporary content expires
  after 30 minutes and cannot be recovered from a browser page.
- **QR card is not printable:** confirm the graduate is eligible, has not
  completed a presentation, and has a current server token.
- **Printed QR will not decode:** verify 100% scale, white paper, intact quiet
  zone, QR/2D symbology, keyboard layout, and one Enter suffix. Reprint the
  individual 65 mm card before using controlled lookup.
- **Old card stopped working:** review administrator replacement history and
  print the current card. Regeneration intentionally invalidates older output.

## Phase boundary

Phase 7 does not add CSV update/synchronization/export, camera scanning, device
credentials, drag-and-drop queue reorder, bulk queue editing, microphone
recording, audio normalization/approval, operational reports, backups,
deployment automation, cloud media, or text-to-speech. Audio preparation and
approval remain Phase 8; later operational and deployment capabilities remain
in their assigned phases.

See [requirements](docs/requirements.md) and
[architecture](docs/architecture.md) for the normative Phase 3 through Phase 7
contracts. Do not begin Phase 8 until Phase 7 receives explicit human approval.
