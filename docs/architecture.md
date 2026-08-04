# Graduation Stage System Architecture

Document status: **Phase 12 verified; ENH-TTS-5 approval and stale-audio guard implemented**

Last updated: 2026-08-03

## 1. Architectural intent

The system is one local Laravel application whose React clients issue HTTP
commands, persist authoritative ceremony state in PostgreSQL, and receive
post-commit notifications through Reverb/Echo. The design favors correctness and
recovery over trusting browser state or transient WebSocket delivery.

The ceremony-runtime architecture has not changed. ENH-TTS-1 adds the
preparation-domain PostgreSQL foundation; ENH-TTS-2 adds pure composition and
safe SSML; ENH-TTS-3 adds an optional preparation-only provider and private
candidate workflow.

## 2. Current stack

| Layer | Implementation | Current responsibility |
| --- | --- | --- |
| Backend | Laravel 13.23 / PHP 8.3 | Sessions, policies, validation, transactions, CRUD, state API |
| Frontend | React 19 through Inertia 3 | Administration, scanner operator UI, and audience display |
| Build | Vite 8 | Local development and compiled assets |
| Database | PostgreSQL 16 | Sole source of business truth and concurrency arbiter |
| WebSocket server | Laravel Reverb 1.11 | Local committed-event transport |
| Browser client | Laravel Echo 2.4 + `pusher-js` 8.6 | Private subscription and reconnect signals |
| Scanner | USB 2D keyboard wedge | Types opaque QR token into `/scanner` |
| Media | Local public paths | Test fixtures and managed graduate photos/audio with readiness approval |
| QR rendering | BaconQrCode 3.1.1 | Local deterministic SVG from the current PostgreSQL token |
| Packaging | Docker Compose | Reproducible app, Reverb, and PostgreSQL processes |
| Backup | Docker-aware local scripts and metadata table | PostgreSQL dump, media copy, manifest, checksum, restore verification |
| Readiness | Phase 11–12 test and browser rehearsal harnesses | Release-candidate and real-world operations verification |

No Redis, queue worker, polling loop, separate SPA/API application, SQLite,
cloud-storage service, cloud speech service, or runtime CDN is used.

Cloud TTS is prohibited during ceremony runtime. Azure Speech is permitted only
during authorized preparation, with generated files reviewed, approved, and
stored locally before ceremony. ENH-TTS-3 adds a disabled-by-default network
path only for explicit authorized preparation generation. No ceremony path
calls it.

Phase 12 does not change the approved architecture. The runtime remains
HTTP for commands and recovery reads, PostgreSQL for authoritative state,
post-commit Reverb broadcasts, Echo browser delivery, local media, and
local-network-only deployment.

## 3. Physical and network topology

```text
                         Dedicated ceremony LAN

 Scanner laptop                                      Stage laptop
 ┌──────────────────────┐                            ┌──────────────────────┐
 │ USB 2D QR scanner    │                            │ Full-screen /stage   │
 │ Authenticated        │                            │ Authenticated        │
 │ React /scanner       │                            │ React + Echo         │
 │ HTTP POST :8000      │                            │ HTTP + WS :8080      │
 └──────────┬───────────┘                            └──────────┬───────────┘
            │                                                   │
            └──────────────────────┬────────────────────────────┘
                                   │
                          Server laptop
                ┌───────────────────────────────────┐
                │ Laravel HTTP             :8000   │
                │ Reverb WebSocket          :8080   │
                │ PostgreSQL        loopback only   │
                │ Compiled React and local media    │
                └───────────────────────────────────┘
```

`APP_BIND_HOST` controls exposure of HTTP and Reverb host ports:

- `127.0.0.1` for single-laptop development;
- `0.0.0.0` for an explicitly firewalled ceremony LAN.

The database port remains bound to `127.0.0.1` in both cases.

## 4. Reverb address model

The configuration intentionally separates server, publisher, and browser
addresses:

```text
REVERB_SERVER_HOST=0.0.0.0
REVERB_SERVER_PORT=8080
    Reverb's container listener

REVERB_HOST=reverb
REVERB_PORT=8080
    Laravel publisher target on the Docker network

VITE_REVERB_HOST=
VITE_REVERB_PORT=8080
    Browser target; empty host falls back to window.location.hostname
```

This prevents the common error where a scanner/stage laptop receives a compiled
`localhost` target and connects to itself.

`REVERB_ALLOWED_ORIGINS` is parsed as a comma-separated hostname list. The
configuration has no wildcard origin. `pusher-js` statistics are disabled to
avoid an external runtime request.

Reverb application ID/key/secret authenticate the Pusher-protocol application;
they are environment secrets, not user or device credentials.

## 5. Process model

Docker Compose defines:

- `app`: Laravel HTTP server on container port `8000`;
- `reverb`: long-running `php artisan reverb:start` on container port `8080`;
- `database`: PostgreSQL 16 with development and testing databases.

The Reverb service has a TCP health check. App and Reverb wait for PostgreSQL
health. There is no application dependency from app to Reverb because commands
must still commit when real-time transport is down. Reverb uses
`restart: unless-stopped` so an unexpected clean process exit is recovered while
an explicit operator stop remains stopped.

Destructive development migrations run before Reverb starts, or are followed by
a Reverb restart. Reverb watches a cache-backed restart marker, and replacing the
database cache table can correctly terminate the old server process.

Events implement `ShouldBroadcastNow`; therefore the runtime does not need a queue
worker. Vite is a development/build process, not a required compiled runtime
service.

## 6. Server component boundaries

### 6.1 HTTP layer

- `ScannerController` renders `/scanner` with only safe active-ceremony context.
- `ScanController` accepts the validated scan request and delegates to
  `ScanStudentForPresentation`.
- `StageController` renders `/stage` and returns safe `GET /stage/state` JSON.
- `CompleteCurrentPresentationController` delegates the clear action.
- `StageControlController` renders `/stage-control`, returns the authoritative
  safe queue snapshot, and delegates present, completion, wrong-current
  correction, skip/return, replay, holding, and automatic-pause commands.
- `StoreScanRequest` normalizes input, validates length, and produces structured
  authorization/validation failures.

Controllers do not implement the state machine.

Phase 4 adds `AdminDashboardController`, `CeremonyController`,
`StudentController`, and `AuditLogController`. They authorize policies, shape
purpose-built Inertia props, apply bounded query filters/pagination, and delegate
writes. `SaveCeremonyRequest` and `SaveStudentRequest` normalize and validate the
complete form contracts, including scoped uniqueness and actual file types.

Phase 5 adds `StudentImportController` with purpose-specific inspect, mapping,
preview, confirm, summary, and template methods. Three form requests enforce
create/owner policies and keep file, mapping, and acknowledgment validation at
separate HTTP boundaries. The controller never accepts browser-normalized rows as
confirmation input.

Phase 6 adds `StudentQrCodeController` for the protected readiness workspace,
individual SVG/review/print responses, bounded batch printing, and
administrator-only token replacement. `ScannerLookupController` provides a
bounded active-ceremony identity lookup. The selected graduate is submitted to
`ScanController::manual`, which delegates to the existing presentation action
instead of bypassing the scan transaction.

Phase 9 adds `ScanLogController`, `StageEventController`,
`CeremonySummaryController`, `BackupController`, and
`CeremonyOperationalResetController`. They authorize Laravel gates, use
purpose-built safe serializers, stream native CSV, expose local backup metadata,
and keep destructive restore out of browser routes.

Phase 12 adds `Admin\UserController` and `SaveUserRequest` for
administrator-only local account management. They return purpose-built account
fields without password data and delegate writes to a transactional domain
action.

### 6.2 Domain actions

`ScanStudentForPresentation` owns queue insertion, optional automatic initial
presentation, safe result mapping, scan logging, and post-transaction dispatch.

`CompleteCurrentPresentation` owns the completion transaction and post-transaction
clear dispatch.

`PresentStageQueueItem`, `SkipStageQueueItem`, `ReturnStageQueueItem`,
`ReplayCurrentAnnouncement`, `SetStageHolding`, and
`SetAutomaticPresentationPaused` each own one locked transition and its
post-commit events. `StageQueueTransitions` centralizes legal status changes.

`CompleteAndPresentNext` atomically completes the current queue item and either
presents the earliest waiting item or clears an empty queue. It requires the
browser's expected stage-state version so concurrent operators cannot advance
twice.

`ReturnCurrentPresentationToQueue` is the correction boundary for a wrongly
presented graduate. It preserves that item's queue position, returns it to
waiting, clears the current pointer into neutral holding, records the action,
and broadcasts only after commit.

Both catch transport exceptions only after commit, log identifiers and exception
class without credentials/payloads, and return a delivery flag to the authorized
operator command response.

`CeremonyAdministration` owns ceremony lifecycle writes, activation locking,
transition checks, presenting-state protection, audit insertion, and safe
deletion. `StudentAdministration` owns graduate CRUD, media replacement/removal
coordination, audio readiness/approval/rejection, safe audit diffs, and
history-aware deletion.

`UserAdministration` owns serialized account creation and updates, safe audit
values, self-demotion/deactivation prevention, and last-active-administrator
protection. Password hashes remain model state only and never enter audit or
Inertia payloads.

`InspectStudentImport` parses one uploaded file and creates an expiring,
owner-bound import row. `ConfirmStudentImport` owns the locked, create-only
confirmation transaction, server QR generation, per-graduate safe audits,
completion/failure summary, and encrypted-source cleanup.

`PrepareStudentQrBatch` locks and revalidates a maximum of 100 selected
graduates, renders only printable records, orders cards deterministically, and
writes one safe count/filter audit. `RegenerateStudentQrToken` locks one
graduate, verifies an opaque optimistic version, blocks presenting/completed
history, replaces the token, and records a redacted audit in the same
transaction.

### 6.3 State helpers

- `ActiveCeremonyResolver` returns `ready`, `no_active_ceremony`, or
  `multiple_active_ceremonies`; it can lock the active ceremony rows.
- `StageStatePayload` is the one serializer for safe HTTP/broadcast presentation
  fields and relative local media URLs.
- `StageControlPayload` builds the current/waiting/skipped/recent operator
  snapshot without tokens or student numbers.
- `ScanAttemptResult` centralizes the public scan response.
- `StudentMediaStorage` is the only writer/deleter for managed Phase 4 media.
  It recognizes only `storage/graduates/...` paths for cleanup.
- `AnnouncementAudio` centralizes local audio readiness, approved preview URLs,
  safe filenames, and readability checks for managed graduate audio and explicit
  development fixtures.
- `AdministrativeAuditLogger` inserts actor/entity/action/IP and caller-provided
  safe values without accepting a serialized model.
- `StudentCsvParser` uses native `fgetcsv` for bounded UTF-8/BOM, delimiter,
  header, row-structure, column, and cell handling.
- `StudentImportPreparation` owns exact alias suggestions, mapping validation,
  normalization, Phase 4 field validation, generated-name warnings, in-file
  duplicate detection, and current PostgreSQL conflict classification.
- `StudentImportLimits` is the single server/browser source for Phase 5 size,
  row, column, cell, pagination, and expiry limits.
- `StudentQrTokenGenerator` owns the `GSS-` plus 48-character random token
  contract for manual creation, CSV creation, and explicit replacement.
- `QrCodeRenderer` owns the on-demand SVG contract: error correction Q, opaque
  white background, black modules, four-module quiet zone, and a 512-unit view
  box.
- `StudentQrReadiness` derives printable/blocking labels from authoritative
  graduate and presentation history. It creates no parallel readiness column.
- `StudentQrTokenVersion` creates an application-keyed HMAC from the graduate,
  current token, and update timestamp. The browser receives the fingerprint,
  never the token.
- `OperationalLogSerializers` turns scan logs, stage events, and audit logs into
  allowlisted operator records without raw tokens, unrestricted JSON payloads,
  storage paths, or secrets.
- `SafeCsvExporter` streams UTF-8 CSV and applies formula-injection mitigation to
  every cell.
- `CeremonyCompletionSummary` owns PostgreSQL-derived report metrics and their
  definitions.
- `BackupManager` records backup metadata and supports environments with
  available dump tooling. The verified Docker workflow is implemented in
  `scripts/gss-backup-docker.sh`.
- `RestoreVerifier` and `scripts/gss-restore-verify-docker.sh` validate manifests
  and checksums before restoring into a disposable PostgreSQL database.

### Phase 9 backup metadata decision

Phase 9 adds a minimal `backup_records` table because authorized operators need
to view the latest backup status and verification metadata in the local app. The
table stores labels and checksums, not backup content or secrets. Full backup
content remains on the local filesystem outside `public/`.

The `student_imports` table is deliberately temporary workflow state:

```text
token UUID route key
user_id owner
ceremony_id nullable FK
safe original_filename + create_only mode
delimiter + JSONB headers/mapping
encrypted source_content
row_count + JSONB safe summary
expires_at + consumed_at + timestamps
```

No import upload path or public temporary file exists.

### 6.4 Events

- `StudentPresented` broadcasts `.student.presented`.
- `StagePresentationCleared` broadcasts `.stage.cleared`.
- `AudioReplayRequested` broadcasts `.audio.replay-requested`.
- `StageHoldingChanged` broadcasts `.stage.holding-changed`.
- `QueueUpdated` broadcasts `.queue.updated` on the separate operator channel.

The event objects contain only ceremony ID and a purpose-built safe array. They do
not serialize Eloquent models. Queue updates are deliberately compact and cause
the control browser to refetch HTTP state.

## 7. Scan transaction

```text
POST /scans
    │
    ├─ session + active account + operate-scanner authorization
    ├─ normalize token (trim + uppercase)
    │
    └─ PostgreSQL transaction (deadlock retry limit: 3)
         │
         ├─ SELECT active ceremonies FOR UPDATE
         │    └─ require count = 1
         ├─ SELECT stage_states FOR UPDATE
         │    └─ create idle row only if absent
         ├─ find student inside active ceremony
         ├─ check eligible
         ├─ check active/completed/skipped history
         ├─ allocate MAX(queue_position) + 1
         ├─ INSERT stage_queue_items(status = waiting)
         ├─ INSERT stage_events(student_queued)
         ├─ INSERT scan_logs(hash, result, reason, actor)
         ├─ version + 1
         └─ if automatic + idle + unpaused + earliest:
              ├─ mark row presenting
              ├─ update current/display state
              ├─ version + 1
              └─ INSERT student_presented_automatically
              │
              └─ COMMIT
                   │
                   ├─ dispatch QueueUpdated synchronously
                   └─ if promoted, dispatch StudentPresented synchronously
```

Expected rejections return from inside the transaction after inserting a safe
scan log. Because they do not carry a broadcast payload, no success event is
dispatched.

The action dispatches only after `DB::transaction` returns. A forced stage-event
failure test proves queue, state, log, and event writes roll back together.

## 8. Concurrency design

All scan and stage-control commands for a ceremony serialize on its stage-state
row. The active ceremony row is also locked to keep ceremony resolution stable
during the command.

Layered invariants:

1. Application checks return precise operator outcomes.
2. `FOR UPDATE` prevents two commands from both observing an idle stage or
   allocating the same queue position.
3. PostgreSQL partial unique index
   `stage_queue_items_one_presenting_unique` prevents two presenting rows.
4. PostgreSQL partial unique index
   `stage_queue_items_one_active_student_unique` prevents duplicate active rows
   for one student.
5. PostgreSQL partial unique index
   `stage_queue_items_ceremony_position_unique` prevents duplicate non-null
   positions.
6. Stage-state `version` creates an ordered client reconciliation boundary.

Separate-process integration tests start two independent PHP/PostgreSQL
connections behind a shared barrier. Phase 7 verifies duplicate scan, two
present-next, next versus selected, skip versus selected presentation,
duplicate return, completion versus replay, and pause versus automatic-scan
races. Acceptable winners differ, but final persisted state always follows one
legal transition and has at most one presenting row.

### 8.1 Ceremony administration concurrency

Every Phase 4 ceremony create/update/delete transaction first obtains a fixed
PostgreSQL transaction-scoped advisory lock, then locks ceremony rows. This
serializes the empty-table case as well as updates to different ceremony rows.
Activation checks run only after that lock, so two concurrent requests cannot
both observe “no active ceremony.” An independent-process test proves one request
activates and the other receives a validation conflict.

The existing active-ceremony resolver still rejects multiple active rows. That
defensive path is retained for direct database corruption or legacy data and for
Phase 3 operational recovery; administration does not rely on ambiguity.

### 8.2 CSV confirmation concurrency

Every Phase 5 confirmation obtains a fixed PostgreSQL transaction-scoped advisory
lock before locking the temporary import, destination ceremony, and current
graduate rows. This conservatively serializes import confirmations. Manual
graduate writes do not take that advisory lock, so the complete CSV is reparsed
after locks and database uniqueness constraints remain responsible for any
concurrent manual insert.

Database unique constraints remain the final protection for ceremony/student
number, ceremony/sequence, and global QR token races. A uniqueness exception or
any later error rolls back all imported students and their in-transaction audit
rows. The action translates database failures into safe operator recovery text;
it never reports a partial success.

## 9. Completion transaction

```text
POST /stage/complete-current
    │
    ├─ session + active account + operate-stage authorization
    │
    └─ transaction
         ├─ lock exactly-one active ceremony
         ├─ lock stage state
         ├─ lock current queue item
         ├─ require queue status = presenting
         ├─ mark queue item completed
         ├─ clear current stage; mode idle; audio stopped
         ├─ version + 1
         └─ insert student_completed event
              │
              └─ COMMIT
                   └─ dispatch StagePresentationCleared
```

If the stage is idle, nothing is written or broadcast. If persisted state is
internally inconsistent, Phase 3 returns `state_conflict` for operator review
instead of silently rewriting history.

Phase 12 adds an adjacent operator-confirmed transaction:

```text
POST /stage/complete-and-present-next + expected_version
    │
    └─ transaction
         ├─ lock active ceremony, stage state, current, earliest waiting
         ├─ reject stale expected_version
         ├─ complete current
         ├─ next exists: mark it presenting and point stage state to it
         ├─ no next: clear stage state
         ├─ version + 1 and insert committed stage event records
         └─ COMMIT
              ├─ next exists: StudentPresented + QueueUpdated
              └─ no next: StagePresentationCleared + QueueUpdated
```

There is no intermediate audience-clear event when a next graduate exists.
`POST /stage/return-current` uses the same expected-version guard to return a
wrongly presented current item to waiting, clear the pointer, and activate the
neutral holding presentation without completing it.

## 10. Real-time channel and authorization

Laravel registers:

```text
stage.{ceremonyId}
stage-control.{ceremonyId}
```

Echo subscribes as:

```text
private-stage.{ceremonyId}
private-stage-control.{ceremonyId}
```

The channel callback requires:

- an authenticated active user whose gate allows `operate-stage`;
- exactly one active ceremony; and
- an integer channel ceremony ID equal to that active ceremony.

Stage operators and administrators pass. Guests, inactive users, scanner/data/
technical roles, inactive ceremonies, and ambiguous active ceremonies fail.

The browser authorizes subscriptions through same-origin
`/broadcasting/auth`. Reverb itself never decides application role access.

## 11. Safe presentation contract

The canonical presentation array is:

```json
{
  "event_id": 31,
  "version": 8,
  "student_id": 104,
  "display_name": "Example Graduate",
  "program": "Bachelor of Science",
  "major": null,
  "honors": "Cum Laude",
  "photo_url": null,
  "audio_url": "/media/phase3/audio/student-01.wav",
  "presented_at": "2026-07-28T12:34:56+08:00"
}
```

This contract is shared by the success event and HTTP recovery state. QR token,
student number, email, user/operator identity, device credentials, model
attributes, internal status, and debugging data are absent.

Scan responses expose only the accepted graduate ID/display name needed by the
scanner operator. Rejected responses expose no graduate identity.

## 12. Browser architecture

### 12.1 Central Echo module

`resources/js/lib/echo.js` owns the single Echo/Pusher configuration, chooses the
page hostname when no explicit browser host is configured, exposes connection
state, uses WS/WSS only, disables external statistics, and points private auth at
Laravel.

Pages do not duplicate WebSocket configuration.

### 12.2 Scanner

The scanner is a controlled form around a focused text input. It uses a same-
origin JSON HTTP helper with the Blade CSRF meta token. Submission is locked until
the response, then the input clears and refocuses. Echo connection state is
informational; the HTTP command remains available even if Reverb is recovering.

### 12.3 Stage

Stage startup ordering:

1. `GET /stage/state`
2. subscribe to the private channel
3. reconcile again on subscription success

For each event:

- `version <= current`: ignore as stale/duplicate;
- `version == current + 1`: apply;
- `version > current + 1`: retrieve HTTP state.

After a disconnected connection returns to connected, the client retrieves HTTP
state. Refresh therefore restores current state without any event replay system.

### 12.4 Phase 7 stage control

The control browser first retrieves `GET /stage/queue` and `GET /stage/state`,
then subscribes to `private-stage-control.{ceremonyId}`. Every newer
`.queue.updated` event triggers a fresh pair of HTTP reads. Reconnection does the
same. The browser never reconstructs queue order from events.

One in-memory command lock and disabled controls prevent rapid duplicate HTTP
actions. This is only a usability boundary; PostgreSQL row locks, state checks,
and unique indexes remain authoritative. Current presentation and primary
action dominate the layout, the deterministic waiting table is secondary, and
skipped/recent history is tertiary.

Phase 12 keeps that hierarchy and changes the primary action according to
authoritative state. With a current graduate and a waiting row, **Complete and
present next** is dominant. **Complete and clear** remains a deliberate
secondary action. **Correct wrong graduate** is a destructive-looking but
history-preserving recovery action with explicit confirmation. When there is no
current graduate, present-next/present-selected remains available even if the
audience is in neutral holding after a correction.

The legacy database value `automatic` is rendered as **Assisted automatic**.
This makes the actual scan-to-idle-stage behavior clear without changing stored
data, event contracts, or queue rules.

### 12.5 Phase 4 administration

All administration pages are Inertia views in the same application:

- `AdminLayout` provides role-aware navigation, identity, logout, and flash
  feedback;
- ceremony/graduate forms use `useForm`, field-linked errors, validation summary,
  pending-submit locks, and multipart method spoofing for file updates;
- list filters use ordinary GET query strings, so refresh/back/pagination remain
  understandable;
- table containers own horizontal overflow at the 1024 px target;
- QR tokens are assigned by Laravel and are never accepted by or included in
  ordinary create/list/detail/edit form props; and
- native audio controls coordinate playback so only one preview plays.

The administration UI has no stage commands, queue controls, fake analytics, or
runtime WebSocket dependency.

Phase 12 adds an administrator-only account list and create/edit form using the
same layout, validation summary, pending lock, bounded filters, and responsive
table pattern. Role-aware navigation chooses an authorized administrative home;
technical operators are not linked to the data-preparation readiness route.

### 12.6 Phase 5 import workspace

The CSV workflow is four separate Inertia pages sharing `AdminLayout`,
`ImportProgress`, validation support, and a compact count definition list:

```text
upload → mapping → server preview/confirmation → safe summary
```

`useForm` controls pending/disabled submission states. Native file, select,
checkbox, and button controls remain keyboard operable and field-linked errors
are included in a summary. Mapping and preview tables own horizontal overflow;
the page body does not. The preview renders only server-provided normalized
values, row findings, and counts. It never renders source content or QR tokens.

There is no frontend CSV parser, client-side import authority, drag-and-drop
library, analytics panel, WebSocket dependency, cloud upload, or added UI
framework.

### 12.7 Phase 6 QR preparation

The protected QR workspace is an Inertia administration screen that derives
readiness from PostgreSQL and offers filters, pagination, individual review, and
bounded batch selection. It never sends a token to React. An individual SVG is
returned from a separately authorized, private no-store response. Print HTML
uses embedded SVG data URIs so Chromium can produce a self-contained local
print job without exposing a raw payload in page text, URLs, filenames, or
remote requests.

```text
GET readiness/review
    → authorized PostgreSQL read
    → safe identity/readiness props only

GET image or print / POST batch
    → policy + validation
    → current PostgreSQL token
    → local SVG render
    → no-store SVG or standalone print HTML

POST regenerate
    → administrator policy + explicit acknowledgment
    → row lock + HMAC version check
    → replace token + safe audit in one transaction
```

Individual output is A4 portrait with 12 mm margins and one 65 mm QR. Batch
output is A4 portrait with 12 mm margins, a two-column grid, no more than four
55 mm QR cards per page, and no more than 100 graduates per request. Browser
headers/footers and fit-to-page scaling are operational printer settings, not
application state.

The scanner fallback is deliberately secondary. It queries at most ten safe
identity results from the sole active ceremony and then submits one verified
student ID over HTTP. `ScanStudentForPresentation::executeStudent` resolves and
locks that student inside the same transaction used by token scans. Eligibility,
duplicate/completed/busy rules, state/event/log persistence, post-commit Reverb
delivery, and HTTP recovery therefore remain one shared path.

The domain action continues to throw unexpected transaction exceptions so
rollback behavior remains testable. At the HTTP boundary, `ScanController`
catches such an exception before Laravel reports request arguments, writes only
operator ID, command type, and exception class, and returns safe
`request_failed` JSON. This keeps unexpected database failures actionable on the
scanner without placing even a failed raw token in application logs.

## 13. Audio architecture

Phase 8 separates file existence from production readiness. Graduate audio
readiness is stored on `students` as `missing`, `uploaded`, `needs_review`,
`approved`, or `rejected`, with approval timestamp/user, safe review notes,
upload time, MIME type, file size, and optional duration metadata. PostgreSQL
remains authoritative; no audio binary, waveform, cloud identifier, raw QR value,
or server path is stored in audit payloads.

The graduate media write path remains compensating:

```text
validate local file -> store generated path -> DB transaction + audit
    ├─ rollback: delete new file and keep old approved metadata
    └─ commit: delete old managed file and reset approval to uploaded
```

Approval and rejection are separate administrator actions. Data encoders can
upload/replace, preview, and mark needs review, but cannot approve by default.
Rejection preserves the file for authorized review and blocks automatic stage
playback until replacement.

The production playback rule is approved-only:

```text
approved + readable same-origin local audio -> stage payload audio_url
all other statuses or unreadable file -> null audio_url
```

The stage keeps at most one browser `Audio` object:

- a new presentation pauses, rewinds, and releases the prior object;
- a clear stops the current object;
- playback begins only after the one-time browser interaction;
- a null audio URL produces visual-only presentation; and
- a rejected `play()` promise is not exposed on the audience screen.

Replay is an authorized HTTP command that locks the current state, verifies the
current graduate still has approved readable audio, records a versioned
`announcement_replayed` event, broadcasts after commit, and never changes queue
status. Completion and holding keep using the existing stage mode transitions to
stop audience playback.

Stage HTTP recovery includes a safe `preload_audio_url` for the next waiting
approved clip when known. The browser preloads current and this single next
same-origin URL only; it does not load the full ceremony library.

Four short PCM WAV tones remain versioned under `public/media/phase3/audio`.
They validate delivery/playback plumbing only and seed as review-required test
fixtures. Browser regression scripts may explicitly approve them in development.

Duration and loudness are manual review items. No media-inspection package,
system binary, waveform library, cloud TTS, or normalization tool was added.

## 14. Failure and recovery

| Failure | Authoritative state | Recovery |
| --- | --- | --- |
| Browser refresh | PostgreSQL stage/queue | HTTP state then private subscription |
| Missed/duplicate event | PostgreSQL version | Ignore stale or fetch on gap |
| Reverb unavailable during command | Transaction remains committed | Delivery flag/log; reconnect and HTTP fetch |
| Reverb restart | No business state lost | Echo reconnect then HTTP fetch |
| Laravel restart | PostgreSQL/media remain | Restart app; session/login; HTTP fetch |
| PostgreSQL unavailable | No command may claim success | Restore DB, validate, retry |
| Concurrent scans | Locked state + position/active indexes | Each eligible graduate persists once; automatic mode presents at most one |
| Concurrent stage commands | Locked state/item + transition checks | One legal winner; safe conflict and HTTP reconciliation |
| Completion/replay race | Locked current state | Completion cannot be undone by replay |
| Reconnect with queue changes | PostgreSQL queue/version | Compact event or reconnect triggers HTTP refetch |
| Transaction exception | Rollback | No partial rows or success event |
| CSV malformed/oversized | No temporary import | Correct local file and inspect again |
| Import mapping/row error | Encrypted temporary row | Remap or correct/re-upload; confirm stays absent |
| Import expires | Temporary row deleted | Upload the source again |
| Conflict appears after preview | Current PostgreSQL rows | Confirmation recheck blocks and imports none |
| Mid-import/database failure | Transaction rollback | No graduates/completion audit; safe retry guidance |
| QR print row changed after selection | Current PostgreSQL graduate/history | Batch lock/revalidation rejects the job |
| Concurrent token replacement | Locked row + HMAC version | One replaces; stale request reloads |
| Token replacement audit/write failure | Transaction rollback | Previous printed token remains valid |
| Printed QR cannot decode | Current active-ceremony student row | Controlled lookup and shared scan transaction |
| Old card used after replacement | Current PostgreSQL token only | Invalid-token rejection; reprint current card |
| Missing media | Presentation record remains valid | Visual fallback |
| Zero/multiple active ceremonies | No implicit selection | Correct ceremony data operationally |

The audience view displays only `Live` or `Syncing`; detailed failures belong in
authorized operator/server logs.

## 15. Security boundaries

1. **Browser → Laravel:** session, active-account middleware, gate, CSRF, input
   validation, safe errors.
2. **Echo → private channel:** Laravel channel authorization before subscription.
3. **Laravel → PostgreSQL:** server-only credentials and constrained schema.
4. **Laravel → Reverb:** application credentials on the private Docker network.
5. **Browser → Reverb:** public application key plus authorized private channel;
   explicit origin hostname allowlist.
6. **Audience display:** purpose-built safe data only.
7. **Administration:** session + active middleware + Ceremony/Student/AuditLog
   policies; hidden links never substitute for server authorization.
8. **Media:** generated local relative paths under the public storage link, no
   original filename or external URL generation; only managed prefixes are
   eligible for cleanup.
9. **Audit:** same-transaction, administrator-only history with purpose-built
   allowlisted values rather than serialized request/model data.
10. **Import custody:** owner-specific policy, CSRF, hard parser limits, encrypted
    PostgreSQL source content, random UUID route key, UTC expiry, and no public
    temporary file.
11. **Imported QR custody:** generated only inside Laravel, globally constrained,
    and absent from CSV/template/Inertia/summary/audit contracts.
12. **Rendered QR custody:** authenticated policy checks, no-store/noindex SVG
    and print responses, token-free URLs/filenames/page text, same-origin local
    rendering, and no persisted QR artifacts.
13. **Token replacement:** administrator-only acknowledgment, PostgreSQL row
    lock, opaque HMAC stale-request rejection, history guards, and same-
    transaction redacted audit.
14. **Scanner fallback:** active-ceremony-only bounded search, allowlisted
    identity response, verified selection, and the same authorized scan action;
    no token disclosure or operational override.

Raw scanned tokens are hashed in operational logs. Reverb/DB credentials and
development passwords remain in the ignored `.env`.

## 16. Test architecture

Phase 3 adds PostgreSQL-backed tests for:

- every role and guest route boundary;
- structured unauthorized scan response;
- accepted, invalid, inactive/ambiguous ceremony, ineligible, duplicate,
  completed, and busy scan outcomes;
- log/state/queue/event persistence;
- post-action transaction level during success dispatch;
- no success event on rejected or failed transactions;
- forced rollback with no partial state;
- safe presentation field allowlist and prohibited-value search;
- idle/restore/completion stage behavior;
- active, denied, inactive, and ambiguous private channels; and
- two independent-process concurrency races.

Frontend verification uses ESLint, the Vite production build, HTTP checks, live
browser sessions, refresh recovery, and local media checks. No new heavy frontend
test runner was introduced. `tests/Support/phase3-browser-check.mjs` drives two
temporary headless Chromium profiles through the actual compiled application and
deliberately restarts Reverb; it is restricted to a freshly seeded development
database.

Phase 4 adds PostgreSQL-backed tests for:

- the guest and five-role administration authorization matrix;
- ceremony/graduate CRUD, validation recovery, scoped/global uniqueness,
  search/filter/sort/pagination, and query preservation;
- ceremony transition, presenting-state, deletion, and simultaneous-activation
  protections;
- graduate history-aware deletion and reviewed-name/hidden-token preservation;
- actual media validation, generated paths, replacement/removal cleanup, and
  local preview URLs;
- redacted audit contents and administrator-only audit history; and
- uploaded-media compatibility with the unchanged Phase 3 stage serializer and
  presented event.

`tests/Support/phase4-browser-check.mjs` drives an authenticated administrator
through the compiled administration routes at the required viewports and checks
overflow, focus, long content, missing media, audio preview, validation/no-result
states, and role denial without adding a browser test package.

Phase 5 adds PostgreSQL-backed tests for:

- guest/inactive/role and cross-owner route boundaries;
- UTF-8/BOM, quoted values, supported delimiters, malformed input, and every
  hard parser limit;
- exact mapping suggestions, required/duplicate mapping errors, normalization,
  generated fields, duplicate/current conflicts, and 50-row pagination;
- encrypted raw storage, expiry deletion, local template safety, and QR/audit
  privacy;
- confirmation revalidation, duplicate confirmation denial, ceremony closure,
  uniqueness races, and injected mid-import rollback; and
- valid all-row creation with collision-resistant internal tokens and safe
  completion summary.

`tests/Support/phase5-browser-check.mjs` exercises the compiled real workflow at
1440×900, 1366×768, and 1024×768. It checks the local template, upload, mapping,
warning and blocked previews, confirmation, summary, graduate retrieval, safe
audit, visible focus, reduced motion, same-origin resources, overflow
containment, and denied scanner role.

Phase 6 adds PostgreSQL-backed tests for:

- local deterministic SVG structure, expected token payload handling, no-store
  responses, and absence of raw values from ordinary Inertia/audit contracts;
- QR readiness, filter/pagination behavior, individual and batch access,
  cross-ceremony/blocked/max-size rejection, physical print CSS, and
  deterministic ordering;
- administrator-only token replacement, old/new scan behavior, unchanged
  identity/history, stale concurrent request rejection, presenting/completed
  protection, redacted audit, and rollback on failure; and
- scanner lookup limits/privacy/authorization, shared validation and
  transaction semantics, rapid-submit behavior, and post-commit event delivery.

`tests/Support/phase6-browser-check.mjs` exercises the compiled QR workspace,
individual and batch print views, replacement warning, scanner QR/manual paths,
visible focus, reduced motion, long content, contained overflow, same-origin
resources, and Chromium print-to-PDF at the approved laptop/A4 viewports.

Phase 7 adds PostgreSQL-backed tests for legal queue transitions, monotonic
positions and the position index, controlled/automatic behavior, persisted
pause, stage-command authorization, event privacy, post-commit dispatch, replay,
holding, and recovery. Independent-process tests cover all identified
stage-command races.

`tests/Support/phase7-browser-check.mjs` drives scanner, stage-control, and
audience profiles through the compiled application. It checks controlled and
automatic workflows, pause/resume, selection, completion, skip/return, replay,
holding, missing audio, refresh and Reverb recovery, role denial, long content,
focus, reduced motion, audience safety, overflow, and every approved Phase 7
viewport. PNG evidence is local and ignored by Git.

Phase 8 adds PostgreSQL-backed tests for announcement audio upload metadata,
role boundaries, needs-review/approval/rejection transitions, replacement
invalidation, failed replacement compensation, safe audit payloads, approved-only
stage payloads, replay rejection for unapproved audio, and next-audio preload
state.

Phase 9 adds tests for log authorization, safe serializers, filter behavior,
CSV export privacy and formula-injection mitigation, ceremony summary accuracy,
backup destination/failure safety, backup metadata privacy, and operational
reset history preservation. Real Docker backup creation and disposable restore
verification are exercised with `scripts/gss-backup-docker.sh` and
`scripts/gss-restore-verify-docker.sh`.

## 17. Phase boundary and future architecture

Phase 7 implements the durable waiting queue, controlled presentation,
automatic initial presentation with persisted pause, selected presentation,
completion, skip/return, replay, holding, and HTTP/WebSocket reconciliation
without bypassing the transaction, version, event, or recovery principles.

Phase 10 adds the deployment layer around the existing architecture:

- `compose.deploy.yaml` is the single production-oriented Compose override.
- `docker/php/Dockerfile.deploy` builds a release image containing the Laravel
  app and compiled frontend assets.
- `scripts/gss-start.sh` starts PostgreSQL, Laravel, and Reverb in order and
  can apply `php artisan migrate --force`.
- `scripts/gss-stop.sh` stops services without removing volumes and can request
  a final backup.
- `scripts/gss-health-check.sh` verifies service health, assets, ports, disk,
  writable storage, migrations, Reverb, and PostgreSQL loopback exposure.
- `scripts/gss-build-release.sh` validates the local release before event use.

The deployment layer does not change scan validation, queue transactions,
post-commit broadcasts, Echo recovery, audio rules, backups, or reporting.

Still deferred after Phase 12:

- CSV update/synchronization/export;
- device authentication/registration;
- arbitrary reorder, bulk queue mutation, and mode switching during a ceremony;
- browser recording, automatic normalization, and advanced media-production
  tooling;
- public hosting, reverse proxy/TLS, external process supervision, firewall
  automation, kiosk automation, automatic failover, and full physical rehearsal.

## 18. Risks and unresolved decisions

| Risk | Current response | Remaining decision |
| --- | --- | --- |
| LAN host/origin mismatch | Browser host fallback, origin allowlist, deployment env example, network plan | Final venue IP/hostname |
| Broadcast outage | Commit first, catch delivery failure, HTTP recovery, Reverb health/start scripts | Final operator monitoring model |
| Browser autoplay | One-time stage interaction and device setup checklist | Physical venue audio test |
| Long/missing media | Responsive text and visual-only fallback | Institutional media standards |
| Physical hardware mismatch | Manual checklist | Approved scanner/display/sound hardware |
| Session on audience device | Stage role session and private channel | Final device credential/kiosk model |
| HTTP on local LAN | Controlled local-only setup and firewall guidance | Local TLS/WSS decision |
| Ceremony ambiguity | Reject zero/multiple | Final multi-ceremony policy |
| Concurrent stage commands | Ceremony/stage/item row locks plus partial unique indexes and safe conflicts | Final load/latency target |
| Queue position gaps | Monotonic positions preserve history; UI does not imply dense numbering | Whether later reporting needs a separate display rank |
| No external process supervisor | Compose health checks and restart policies | Whether a later service manager is approved |
| Public local media URL | Generated names, controlled LAN, no external origin | Whether production delivery requires a policy-authorized media controller |
| Upload filesystem/DB split | Store-first compensation and managed-prefix cleanup | Backup/restore and orphan reconciliation procedure |
| Media standards | Actual-type and size validation | Final dimensions, duration, loudness, approval, and retention |
| Temporary CSV sensitivity | Encrypted owner-bound row, hard limits, 30-minute UTC expiry, cleared content | Final retention/privacy notice |
| Import concurrency throughput | Global advisory lock and full current-state recheck | Whether later ceremony-scoped parallelism is required |
| Create-only correction | Conflicts block and existing rows never change | Approved future update/sync identity and merge rules |
| Printer/scanner variance | Fixed SVG/physical CSS contract and reproducible checklist | Approved devices, grayscale/scale/distance matrix |
| Lost/replaced paper cards | Immediate server-side invalidation and redacted replacement audit | Institutional custody/reprint/destruction procedure |
| Staff card identity | Student number appears only on protected staff print cards | Final privacy approval for printed matching fields |

## 19. Phase 12 operations architecture

Phase 12 adds no service, datastore, polling loop, UI library, external asset, or
runtime network dependency. It extends existing HTTP and transaction boundaries:

- stage-control sends `expected_version` with the two new recovery/advancement
  commands;
- PostgreSQL locks decide the only successful concurrent transition;
- stage and queue events are persisted before post-commit Reverb dispatch;
- Echo remains advisory and every browser can recover from HTTP;
- user administration uses existing session authentication, Laravel gates,
  Inertia forms, password hashing, and audit storage; and
- the `automatic` enum value remains compatible while its operator label becomes
  **Assisted automatic**.

Unattended timer-driven advancement and audio-ended advancement are deliberately
not introduced. Human confirmation remains the safety boundary between names.
The physical event status remains conditional until the actual scanner,
display, audio system, network, and devices complete the venue rehearsal.

Phase 12 and ENH-TTS-0 were reviewed before the additive ENH-TTS-1 work began.

## 20. ENH-TTS-1 preparation-domain architecture

ENH-TTS-1 adds five ordered PostgreSQL migrations. Student announcement/source
columns are added first; awards and templates follow; generations then reference
the existing `(ceremony_id, student_id)` identity and the template's
`(ceremony_id, id)` identity; the nullable student-to-generation reference is
added last. Rollback reverses that order and removes the circular edge before
dropping generation history.

```text
ceremonies
    ├── students
    │     ├── student_awards (ordered by display_order, id)
    │     └── announcement_generations
    └── announcement_templates
          └── announcement_generations

students.(ceremony_id, id, audio_generation_id)
    ──> announcement_generations.(ceremony_id, student_id, id) (nullable)
```

PostgreSQL remains the concurrency and integrity boundary:

- a partial unique index permits historical inactive templates while enforcing
  one active template per ceremony;
- activation locks the ceremony row, deactivates the former template, and
  activates the requested version in one transaction;
- composite foreign keys reject student/template ceremony mismatches and prevent
  a student from linking another graduate's generation as active audio;
- checks constrain providers, generation statuses, hashes, counters, template
  versions, rates, pauses, and character limits; and
- restrictive generation foreign keys preserve history while award rows cascade
  only with an otherwise deletable student.

`BuildAnnouncementSourceFingerprint` accepts a typed source DTO. It recursively
sorts associative keys, preserves award-list order, emits unescaped Unicode and
slashes without insignificant whitespace, and hashes the JSON with SHA-256.
Only allowlisted announcement fields are representable, so timestamps,
credentials, paths, request IDs, and review notes cannot enter the fingerprint.

`AnnouncementGeneration` hides candidate and approved paths from default model
serialization and prevents mutation of its persisted source snapshot through
the model. No large or sensitive relation is globally eager-loaded.

The existing `Gate::before` continues to deny inactive users and grant active
administrators. Named announcement gates add data-encoder award/generation
preparation authority without granting review authority to encoders or any
announcement authority to stage, scanner, or technical operators.

Manual upload and import actions classify audio as `manual_upload`; replacement
clears any generation link/hash; removal clears all source metadata. The
existing `AnnouncementAudio`, stage serializers, Reverb events, playback gate,
routes, and React pages are unchanged. No Azure client, synthesis request, SSML
builder, candidate write, generation endpoint, promotion workflow, or batch
worker exists in ENH-TTS-1.

## 21. ENH-TTS-2 pure composition architecture

The new application flow is read-only and server-local:

```text
Student + active AnnouncementTemplate + ordered StudentAward rows
    -> PreviewGraduationAnnouncement
    -> normalized AnnouncementCompositionInput
    -> ComposeGraduationAnnouncement
       -> ComposedAnnouncement + source fingerprint
    -> BuildGraduationAnnouncementSsml
       -> AnnouncementSsmlResult
```

The DTO boundary deliberately excludes QR tokens, student numbers, media paths,
timestamps, credentials, provider responses, and review data. The composer and
award formatter operate on immutable DTOs rather than unrestricted Eloquent
models. Normalization collapses Unicode whitespace but preserves approved
wording. Award ordering is `display_order`, then identifier. Disabled award
records remain in the fingerprint with their enablement flag but cannot enter
spoken sections.

Composition is deterministic and section-aware. The fixed order, conservative
whole-phrase duplicate checks, natural-language award formatter, punctuation,
Unicode character count, 450/700 default thresholds, and 145–165 WPM estimate
are owned by domain services. `composition_schema_version: 1` makes future
spoken-format rule changes an explicit source-hash invalidation decision.

SSML is a one-way projection of ready structured sections. Voice/locale pairs
come from `config/announcements.php`; rate (-20% through +10%) and pauses
(0–1500 ms) are typed integers. `XMLWriter` owns the namespace, attributes, and
elements, while dynamic source strings use its text API. The builder cannot
accept raw elements, arbitrary attributes, styles, roles, audio, or phonemes.
Breaks occur only between present sections: the name uses the configured
name pause and every other non-final section uses the section pause.

Preview resolves an active same-ceremony template, reads ordered awards,
composes, and returns plain/structured/SSML metadata. It does not authorize a
route and has no transaction or persistence step. No generation model is
created, no file or audio readiness changes, no HTTP call or Reverb event is
made, and stage serializers/playback are untouched. ENH-TTS-3 adds provider and
candidate custody below; UI, approval, invalidation, and batch work remain later
concerns.

## 22. ENH-TTS-3 provider and private-candidate architecture

The preparation-only provider path is isolated behind two interfaces:

```text
GenerateAnnouncementCandidate
    -> PreviewGraduationAnnouncement
    -> TextToSpeechProvider (AzureSpeechProvider)
    -> CandidateAudioStore (AtomicPrivateCandidateAudioStore)
    -> announcement_generations.needs_review
```

Configuration is loaded once through Laravel config and is disabled by default.
`AzureSpeechConfiguration` validates the key as a header-safe server value, the
region, HTTPS Azure Speech host/path, timeouts, retry bounds, response limit, and
user agent. The provider never follows redirects or accepts a request-supplied
endpoint, key, output format, voice, or locale. HTTP failures become bounded
safe exceptions without upstream bodies or secret-bearing context.

The generation action authorizes before reporting configuration state. A short
PostgreSQL transaction locks the student, composes the current source, reuses an
identical readable candidate when available, or creates the immutable generation
snapshot and moves it to `generating`. The network request is deliberately
outside the transaction. Success validates and atomically stores the MP3 before
a second locked transaction moves the row to `needs_review`. A finalization
failure deletes the new file; provider/storage failures retain only safe status,
code, message, and attempt metadata.

Candidate storage uses the existing local disk rooted at `storage/app/private`.
Random UUID directory/file names contain no graduate identity. A same-directory
mode-0600 temporary file is written completely, flushed, synchronized, and
atomically renamed. Invalid, partial, or failed files are removed. Database
paths remain hidden from default serialization and are not exposed by a route.

The workflow creates no active-audio link and does not update student audio
source, readiness, stage state, Reverb, or payloads. Configuration health is
offline unless an explicit caller requests the bounded voice-catalog check.
ENH-TTS-4 adds authorized candidate playback and preparation UI below. Approval,
promotion, invalidation, and stage guarding remain later-phase architecture.

## 23. ENH-TTS-4 awards and single-graduate workspace

The graduate detail response is extended through `StudentAnnouncementWorkspace`.
It serializes only reviewed announcement source, ordered awards, safe template
and voice choices, plain-text preview metadata, provider configuration health,
safe generation history, and authorized usage estimates. SSML, provider request
IDs, credentials, and private paths remain server-only.

```text
Graduate detail page
    -> award POST/PUT/DELETE/move commands -> PostgreSQL + safe audit
    -> preview GET -> same-ceremony template + allowlisted voice -> JSON
    -> generation POST -> GenerateAnnouncementCandidate
    -> candidate GET -> nested authorization -> validated private MP3 bytes
```

Award commands use the established HTTP-command pattern and transactional row
locks. Preview is a recovery/read projection and never calls Azure. Generation
retains the ENH-TTS-3 two-transaction provider boundary, adds exact in-progress
duplicate blocking, and can select a persisted same-ceremony template snapshot
and allowlisted voice. Candidate delivery uses no-store same-origin responses
with a generic filename and revalidates the MP3 before serving it.

The React workspace disables duplicate submissions, confirms force generation,
uses accessible move buttons, and keeps the existing manual media controls
visible. There are no approval/rejection/promotion commands, no active-audio or
readiness update, no source invalidation mutation, no Reverb event, and no stage
serializer change. Those remain ENH-TTS-5 concerns.

## 24. ENH-TTS-5 review, promotion, and invalidation

Candidate review uses nested student/generation HTTP commands and the existing
administrator-only review ability. Approval locks the student before the
generation, recalculates the deterministic fingerprint, validates the private
MP3, promotes a unique managed copy, and updates the generation and student's
active-audio metadata in one compensated workflow. The previous managed audio
is deleted only after commit; the promoted file is deleted on rollback.

Model observers invalidate affected `needs_review` and `approved` generations
after spoken student fields, ordered awards, or template/voice inputs change.
An active generated source is moved back to `needs_review`. The stage payload,
preload, and replay paths all pass through `AnnouncementAudio`, which requires a
readable file, approved generation, matching stored hashes, and a matching live
recomposition. Azure is never called by this runtime guard.

## 25. ENH-TTS-6 ceremony settings and voice comparison

The administrator-only settings workspace reads a server-built projection and
publishes changes through one versioning action. The action locks the ceremony,
deactivates the current template, inserts the next version, and invalidates
earlier candidates transactionally. Old template rows remain available for
history and source attribution. Request validation enforces the configured
voice/locale allowlist, plain-text fields, pacing bounds, and coherent character
limits independently of the React form.

Draft preview composes a fixed fictional input in memory and has no provider or
database side effects. Explicit comparison performs provider calls outside the
database transaction, validates and atomically stores MP3s in a dedicated
private temporary directory, then records expiring metadata after rechecking
the active template under the ceremony lock. Failure removes prepared files.
Playback revalidates nested ceremony ownership, expiry, existence, and MP3
content and returns a private no-store response.

Comparison rows are PostgreSQL-authoritative while active, but their payloads
and files are disposable: database dumps exclude their row data and the backup
media copy never includes their separate directory. The stage serializer and
runtime audio decision remain unchanged and local-only.
