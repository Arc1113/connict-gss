# Graduation Stage System Requirements

Document status: **Phase 0 baseline approved; later-phase decisions remain open**

Last updated: 2026-07-28

## 1. Purpose and objective

The Graduation Stage System is an event-critical, local web application used to
move an eligible graduate from QR scan through stage presentation. A successful
scan must identify the correct graduate, persist the operation, update the
audience-facing stage display in real time, and play the correct verified local
announcement.

The system must favor correctness, data integrity, recoverability, and a simple
operator experience over feature volume. It must remain operational without
internet access.

## 2. Scope and operating constraints

### 2.1 Required operating model

- The runtime is restricted to a dedicated local network.
- The system uses a server laptop, a scanner laptop, and a stage laptop.
- A USB 2D QR scanner is the primary scanning device and is expected to behave as
  keyboard input terminated by Enter.
- The stage laptop is connected to a projector or LED wall and the venue audio
  system.
- All application code, fonts, images, audio, and compiled assets required during
  a ceremony must be stored locally.
- PostgreSQL is the authoritative store for ceremony, queue, stage, scan, and
  audit state.
- Browser state and WebSocket events are transient views of persisted state.
- HTTP performs commands and state retrieval; Reverb and Echo notify browsers of
  committed changes.
- The Vite development server must not be used during a ceremony.

### 2.2 Out-of-scope architecture

The initial release must not depend on cloud hosting, cloud databases, online
text-to-speech, remote QR services, external CDNs, public internet access, or
direct browser-to-database connections. Laravel and React must not be split into
separate applications unless a later architecture change is explicitly approved.

## 3. Users and roles

The baseline role set comes from the playbook. The detailed permission matrix
remains an approval item.

| Role | Intended responsibility |
| --- | --- |
| `administrator` | Manage users, permissions, configuration, ceremonies, and authorized destructive actions |
| `data_encoder` | Prepare and correct ceremony and graduate data, photos, and announcement audio |
| `scanner_operator` | Operate the scanner page, view scan feedback, and use approved lookup fallbacks |
| `stage_operator` | Control queue presentation, replay/stop audio, skip or complete graduates, and recover from presentation mistakes |
| `technical_operator` | Monitor local services/devices, support startup and recovery, manage approved backup/restore operations |

Every state-changing request must be authenticated and authorized for the
operator's role. Whether the audience-only stage display uses a user session, a
restricted device identity, or both is unresolved. The local network must not be
treated as sufficient authorization by itself.

## 4. Core domain and lifecycle

### 4.1 Ceremony

A ceremony represents one graduation event or session. Supported lifecycle states
are expected to be:

`draft`, `prepared`, `active`, `paused`, `completed`, and `archived`.

Only an appropriately authorized operator may change the ceremony lifecycle.
Lifecycle changes must be persisted and important changes must be recorded as
stage or audit events. Scan and presentation behavior must be rejected or safely
limited when the ceremony is not active.

### 4.2 Graduate

A graduate belongs to a ceremony and has identity, academic, presentation, media,
eligibility, and ordering data. The following values must remain distinct:

- `display_name`: text intended for the audience display
- `announcement_name`: text represented by the verified announcement audio
- `pronunciation_guide`: internal preparation/operator guidance

### 4.3 QR token

Each graduate must have a unique, random, non-sequential token. Personal data,
student numbers, names, or ceremony details must not be encoded directly in the QR
payload.

### 4.4 Stage queue item

A queue item records one graduate's movement through the ceremony workflow.
Expected states are:

`waiting`, `ready`, `presenting`, `announced`, `completed`, `skipped`, and
`cancelled`.

The final transition rules, including safe return/undo paths, require approval
before implementation.

### 4.5 Stage state

Stage state is the authoritative, recoverable snapshot for the active ceremony. It
includes the current queue item and graduate, display mode, audio state, mute
state, presentation mode, and a monotonically changing version.

### 4.6 Permanent operational records

- A scan log records every scan attempt, including invalid and duplicate scans.
- A stage event records each important ceremony action.
- An audit log records important administrative changes with actor and before/after
  information where applicable.

## 5. Main ceremony workflow

### 5.1 Preparation

1. An administrator or data encoder creates the ceremony.
2. Graduate data is entered or imported and validated.
3. Eligibility, display name, announcement name, pronunciation, honors, order,
   photo, and audio are reviewed.
4. Random QR tokens and printable QR cards are generated locally.
5. QR cards are distributed through an institution-controlled process.
6. Photos and announcement files are checked locally against the correct graduate.
7. Operators verify the scanner, stage display, audio output, network, services,
   backup, and recovery procedure during rehearsal.
8. An authorized operator marks the ceremony prepared and later active.

Data administration, import, QR generation, and rehearsals belong to later phases;
this sequence is documented now to establish the full operational requirement.

### 5.2 Startup

1. The technical operator starts or verifies PostgreSQL, Laravel, Reverb, and the
   production asset deployment on the server laptop.
2. The scanner operator opens `/scanner` on the scanner laptop and confirms input
   focus and scanner feedback.
3. The stage operator opens `/stage-control`.
4. The stage laptop opens `/stage` in the approved full-screen configuration and
   performs any browser-required one-time audio activation.
5. Operators verify that all devices refer to the intended active ceremony and
   can recover current state over HTTP.

### 5.3 Scan-to-stage processing

1. The USB scanner enters the opaque QR token on `/scanner`.
2. The React scanner page sends the token through `POST /scans`.
3. Laravel authenticates and authorizes the request, validates the token and
   ceremony rules, and applies duplicate/concurrency protections.
4. A database transaction records the scan result and any permitted queue/stage
   changes in PostgreSQL.
5. The transaction commits.
6. Only after commit, Laravel broadcasts the resulting event.
7. Reverb transmits the event to authorized subscribers.
8. Echo receives the event in the relevant browser.
9. React refreshes or reconciles its view of the persisted stage state.
10. When presentation is authorized by the selected mode, the stage displays the
    correct graduate and plays the verified local audio without overlap.

An invalid or rejected scan must be logged but must not change queue or stage
state.

### 5.4 Presentation and completion

1. Exactly one graduate may be in `presenting` state for a ceremony.
2. The audience display shows only approved presentation information.
3. The correct local audio is played at most once automatically for that
   presentation unless an authorized replay is requested.
4. The stage operator may use approved controls such as replay, stop, skip,
   complete, clear, present next, or present selected according to mode and role.
5. Every material action is persisted and broadcast after commit.
6. Completion advances or readies the next queue item according to the approved
   presentation-mode rules.

### 5.5 Ceremony completion

The operator confirms that all intended graduates are completed, skipped, or
otherwise reconciled before completing the ceremony. Logs, stage events, audit
records, media, and PostgreSQL data are included in the documented backup
procedure. Completion and any reset are authorized, confirmed, and audited.

## 6. Presentation modes

Both modes are required. The ceremony's selected mode and all mode changes must be
persisted in PostgreSQL. A mode change during an active ceremony must be
authorized, validated, and audited.

### 6.1 Automatic mode

Automatic mode minimizes operator action. A valid scan may queue a graduate and
may cause the next eligible queue item to be presented when the stage is available.
It must never allow a later scan to replace a graduate who is already presenting,
must retain queue state, and must apply concurrency protection.

The exact automatic trigger—immediate presentation on scan, presentation only
when stage state is clear, or advancement after the preceding graduate is marked
complete—requires human confirmation.

### 6.2 Controlled mode

In controlled mode, valid scans add or update the persistent queue but do not
change the displayed graduate. An authorized stage operator explicitly chooses
`present next` or `present selected`. Skip, return, completion, clear, and safe
undo behavior must follow defined state transitions and be recorded.

### 6.3 Shared invariants

- Only an active ceremony accepts presentation actions.
- Only one queue item may be presenting in a ceremony.
- A new scan cannot silently replace the current graduate.
- Queue and stage state survive browser refreshes and reconnects.
- Mode logic does not bypass validation, authorization, persistence, or audit.
- The stage display reconciles from HTTP state after startup or lost events.

## 7. Data requirements

The final schema will be implemented in Phase 2 and may refine names while
preserving these concepts.

### 7.1 Required entities and data

| Entity | Required data categories |
| --- | --- |
| Users | Name, login identifier, password hash, role, active status, timestamps |
| Ceremonies | Name, academic year, venue, event date, status, presentation mode, activation/completion timestamps |
| Students / graduates | Ceremony, student number, QR token, name parts, display and announcement names, pronunciation, college, program, major, honors, sequence, local photo/audio paths, eligibility, timestamps |
| Stage queue items | Ceremony, graduate, state, queue position, scan/readiness/presentation/announcement/completion timestamps, scanning and presenting actors |
| Stage states | Ceremony, current queue item, display mode, audio state, mute state, version, timestamps |
| Scan logs | Ceremony when known, graduate when known, hash of raw token when appropriate, scanner/device, result, safe operator message, actor, scan time |
| Stage events | Ceremony, queue item when applicable, event type, minimal safe payload, actor, timestamp |
| Devices | Name, type, credential/key representation, network address when needed, last seen time, status |
| Audit logs | Actor, action, entity type/id, safe old/new values, network address, timestamp |

### 7.2 Required integrity rules

Application validation and PostgreSQL constraints must work together to enforce:

- globally unique QR tokens;
- unique student numbers within a ceremony;
- unique sequence numbers within a ceremony;
- valid ceremony, queue, eligibility, display, and audio states;
- required ceremony and graduate relationships;
- no duplicate active queue item for the same graduate and ceremony;
- no more than one presenting graduate per ceremony;
- transactionally consistent stage state, queue state, and operational logs; and
- indexes for QR lookup, active ceremony, queue order/status, and event/log lookup.

The concrete PostgreSQL mechanism for conditional uniqueness and concurrency will
be designed and tested in Phase 2/Phase 7.

### 7.3 QR and token handling

- QR tokens are generated locally with sufficient randomness.
- QR output contains the token only.
- Printable cards may show human-readable graduate name and sequence outside the
  QR symbol.
- Token regeneration requires confirmation, invalidates or archives the prior
  token according to an approved policy, and is audited.
- Invalid raw tokens should be hashed or omitted from logs where possible.
- The owner, timing, custody, reprint, lost-card, and revocation process for QR
  distribution remains unresolved.

### 7.4 Media requirements

- Graduate photos and announcement audio are stored locally.
- Media must be validated for allowed type and size.
- The association between each graduate and each media file must be verified
  before the ceremony.
- Missing or invalid media must not crash or contaminate the audience display.
- Audio must not overlap; replay stops or safely resets current playback.
- Only current and next media should be preloaded.
- Exact audio formats, encoding, loudness target, naming convention, announcement
  wording, and approval responsibility remain unresolved.

### 7.5 Retention and privacy

The system must collect only data needed for ceremony operations and auditing.
Retention duration, deletion/export procedures, privacy notices, consent or legal
basis, and who may access post-ceremony records require institutional approval.

## 8. Real-time communication requirements

1. Browsers open pages and retrieve authoritative state through HTTP.
2. State-changing actions are sent through authenticated, authorized HTTP requests.
3. Laravel validates the request and performs all related writes in a PostgreSQL
   transaction.
4. Broadcast events are dispatched only after a successful commit.
5. Reverb delivers small event notifications over restricted ceremony/control/
   display channels.
6. Echo receives notifications and prompts the React UI to update or reconcile.
7. After initial load, page refresh, reconnect, version gap, or suspected missed
   event, the browser retrieves authoritative state through HTTP.

WebSockets must not be the only validation, command, or persistence mechanism.
An event delivery failure must not roll back successfully committed business data.

## 9. Failure and recovery requirements

| Scenario | Required safe behavior | Recovery / operator visibility |
| --- | --- | --- |
| Invalid or unknown QR | Log a safe failure; do not change queue or stage | Scanner operator sees a clear retry/search message |
| Inactive/paused/wrong ceremony | Reject presentation effects | Operator sees the ceremony mismatch/status without token exposure |
| Ineligible graduate | Log rejection; preserve stage | Authorized operator follows a documented exception process |
| Duplicate or rapid scan | Prevent duplicate active queue/presentation records | Show current graduate status and approved next action |
| Two concurrent valid scans | Serialize or constrain updates; preserve both valid queue entries | Queue order remains deterministic and reviewable |
| Scan while a graduate is presenting | Never replace the current graduate | Queue the new graduate or reject according to approved mode rules |
| Out-of-sequence scan | Preserve data and apply approved queue policy | Stage operator can review and select/hold without silent reorder |
| Browser refresh | Do not lose operational state | Reload current state and queue from HTTP |
| Reverb/Echo disconnect or missed event | Persisted action remains correct | Show connection status to operators and reconcile via HTTP |
| Scanner page disconnect | Do not assume an unconfirmed scan succeeded | Restore focus/state and show whether the last request was accepted |
| PostgreSQL unavailable | Reject writes safely; never fabricate success | Operators receive a non-audience error and start recovery procedure |
| Laravel/Reverb restart | Persisted state remains intact | Restart service, reconnect clients, retrieve HTTP state |
| Local network loss | No inconsistent local-only UI action | Reconnect, verify state/version, and reconcile before continuing |
| Missing/corrupt audio | Continue safe visual presentation without crash | Warn stage operator only; allow approved fallback/replay |
| Audio overlap/replay during playback | Stop/reset current audio before another play | Controls expose deterministic playback state |
| Browser autoplay blocked | Do not repeatedly fail or overlap | One-time operator audio activation before ceremony |
| Missing/corrupt photo | Use an approved local fallback | Warn preparation/operator views, not the audience |
| Wrong graduate selected | Record actions and prevent hidden overwrite | Use an approved clear/return/undo procedure |
| Server or power failure | Preserve committed PostgreSQL state | Restart or activate tested standby; restore and verify state |
| Backup/restore failure | Do not overwrite the known-good source | Escalate, retain originals, and use tested restore instructions |
| Projector/audio hardware failure | Application stays operable | Technical operator follows venue fallback procedure |

Audience-facing `/stage` must never show stack traces, debug information, QR
tokens, student numbers, admin controls, scanner controls, connection diagnostics,
or browser notifications.

## 10. Security and privacy requirements

- Use Laravel's standard password hashing, sessions, CSRF protection, validation,
  policies, and middleware rather than custom authentication.
- Authorize every state-changing action and every private channel subscription.
- Use least privilege for users, devices, database access, files, and services.
- Keep database credentials and secrets server-side in an untracked `.env`.
- Never expose database credentials to React or allow browsers to connect directly
  to PostgreSQL.
- Keep QR payloads opaque and avoid logging raw invalid tokens.
- Do not log secrets, password values, device credentials, or unnecessary personal
  data.
- Restrict stage, control, and ceremony channels to their intended clients.
- Validate uploads by type, size, and safe storage path; serve only intended media.
- Protect destructive actions with authorization, explicit confirmation, and
  audit records.
- Disable debug output for ceremony deployment and keep technical errors off the
  audience screen.
- Do not rely on internet-hosted assets or services.
- Document local firewall rules, service binding, device provisioning, password
  handling, backup protection, and credential rotation in later phases.

Whether local HTTP is sufficient on the isolated venue network or local TLS is
required remains a security decision. The decision must account for login and
device credentials traversing the network.

## 11. Reliability and performance requirements

- Multi-record stage changes use database transactions.
- Concurrent scans/actions use database locking and constraints as appropriate.
- Events remain small and contain no unnecessary personal information.
- Admin lists are paginated; the browser does not load the full graduate roster.
- Current and next media only are preloaded.
- Queue and stage state are versioned or otherwise detectable for reconciliation.
- Services have documented startup, shutdown, restart, health, and recovery steps.
- The system is tested across separate devices on both approved wired and wireless
  paths.
- The physical scanner, projector, and venue sound system are tested before use.
- Backup and restore are tested, not merely documented.
- Expected graduate count, peak scan rate, maximum acceptable scan-to-display
  latency, recovery-time objective, and recovery-point objective remain unresolved.

## 12. MVP boundary

The ceremony-ready MVP includes:

- a reproducible Laravel/React/Inertia/Vite/PostgreSQL foundation;
- authentication and the approved operational roles;
- ceremony and graduate data management;
- safe CSV preparation/import;
- local opaque QR generation and printable distribution output;
- scanner input, validation, permanent scan logging, and duplicate protection;
- a PostgreSQL-backed queue and stage state;
- automatic and controlled presentation modes;
- a clean audience display and an authorized stage-control interface;
- Reverb/Echo notifications with HTTP recovery;
- verified local photo/audio handling, non-overlapping playback, and replay;
- audit/event logs and ceremony completion reporting;
- tested local backup/restore and optional standby procedures;
- documented three-laptop offline deployment; and
- automated, failure, physical-device, and full-rehearsal validation.

The first implementation milestone is the smaller Phase 3 vertical slice proving
scan → transaction → after-commit broadcast → stage display → local audio → HTTP
state recovery. It is not permission to implement the entire MVP in one phase.

## 13. Deferred features

The following are excluded from the initial release unless separately approved:

- public graduate portal;
- cloud synchronization or cloud backup;
- facial recognition;
- mobile application or phone-camera scanner;
- online registration;
- AI-generated speech during the event;
- multi-campus synchronization;
- livestream integration;
- advanced analytics or business-intelligence dashboards;
- guest attendance tracking; and
- public APIs.

## 14. Recorded assumptions

These assumptions allow planning but require confirmation where indicated:

1. One local server hosts the single Laravel application, PostgreSQL, Reverb,
   compiled frontend assets, and local media.
2. The primary ceremony setup uses exactly one scanner laptop and one audience
   stage laptop, with a separate control view available to an authorized operator.
3. One active ceremony is the normal operating case, although the data model
   retains ceremony scoping.
4. USB scanner input is keyboard-wedge text terminated by Enter.
5. Both automatic and controlled modes are mandatory.
6. A later scan never preempts a graduate already presenting.
7. QR codes contain only opaque random tokens and are prepared/distributed before
   the ceremony.
8. Announcement audio is prepared, approved, and stored locally before the event;
   no live online synthesis is used.
9. PostgreSQL data is authoritative after refresh, reconnect, or service restart.
10. An optional fourth laptop may be prepared as a cold standby.
11. Application timezone is expected to be `Asia/Manila`, matching the current
    project environment, but must be explicitly approved during Phase 1.
12. Interface language is initially English unless the institution requests
    localization.

## 15. Unresolved questions and required decisions

These questions are documented requirements gaps, not permission for an
implementation agent to choose silently.

1. What is the final permission matrix for the five roles, and who provisions or
   deactivates operator accounts?
2. How is the audience stage browser authenticated: user login, restricted device
   identity, or both?
3. Can more than one ceremony be active at once, or must the database enforce a
   single globally active ceremony?
4. What precisely triggers presentation in automatic mode, and when does it
   advance after completion?
5. May presentation mode change while a ceremony is active, and what happens to
   the current queue when it changes?
6. Should a duplicate scan be rejected, acknowledged as already queued, or allowed
   to requeue after specific terminal states?
7. What is the policy for out-of-sequence graduates and queue-position ties?
8. Which queue actions can be undone, and from which states may a graduate return?
9. Which graduate fields are mandatory, what are the allowed eligibility values,
   and what is the authoritative source/template for imported data?
10. How should multiple honors, programs, suffixes, and very long names be
    formatted on the stage display and in announcements?
11. What is the exact announcement script, audio format/encoding, loudness target,
    filename rule, production owner, and approval process?
12. What photo formats, dimensions, crop behavior, size limits, and fallback image
    are required?
13. What stage resolution, safe area, branding, information fields, and accessibility
    requirements must the display meet?
14. Who owns QR generation, physical distribution, replacement, revocation,
    reprinting, and custody after the ceremony?
15. Are scanner identifiers/device credentials required, and how are devices
    enrolled or replaced?
16. What network hardware, fixed address plan, wired/wireless priority, firewall
    rules, and local DNS or direct-IP convention will the venue use?
17. Is local TLS required for browser traffic and WebSockets on the dedicated
    network?
18. What are the expected number of graduates, peak scan rate, latency target, and
    maximum queue size?
19. What are the required data/media retention period, privacy notice, access,
    export, deletion, and secure-disposal rules?
20. What backup frequency, recovery-point objective, recovery-time objective,
    encryption, removable-media custody, and standby activation threshold apply?
21. What is the approved procedure when an ineligible graduate presents, an
    operator selects the wrong graduate, or a graduate has no usable QR?
22. Who has authority to pause, resume, complete, reset, or archive a ceremony?
23. Is `Asia/Manila` the required application timezone, and are localized names or
    interfaces required?
24. What venue projector/LED resolution, browser, operating system, audio device,
    and output level will be used for physical acceptance testing?

## 16. Phase 0 acceptance

Phase 0 was approved on 2026-07-28. The unresolved business and ceremony-policy
decisions are explicitly deferred and do not block the Phase 1 project foundation.
They remain binding decision gates for their applicable later phases.
