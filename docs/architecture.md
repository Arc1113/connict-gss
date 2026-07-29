# Graduation Stage System Architecture

Document status: **Phase 4 administration architecture implemented**

Last updated: 2026-07-28

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
- QR tokens are password inputs on create/replacement and are never included in
  list/detail/edit props; and
- native audio controls coordinate playback so only one preview plays.

The administration UI has no stage commands, queue controls, fake analytics, or
runtime WebSocket dependency.

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

## 17. Phase boundary and future architecture

Phase 3 deliberately has no durable waiting-queue workflow. A scan is either the
current presentation or a conflict. Phase 7 must define and implement the full
queue transition graph and both presentation modes without bypassing the
transaction, version, event, or recovery principles established here.

Still deferred after Phase 4:

- CSV import/export and preflight;
- QR generation and custody;
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

The implementation may not advance to Phase 5 until Phase 4 is reviewed and
approved.
