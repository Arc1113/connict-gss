# Graduation Stage System Architecture

Document status: **Approved Phase 0/1 baseline with Phase 2 foundation awaiting
approval**

Last updated: 2026-07-28

## 1. Architecture goals

The architecture must provide a reliable scan-to-stage workflow on a dedicated
local network without internet access. It must preserve operational state across
browser refreshes, WebSocket interruption, and service restart; prevent duplicate
or conflicting presentation actions; protect personal data; and keep the
audience-facing display free of technical detail.

## 2. Approved technology decisions

| Concern | Approved decision | Consequence |
| --- | --- | --- |
| Application shape | One Laravel application | Backend and React frontend share one repository and deployment |
| Frontend | React rendered/routed through Inertia.js | No separately deployed SPA or independent public API is required |
| Asset pipeline | Vite | Ceremony deployment uses compiled local production assets |
| Database | PostgreSQL | Authoritative ceremony and stage data is relational and transactional |
| Real-time server | Laravel Reverb | Committed changes are announced over local WebSockets |
| Browser real-time client | Laravel Echo | React clients subscribe to restricted channels and reconcile state |
| Commands and queries | HTTP | Validation, authorization, writes, and authoritative reads do not depend on WebSockets |
| Scanner | USB 2D keyboard-wedge QR scanner | `/scanner` captures token input and submits on Enter |
| Runtime network | Dedicated local network | No cloud or internet runtime dependency |
| Runtime devices | Server, scanner, and stage laptops | Operational responsibilities are physically separated |

These decisions are fixed for the approved baseline. A change requires a written
rationale, impact analysis, and human approval before implementation.

## 3. Current repository baseline

Phase 1 initialized a Laravel 13 application directly in this repository. Phase 2
adds Laravel session authentication, inactive-account enforcement, five
backed-enum roles, server-side gates, the required PostgreSQL domain tables,
Eloquent relationships, factories, controlled development seed data, and focused
tests. The frontend contains only a public foundation page, login page, and
minimal protected dashboard.

The host environment has PHP 8.3.6, Composer 2.7.1, Node.js 24.14.0, and npm
11.9.0. Because host PostgreSQL tools and PHP PostgreSQL extensions are not
available, the verified development environment uses project-scoped Docker
services with PHP 8.3.32, `pdo_pgsql`, and PostgreSQL 16.14. This is a development
packaging decision, not a change to the approved runtime architecture.

The foundation contains no ceremony or graduate administration interface,
scanner endpoint, queue command, scanner/stage interface, Reverb/Echo setup, media
workflow, report, backup feature, heartbeat service, or deployment automation.

## 4. System context and device architecture

```text
                         Dedicated local network

  Scanner laptop                                  Stage laptop
  ┌────────────────────┐                          ┌────────────────────────┐
  │ USB 2D QR scanner  │                          │ Projector / LED output │
  │ Browser /scanner   │                          │ Venue audio output     │
  │ Echo client        │                          │ Browser /stage         │
  └─────────┬──────────┘                          │ Echo client            │
            │ HTTP + WebSocket                    └───────────┬────────────┘
            │                                                 │
            └──────────────────┬──────────────────────────────┘
                               │
                   Server laptop (fixed local address)
                   ┌────────────────────────────────────┐
                   │ Laravel + Inertia                  │
                   │ Compiled React/Vite assets         │
                   │ Reverb WebSocket server            │
                   │ PostgreSQL                         │
                   │ Local photos and audio             │
                   │ Logs and backups                   │
                   └────────────────────────────────────┘

              Optional cold-standby server (offline until needed)
```

The stage-control interface may run on an authorized operational laptop. Whether
it shares the scanner laptop, uses the server laptop, or requires an additional
operator device is an unresolved deployment decision. It does not alter the
required three-laptop minimum.

## 5. Local-network architecture

The server laptop exposes the Laravel HTTP service and Reverb WebSocket service on
an approved fixed local address. Scanner, stage, and authorized control browsers
connect only over the dedicated venue network. PostgreSQL is an internal
server-side dependency and must not be exposed for direct client use.

Required later deployment decisions include:

- fixed IP address and optional local hostname;
- HTTP and WebSocket ports and service bindings;
- wired versus wireless primary and backup paths;
- firewall allow rules limited to required local clients/services;
- local HTTP versus locally managed TLS/WSS;
- network hardware, power protection, and backup network;
- supported browser and full-screen/kiosk procedure; and
- service startup, health, restart, shutdown, and standby activation.

All runtime assets are served locally. Package registries and internet downloads
may be used only during an approved development/setup process, not by the
ceremony runtime.

## 6. Logical application architecture

### 6.1 Laravel server

Laravel owns:

- authentication, sessions, CSRF protection, and authorization;
- Inertia page responses and HTTP/JSON endpoints;
- validation of ceremony, graduate, queue, and stage rules;
- transaction boundaries and concurrency control;
- Eloquent persistence to PostgreSQL;
- after-commit event dispatch;
- private broadcast-channel authorization;
- local media upload/storage access;
- audit, scan, and stage-event recording; and
- later backup/restore orchestration where approved.

Phase 2 uses the built-in `web` session guard and Eloquent user provider. Login
requires email, password, and active account status; successful login regenerates
the session. Logout and forced inactive-account removal invalidate the session and
regenerate its CSRF token. A rate limiter protects login attempts. Public
registration and API-token authentication are absent.

Authorization is centralized in Laravel gates rather than React. A backed role
enum represents the five approved roles. Administrators receive the defined
baseline abilities globally, operational roles receive only their initial
functional ability, and inactive users receive none. The final permission matrix
will refine these gates in the phase that implements each protected operation.

Controllers should remain thin. Non-trivial stage workflows should be placed in
focused action or service classes only when their implementation phase requires
them. Empty placeholder classes are not part of this baseline.

### 6.2 React through Inertia

React owns interactive operator and display views while treating its state as a
cache of server state. Intended pages include administration, scanner,
stage-control, stage display, logs, and settings. Inertia keeps page rendering and
navigation within the Laravel application.

React must:

- send commands over HTTP;
- show safe, actionable feedback to operators;
- receive committed-change notifications through Echo;
- reconcile authoritative state after initial load, refresh, reconnect, or version
  mismatch;
- prevent accidental double submissions in the UI without relying on the UI as
  the only duplicate protection; and
- keep technical and sensitive information off `/stage`.

### 6.3 PostgreSQL

PostgreSQL is the source of truth for:

- users and roles;
- ceremonies and selected presentation mode;
- graduate identity/presentation data and media references;
- persistent stage queue;
- current stage state and version;
- every scan attempt;
- stage events and important operator actions;
- registered devices where used; and
- audit history.

Important invariants require both application checks and database constraints.
Transactions, row locking, indexes, and conditional unique constraints will be
selected in the appropriate data/workflow phases.

Phase 2 implements the nine required entities. Standard foreign keys express
ownership; nullable user/device/student references preserve operational history
when an optional actor or referenced record is removed. Ceremony deletion
cascades preparation-only students and stage state, while queue and stage-event
history restrict destructive parent deletion.

PostgreSQL check constraints enforce enum-backed values. Composite unique
constraints protect ceremony-scoped student numbers and sequence numbers, while a
global unique index protects QR tokens. Partial unique indexes enforce one active
queue row per ceremony/student and one presenting queue row per ceremony. One
stage-state row is allowed per ceremony. Composite foreign keys prevent
cross-ceremony student/queue references; PostgreSQL's column-specific `ON DELETE
SET NULL` preserves scan history and ceremony context when a student is deleted.

### 6.4 Reverb and Echo

Reverb transports small notifications after successful database commits. Echo
subscribes browser clients to authorized ceremony-specific channels. Reverb is not
the database and WebSocket delivery is not proof that an action persisted.

Planned event names include:

- `StudentQueued`
- `QueueUpdated`
- `StudentPresented`
- `StudentCompleted`
- `StageCleared`
- `AudioReplayRequested`
- `CeremonyPaused`
- `CeremonyResumed`

Planned restricted channel patterns include:

- `private-ceremony.{ceremonyId}`
- `private-stage-control.{ceremonyId}`
- `private-stage-display.{ceremonyId}`

Exact payloads and channel authorization are designed in their implementation
phase. Payloads must be minimal and must not expose QR tokens, credentials, or
unnecessary personal data.

## 7. Command, persistence, and real-time flow

The required ordering for a state change is:

```text
Browser command over HTTP
        ↓
Laravel authentication, authorization, and validation
        ↓
PostgreSQL transaction and concurrency checks
        ↓
Commit queue, stage, scan, event, and audit changes
        ↓
Dispatch broadcast event after commit
        ↓
Reverb transmits on an authorized local channel
        ↓
Echo notifies the React client
        ↓
React updates or retrieves authoritative HTTP state
```

Rules:

1. A broadcast must never precede the database commit it describes.
2. A transaction failure produces no success broadcast.
3. A broadcast failure does not invalidate successfully committed state.
4. Clients retrieve state over HTTP after startup, refresh, reconnect, a detected
   version gap, or ambiguity.
5. A state-changing WebSocket-only command path is not permitted.

Expected page and action routes are documented by the playbook and requirements;
they are planned interfaces, not implemented endpoints.

## 8. State and concurrency architecture

Stage state and queue state are ceremony-scoped and persisted. The model must
prevent:

- duplicate active queue items for the same graduate;
- more than one presenting graduate per ceremony;
- a rapid or concurrent scan from silently replacing the displayed graduate;
- invalid state transitions;
- stale browser state from overwriting newer committed state; and
- double logging of one accepted request where idempotent handling applies.

The intended defenses are:

- PostgreSQL transactions around related writes;
- database uniqueness/check constraints;
- conditional uniqueness for active/presenting records where appropriate;
- row-level locking or atomic updates for stage transitions;
- an explicit stage-state version used for client reconciliation; and
- server-side idempotency/duplicate rules in addition to disabled UI submission.

The Phase 2 partial index defines active queue rows as `waiting`, `ready`,
`presenting`, or `announced`; terminal rows are `completed`, `skipped`, or
`cancelled`. This is a persistence boundary, not a completed transition policy.
Phase 3 and Phase 7 must still define transactions, locking, commands, duplicate
responses, and legal transitions.

The exact duplicate window, idempotency key strategy, queue ordering algorithm,
and transition table remain open requirements.

## 9. Presentation-mode architecture

Both automatic and controlled modes use the same persisted queue, stage state,
validation, authorization, and event history.

- In automatic mode, the server may advance an eligible queued graduate when the
  approved automatic trigger occurs and the stage is available.
- In controlled mode, the server changes the current graduate only after an
  authorized `present next` or `present selected` command.

Neither mode may allow an incoming scan to preempt a presenting graduate. Mode
changes and resulting state transitions must be persisted and audited. Precise
automatic triggers and mid-ceremony mode-change behavior require approval before
workflow implementation.

## 10. Media architecture

Photos and announcement audio live on server-controlled local storage and are
referenced by database records. The system must not call a cloud media store or
online speech service.

Later phases must define:

- approved file types, codecs, dimensions, duration, and size limits;
- safe upload paths and access rules;
- file naming and integrity/verification process;
- fallback image and missing-audio behavior;
- current/next media preloading;
- browser audio activation, replay, stop, mute, and no-overlap logic;
- volume normalization guidance; and
- backup/restore coverage for both PostgreSQL and media.

Technical media warnings belong on operator interfaces, never the audience stage
display.

## 11. Trust boundaries and security architecture

The dedicated network reduces external exposure but is not a trust substitute.
The principal boundaries are:

1. **Operator/browser to Laravel:** authenticate sessions, enforce CSRF,
   authorization, validation, safe errors, and rate/duplicate protections.
2. **Echo client to Reverb channels:** authorize private subscriptions and expose
   only role/device-appropriate event data.
3. **Laravel to PostgreSQL:** use a least-privilege server-side credential kept in
   `.env`; do not expose the database to browsers.
4. **Laravel to local storage:** validate uploads, prevent path traversal, restrict
   served files, and back up media safely.
5. **Server/backup media to operators:** restrict backup access and do not log or
   copy secrets unnecessarily.
6. **Audience stage boundary:** expose only approved display fields and media; hide
   QR tokens, student numbers, controls, logs, errors, and diagnostics.

Passwords use Laravel's standard hashing. QR values are opaque random tokens.
Invalid raw QR values are hashed or omitted from permanent logs where practical.
Destructive operations require authorization, confirmation, and audit.

Inertia receives only the authenticated user's ID, name, email, enum role
value/label, and active flag. Passwords, remember tokens, device key hashes, QR
tokens, and database credentials are not shared. Development seed passwords come
only from the untracked `GSS_SEED_PASSWORD` setting.

The local TLS decision, session duration, device-credential model, password
policy, backup encryption, data retention, and credential rotation are unresolved
security decisions.

## 12. Failure detection and recovery architecture

| Failure | Persistent truth | Recovery path |
| --- | --- | --- |
| Browser refresh/crash | PostgreSQL queue and stage state | Reload through HTTP and resume Echo subscription |
| Missed/delayed WebSocket event | Committed PostgreSQL state/version | Detect reconnect/gap and fetch state through HTTP |
| Reverb restart | Business state remains committed | Restart Reverb, reconnect clients, fetch current state |
| Laravel restart | PostgreSQL and local media remain | Restart service, restore sessions as applicable, fetch current state |
| PostgreSQL interruption | No new write may claim success | Restore database service, validate integrity/state, then resume |
| Network interruption | Server remains authoritative | Reconnect device, reconcile version/state before new action |
| Concurrent scans/actions | Transaction/constraints decide valid result | Return explicit accepted/rejected state and refresh queue |
| Missing/corrupt media | Presentation record remains valid | Use approved fallback and operator warning; never crash `/stage` |
| Server/power loss | Last committed backup/live database | Restart primary or activate tested standby and verify state |
| Operator mistake | Stage/audit event history | Use approved clear/return/undo transition; never edit history silently |

Operators need visible connection and service health on operational interfaces,
but `/stage` must fail cleanly without technical output. Backup recovery must be
tested on a separate database before it is considered valid.

## 13. Deployment architecture

The ceremony deployment uses production-compiled Vite assets, disabled debug
mode, locally available fonts/media, predictable service processes, a fixed server
address, and documented device startup. PostgreSQL, Laravel, and Reverb must start
and restart predictably.

Production-readiness later requires:

- access from separate scanner and stage laptops;
- no runtime requests to external hosts;
- firewall and service health verification;
- sleep and automatic-update controls on event devices;
- wired and wireless path tests;
- physical USB scanner, projector/LED, and sound-system tests;
- local backup and separately verified restore;
- optional cold-standby instructions; and
- a full ceremony rehearsal including failure scenarios.

## 14. Repository structure direction

The implementation will follow Laravel and the selected starter kit's conventions.
The playbook's `app/Actions`, events, requests, policies, services, and
`resources/js` page/component structure are directional, not placeholders to
create in advance.

Phase 2 adds the domain and authentication foundation without creating future
feature modules. Scanner, Reverb/Echo, and stage implementation begin with the
Phase 3 vertical slice. Later administration, import, QR, queue, media, audit
interfaces, deployment, and rehearsal features remain gated by their phases.

## 15. Architecture risks

| Risk | Architectural response |
| --- | --- |
| PostgreSQL tools/driver absent from current host | Verified project Docker services provide PostgreSQL and `pdo_pgsql`; native setup still requires the documented packages |
| Node.js 24 compatibility with selected Laravel/Vite dependencies | ESLint, the Vite development server, and the production build passed on the verified host |
| Duplicate/concurrent stage changes | Layer transactions, locking, constraints, and workflow tests |
| WebSocket interruption or event loss | Keep PostgreSQL authoritative and recover state through HTTP |
| Browser audio autoplay/overlap | Require pre-event activation and deterministic single-playback control |
| Keyboard-wedge focus, rapid scan, damaged QR | Harden scanner UI and test the physical device; provide authorized lookup fallback |
| Local network/firewall/service misconfiguration | Fix addressing, document startup/health, rehearse wired/wireless recovery |
| Server or power failure | Test backups, restore, UPS/power procedures, and optional standby |
| Media mismatch or quality failure | Verify graduate-to-media mapping, preload narrowly, and define safe fallback |
| Personal data exposed to stage/events/logs | Minimize payloads, restrict channels, hash tokens, and separate operator/audience views |
| Operator error under ceremony pressure | Persist queue/events, authorize controls, use confirmation and safe recovery transitions |
| Unresolved scale and recovery targets | Obtain counts, latency, RPO, and RTO before performance/deployment acceptance |
| Provisional eligibility/display/audio vocabularies | Keep enum/check values explicit and revise them through a reviewed migration before dependent workflows |
| Seed credentials copied into source control | Require an untracked environment value and reject missing/short seed passwords |

## 16. Confirmed decisions and unresolved decisions

Confirmed by the approved playbook:

- Laravel + React + Inertia in one application
- Vite production assets
- PostgreSQL source of truth
- Reverb + Echo for local real-time notifications
- HTTP for commands and recovery
- local-network-only runtime
- USB 2D QR scanner
- server/scanner/stage laptop topology
- both automatic and controlled modes
- verified local announcement audio
- after-commit broadcasting

Still requiring human confirmation:

- detailed role/permission and stage-device authentication models;
- automatic-mode trigger and mode-switch behavior;
- duplicate, out-of-order, undo, and exception workflows;
- required graduate/import fields and eligibility vocabulary;
- stage visual, photo, announcement, and audio specifications;
- QR custody/distribution/regeneration procedure;
- concurrent ceremony policy;
- device enrollment and stage-control device placement;
- network addressing, browser, ports, local TLS, and firewall plan;
- scale, latency, retention, privacy, backup, RPO, and RTO requirements; and
- final physical venue hardware specifications.

The complete question list is maintained in
[`docs/requirements.md`](requirements.md#15-unresolved-questions-and-required-decisions).

## 17. Architecture acceptance

The Phase 0 architecture baseline was approved on 2026-07-28 and Phase 1 was
subsequently approved. Phase 2 records the authentication, authorization, and
PostgreSQL domain foundation without changing the approved application,
real-time, or device architecture. Every later phase remains subject to its own
acceptance and human approval.
