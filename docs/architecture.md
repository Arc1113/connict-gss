# Graduation Stage System Architecture

Document status: **Phase 6 QR preparation and scanner hardening architecture implemented**

Last updated: 2026-07-30

## 1. Architectural intent

The system is one local Laravel application whose React clients issue HTTP
commands, persist authoritative ceremony state in PostgreSQL, and receive
post-commit notifications through Reverb/Echo. The design favors correctness and
recovery over trusting browser state or transient WebSocket delivery.

The approved architecture has not changed.

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
| Media | Local public paths | Tone fixtures and managed Phase 4 graduate uploads |
| QR rendering | BaconQrCode 3.1.1 | Local deterministic SVG from the current PostgreSQL token |
| Packaging | Docker Compose | Reproducible app, Reverb, and PostgreSQL processes |

No Redis, queue worker, polling loop, separate SPA/API application, SQLite,
cloud-storage service, cloud speech service, or runtime CDN is used.

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
- `CompleteCurrentPresentationController` delegates the minimal clear action.
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

### 6.2 Domain actions

`ScanStudentForPresentation` owns the scan transaction, safe result mapping, scan
logging, and post-transaction presentation dispatch.

`CompleteCurrentPresentation` owns the completion transaction and post-transaction
clear dispatch.

Both catch transport exceptions only after commit, log identifiers and exception
class without credentials/payloads, and return a delivery flag to the authorized
operator command response.

`CeremonyAdministration` owns ceremony lifecycle writes, activation locking,
transition checks, presenting-state protection, audit insertion, and safe
deletion. `StudentAdministration` owns graduate CRUD, media replacement/removal
coordination, safe audit diffs, and history-aware deletion.

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
- `ScanAttemptResult` centralizes the public scan response.
- `StudentMediaStorage` is the only writer/deleter for managed Phase 4 media.
  It recognizes only `storage/graduates/...` paths for cleanup.
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

The event objects contain only ceremony ID and a purpose-built safe array. They do
not serialize Eloquent models.

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
         ├─ check active/completed history
         ├─ check current stage is idle
         ├─ INSERT stage_queue_items(status = presenting)
         ├─ UPDATE stage_states(current, mode, version + 1)
         ├─ INSERT stage_events(student_presented)
         └─ INSERT scan_logs(hash, result, reason, actor)
              │
              └─ COMMIT
                   │
                   └─ dispatch StudentPresented synchronously
```

Expected rejections return from inside the transaction after inserting a safe
scan log. Because they do not carry a broadcast payload, no success event is
dispatched.

The action dispatches only after `DB::transaction` returns. A forced stage-event
failure test proves queue, state, log, and event writes roll back together.

## 8. Concurrency design

All scan and complete commands for a ceremony serialize on its stage-state row.
The active ceremony row is also locked to keep ceremony resolution stable during
the command.

Layered invariants:

1. Application checks return precise operator outcomes.
2. `FOR UPDATE` prevents two commands from both observing an idle stage.
3. PostgreSQL partial unique index
   `stage_queue_items_one_presenting_unique` prevents two presenting rows.
4. PostgreSQL partial unique index
   `stage_queue_items_one_active_student_unique` prevents duplicate active rows
   for one student.
5. Stage-state `version` creates an ordered client reconciliation boundary.

Separate-process integration tests start two independent PHP/PostgreSQL
connections behind a shared barrier. They verify simultaneous valid scans and
completion racing a scan. Acceptable race order differs, but final persisted
state is always consistent and has at most one presenting row.

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

## 10. Real-time channel and authorization

Laravel registers:

```text
stage.{ceremonyId}
```

Echo subscribes as:

```text
private-stage.{ceremonyId}
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

### 12.4 Phase 4 administration

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

### 12.5 Phase 5 import workspace

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

### 12.6 Phase 6 QR preparation

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

The stage keeps at most one browser `Audio` object:

- a new presentation pauses, rewinds, and releases the prior object;
- a clear stops the current object;
- playback begins only after the one-time browser interaction;
- a null audio URL produces visual-only presentation; and
- a rejected `play()` promise is not exposed on the audience screen.

Four short PCM WAV tones are versioned under
`public/media/phase3/audio`. They validate delivery/playback plumbing only. One
sample intentionally has no path.

Phase 4 stores validated JPEG/PNG/WebP and MP3/WAV/OGG uploads on Laravel's local
`public` disk under generated `graduates/photos` and `graduates/audio` names.
Database paths remain relative, so `StageStatePayload` continues to generate
same-origin URLs. Replacement is compensating rather than pretending file
storage is transactional:

```text
validate → store new file → DB transaction + audit
    ├─ rollback: delete new file, retain old path/file
    └─ commit: delete old managed file
```

The documentation gives provisional clip-duration and loudness preparation
targets, but the application does not normalize or automatically approve audio.
Pronunciation recording, server-side normalization, preloading, replay/mute,
final institutional loudness standards, and operator warnings remain deferred.

## 14. Failure and recovery

| Failure | Authoritative state | Recovery |
| --- | --- | --- |
| Browser refresh | PostgreSQL stage/queue | HTTP state then private subscription |
| Missed/duplicate event | PostgreSQL version | Ignore stale or fetch on gap |
| Reverb unavailable during command | Transaction remains committed | Delivery flag/log; reconnect and HTTP fetch |
| Reverb restart | No business state lost | Echo reconnect then HTTP fetch |
| Laravel restart | PostgreSQL/media remain | Restart app; session/login; HTTP fetch |
| PostgreSQL unavailable | No command may claim success | Restore DB, validate, retry |
| Two scans | Locked row + unique indexes | One accepted; other conflict |
| Completion/scan race | Locked row defines order | Consistent idle or next-presenting state |
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

## 17. Phase boundary and future architecture

Phase 3 deliberately has no durable waiting-queue workflow. A scan is either the
current presentation or a conflict. Phase 7 must define and implement the full
queue transition graph and both presentation modes without bypassing the
transaction, version, event, or recovery principles established here.

Still deferred after Phase 6:

- CSV update/synchronization/export;
- device authentication/registration;
- advanced stage controls and queue commands;
- recording, media normalization, and production approval workflow;
- operational logs/reports and exports;
- backup/restore and standby;
- reverse proxy/TLS, production process supervision, firewall policy, kiosk
  automation, and full physical rehearsal.

## 18. Risks and unresolved decisions

| Risk | Current response | Remaining decision |
| --- | --- | --- |
| LAN host/origin mismatch | Browser host fallback and origin allowlist | Fixed IP/hostname/firewall plan |
| Broadcast outage | Commit first, catch delivery failure, HTTP recovery | Operator monitoring and process supervisor |
| Browser autoplay | One-time stage interaction | Final kiosk/audio startup procedure |
| Long/missing media | Responsive text and visual-only fallback | Institutional media standards |
| Physical hardware mismatch | Manual checklist | Approved scanner/display/sound hardware |
| Session on audience device | Stage role session and private channel | Final device credential/kiosk model |
| HTTP on development LAN | Controlled local-only setup | Local TLS/WSS decision |
| Ceremony ambiguity | Reject zero/multiple | Final multi-ceremony policy |
| Temporary immediate-present rule | Explicit Phase 3 boundary | Phase 7 queue/mode policy |
| No process supervisor | Compose health/restart commands | Production supervision and startup |
| Public local media URL | Generated names, controlled LAN, no external origin | Whether production delivery requires a policy-authorized media controller |
| Upload filesystem/DB split | Store-first compensation and managed-prefix cleanup | Backup/restore and orphan reconciliation procedure |
| Media standards | Actual-type and size validation | Final dimensions, duration, loudness, approval, and retention |
| Temporary CSV sensitivity | Encrypted owner-bound row, hard limits, 30-minute UTC expiry, cleared content | Final retention/privacy notice |
| Import concurrency throughput | Global advisory lock and full current-state recheck | Whether later ceremony-scoped parallelism is required |
| Create-only correction | Conflicts block and existing rows never change | Approved future update/sync identity and merge rules |
| Printer/scanner variance | Fixed SVG/physical CSS contract and reproducible checklist | Approved devices, grayscale/scale/distance matrix |
| Lost/replaced paper cards | Immediate server-side invalidation and redacted replacement audit | Institutional custody/reprint/destruction procedure |
| Staff card identity | Student number appears only on protected staff print cards | Final privacy approval for printed matching fields |

The implementation may not advance to Phase 7 until Phase 6 is reviewed and
approved.
