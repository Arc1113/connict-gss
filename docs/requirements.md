# Graduation Stage System Requirements

Document status: **Phase 11 software readiness verified; physical readiness pending**

Last updated: 2026-07-30

## 1. Objective

The Graduation Stage System must reliably move an eligible graduate from an
opaque QR scan to an audience presentation on a dedicated local network. The
system must preserve correct ceremony state under duplicate input, simultaneous
operators, browser refresh, WebSocket interruption, and missing media while
minimizing exposed personal information.

Phase 3 proves the critical vertical slice. Phase 4 adds reliable manual
ceremony and graduate preparation while preserving that operational workflow.
Phase 5 adds controlled create-only CSV preparation without changing the
scanner, presentation, event, or recovery contracts.
Phase 6 adds protected local QR rendering, print preparation, explicit token
replacement, and controlled scanner fallback without changing those contracts.
Phase 7 replaces the temporary immediate-presentation rule with durable queue
transitions, controlled and automatic initial presentation, and recoverable
stage-control commands.
Phase 8 adds graduate announcement-audio readiness, approval/rejection,
replacement invalidation, approved-only stage playback, safe replay, preloading,
and human-announcer fallback while preserving the Phase 3-7 scan/queue/stage
architecture.
Phase 9 adds traceable operational log views, safe CSV exports, ceremony
completion summaries, local PostgreSQL/media backup, backup manifests and
checksums, disposable restore verification, safe operational reset, and recovery
documentation.
Phase 10 adds local deployment packaging and device/network setup guidance.
Phase 11 verifies the implemented system as a release candidate and separates
software readiness from physical hardware and venue readiness.

## 2. Fixed architecture and device requirements

- Laravel owns authentication, authorization, validation, transactions, and HTTP
  endpoints.
- React runs through Inertia.js in the same Laravel application.
- Vite builds all frontend assets locally.
- PostgreSQL is the only authoritative application database.
- Laravel Reverb provides local WebSocket delivery.
- Laravel Echo and `pusher-js` are the browser-side WebSocket client.
- HTTP carries commands, forms, validation, and state retrieval.
- Runtime operation does not depend on the internet, cloud storage, online speech,
  browser speech synthesis, polling, or a CDN.
- The primary setup has a server laptop, scanner laptop, and stage laptop.
- The primary scan device is a USB 2D keyboard-wedge QR scanner which types a
  token and terminates it with Enter.
- The stage laptop drives the projector/LED output and local venue audio.

## 3. Roles and access

| Function | Allowed roles |
| --- | --- |
| `/scanner` and `POST /scans` | `scanner_operator`, `administrator` |
| `/stage`, `GET /stage/state` | `stage_operator`, `administrator` |
| `/stage-control`, queue reads, and all stage commands | `stage_operator`, `administrator` |
| `private-stage.{ceremonyId}` subscription | Active `stage_operator` or `administrator` session for the sole active ceremony |
| `private-stage-control.{ceremonyId}` subscription | Active `stage_operator` or `administrator` session for the sole active ceremony |
| Administration readiness and ceremony viewing | `data_encoder`, `administrator` |
| Ceremony create/edit/status/delete | `administrator` |
| Graduate list/detail/create/edit and local media | `data_encoder`, `administrator` |
| Audio upload, preview, replacement, and needs-review marking | `data_encoder`, `administrator` |
| Audio approval and rejection | `administrator` |
| CSV template/upload/mapping/preview/confirmation/summary | Owning `data_encoder` or `administrator` |
| QR readiness, individual/batch output, and printing | `data_encoder`, `administrator` |
| QR token replacement and replacement history | `administrator` |
| Scanner controlled lookup and manual verified selection | `scanner_operator`, `administrator` |
| Graduate deletion | `administrator`, subject to history protection |
| Administrative audit history | `administrator` |
| Operational scan/stage log viewing | `administrator`, `technical_operator`, limited `stage_operator` |
| Operational CSV exports | `administrator` |
| Ceremony completion summary | `administrator` |
| Backup status, backup creation, and restore verification | `administrator`, `technical_operator` |
| Operational reset | `administrator` |
| Event readiness review and final operational sign-off | Human lead operator, administrator, and technical operator |

All access uses Phase 2 session authentication, CSRF protection, active-account
enforcement, and Laravel gates/policies. Phase 4 does not introduce device keys or token
authentication.

React may use server-provided abilities to decide which links to show, but every
controller/form action applies a server policy and the server remains the
authorization boundary. Data encoders do not inherit scanner, stage, technical,
user-management, destructive, or audit-history privileges.

## 4. Active ceremony requirement

Exactly one PostgreSQL ceremony must have status `active`.

- Zero active ceremonies is an operational error.
- More than one active ceremony is an operational error.
- Scanning, stage state, completion, and private-channel authorization must not
  silently choose a ceremony when the state is zero or ambiguous.
- Phase 4 provides administrator-only activation and ceremony management.
- Activating a ceremony uses a PostgreSQL transaction-scoped advisory lock and
  row locks.
- A request that would create a second active ceremony fails with an actionable
  validation error. It does not silently deactivate the current ceremony.
- Event dates never activate ceremonies.
- Existing zero/multiple handling remains a defensive operational safeguard for
  corrupted or externally edited data.

Both zero and multiple cases produce a safe `ceremony_inactive` scan response.
The operator message distinguishes missing from ambiguous configuration without
exposing graduate data.

## 5. Persistent queue and presentation policy

1. A valid eligible scan creates one durable queue row before any presentation
   decision.
2. Controlled mode leaves the new row `waiting`.
3. Automatic mode may promote the new row to `presenting` in the same
   transaction only when the stage is idle, automatic presentation is not
   paused, and the row is the earliest waiting item.
4. A scan never replaces or preempts the current graduate.
5. Present-next selects the smallest non-null waiting position. Present-selected
   may choose any waiting row in the active ceremony without renumbering others.
6. Completion is always explicit and never automatically advances the queue.
7. Skip changes a waiting row to `skipped` and clears its position. Return
   changes that same row back to `waiting` at the new tail position.
8. Replay does not alter queue status. Holding does not complete or detach the
   current row.
9. Automatic pause is persisted in PostgreSQL. Resuming affects future scans
   only and does not silently consume existing backlog.
10. All transitions are HTTP commands with PostgreSQL locks, recorded stage
    events, monotonic state versions, post-commit broadcasts, and HTTP recovery.

## 6. Scanner requirements

`/scanner` must:

- keep the token input focused on load and after each attempt;
- accept keyboard-wedge input ending in Enter;
- allow manual token entry for controlled testing and fallback;
- provide a bounded active-ceremony graduate lookup when a printed QR cannot be
  decoded, without returning token or unnecessary identity fields;
- cap input at 128 characters;
- prevent another submission while the current request is pending;
- clear and refocus after the response;
- show large, distinct accepted/rejected feedback;
- show the last accepted display name for the current browser session;
- show a practical Reverb connection state while clarifying through behavior that
  scans are HTTP commands;
- avoid camera scanning and unrelated personal information; and
- continue returning safe feedback if real-time delivery is temporarily
  unavailable.

The UI lock is a usability guard, not the duplicate or concurrency authority.

## 7. Scan command contract

`POST /scans` accepts JSON:

```json
{
  "token": "OPAQUE-QR-TOKEN"
}
```

The server trims and uppercases the token before matching and hashing. It returns
a safe message and one status:

| Status | HTTP | Meaning |
| --- | ---: | --- |
| `queued` | 201 | Eligible graduate committed; response includes position/status and whether automatic presentation occurred |
| `invalid_token` | 422 | No graduate in the active ceremony matches |
| `ceremony_inactive` | 409 | Active ceremony state is zero or ambiguous |
| `student_ineligible` | 422 | Matching graduate is not eligible |
| `already_queued` | 409 | Graduate is already waiting |
| `already_presenting` | 409 | Graduate is currently presented |
| `already_completed` | 409 | Graduate has completed history |
| `skipped_requires_stage_action` | 409 | Graduate was skipped and must be returned by stage control |
| `unauthorized` | 403 | Authenticated user lacks scanner permission |

Malformed/missing/overlong input receives a structured `invalid_token` validation
response. No raw invalid token may be echoed back.

## 8. Transactional scan requirements

`ScanStudentForPresentation` must use one PostgreSQL transaction and:

1. Resolve and lock the exactly-one active ceremony state.
2. Lock or initialize its `stage_states` row.
3. Resolve the normalized token within that ceremony.
4. Verify eligibility.
5. Check active, completed, and skipped queue history.
6. Allocate `MAX(queue_position) + 1` while holding the ceremony/stage lock.
7. Insert one `stage_queue_items` row with status `waiting`.
8. Insert a `student_queued` event and accepted scan log; increment version.
9. In automatic mode only, promote the earliest new waiting row to
   `presenting` when the persisted stage is idle and automation is unpaused.
10. When promoted, update stage state and record a
    `student_presented_automatically` event.
11. Commit all changes.
12. Dispatch `QueueUpdated` and any safe audience presentation event only after
    the transaction returns.

Rejected attempts must log safely when practical but must not change queue, stage
state, or stage events. A transaction exception must roll back all related writes
and emit no success event.

The stage-state row lock serializes scans and completion. Existing PostgreSQL
partial unique indexes independently prevent two active rows for one
ceremony/student and two presenting rows for one ceremony.

## 9. Scan logging requirements

Each safely processable attempt stores:

- ceremony ID when known;
- graduate ID when known;
- `device_id = null` in Phase 3;
- SHA-256 of the normalized token;
- coarse Phase 2 result category;
- safe Phase 3 reason code;
- authenticated scanner operator ID; and
- scan time.

Logs must not store the raw QR token. Unauthorized requests are rejected before
the workflow and are not recorded as scanner attempts. An unexpected HTTP scan
failure must return safe `request_failed` JSON and log only the operator ID,
command type, and exception class; it must not pass the raw input to Laravel's
exception reporter.

## 10. Real-time requirements

Audience events are `StudentPresented`, `StagePresentationCleared`,
`AudioReplayRequested`, and `StageHoldingChanged`. The control event is
`QueueUpdated`.

All events:

- implement synchronous Laravel broadcasting so no queue worker is required;
- are dispatched after the database transaction completes;
- publish on the purpose-specific private audience or control channel;
- may fail to deliver without rolling back committed PostgreSQL state.

`QueueUpdated` publishes `.queue.updated` on
`private-stage-control.{ceremonyId}` and contains only version, action, and an
optional queue item ID. It tells the operator client to refetch; it is not an
authoritative queue snapshot. Audience event names are `.student.presented`,
`.stage.cleared`, `.audio.replay-requested`, and `.stage.holding-changed`.

`StudentPresented` contains only:

- `event_id`
- `version`
- `student_id`
- `display_name`
- `program`
- `major`
- `honors`
- `photo_url`
- `audio_url`
- `presented_at`

It must not contain QR tokens, student numbers, email, credentials, raw scan
input, operator details, internal model dumps, or debug data.

The clear event contains only event ID, version, null presentation, and clear
time.

Reverb origins must be an explicit environment-controlled hostname list. `*` is
not allowed in the Phase 3 configuration. Pusher statistics are disabled so the
client does not add an external runtime call.

## 11. Stage state and recovery

`GET /stage/state` returns only the active ceremony ID/name and:

```json
{
  "version": 7,
  "mode": "graduate",
  "presentation": {}
}
```

or an idle state with `presentation: null`.

The stage client must:

1. retrieve HTTP state before subscribing;
2. subscribe to the authorized private channel;
3. reconcile again when the subscription succeeds;
4. ignore events whose version is not newer;
5. retrieve HTTP state when a version gap is detected;
6. retrieve HTTP state after a WebSocket reconnection; and
7. restore the persisted presentation after refresh.

Stale or duplicate events must not overwrite newer state. Reverb is transport,
not state storage.

## 12. Completion requirements

`POST /stage/complete-current` must:

- lock the active ceremony and stage-state row;
- lock the current presenting queue item;
- return `stage_idle` if nothing is current;
- reject inconsistent persisted state without silently rewriting it;
- mark the item `completed` and record completion time/operator;
- clear the current stage reference and set idle/stopped state;
- increment the version;
- record a `student_completed` event;
- commit; and
- dispatch `StagePresentationCleared` after commit.

The minimal completion button belongs to an authenticated operator context. It
must not appear on the audience stage display.

## 13. Audience display requirements

`/stage` must:

- show a neutral ceremony welcome while idle;
- display the graduate name prominently;
- display program, major, honors, and photo only when provided;
- handle unusually long names without leaving the viewport;
- show only a small safe `Live`/`Syncing` state;
- never show scanner input, QR values, student numbers, account data, logs,
  administrator controls, stack traces, service endpoints, or diagnostic text;
  and
- contain no queue/stage operational controls.

The one-time **Enable ceremony audio** interaction is allowed because browsers
block unprompted audio. It is not a stage-state command.

## 14. Local audio requirements

- Audio must come from local application paths.
- Browser speech synthesis and cloud text-to-speech are prohibited.
- A new presentation must stop and rewind any prior playback before starting.
- Clear must stop playback.
- Missing audio must remain a valid visual presentation.
- Playback failure must not expose technical errors on `/stage`.
- Final voice quality, pronunciation approval, format normalization, replay/mute
  controls, and bulk media preparation remain Phase 8 work.

Phase 3 includes four short local WAV test tones. The fifth seeded graduate
intentionally has no audio to test the missing-audio path.

## 15. Concurrency and failure requirements

| Scenario | Required result |
| --- | --- |
| Rapid repeated Enter/submission | UI blocks pending repeat; server rules remain authoritative |
| Two valid simultaneous controlled scans | Both persist once with unique ordered positions |
| Two automatic scans on idle stage | At most one presents; the other waits without preemption |
| Same graduate retried | One active item; later attempt receives the precise current-state conflict |
| Two present-next commands | At most one graduate presents |
| Present-next races present-selected | At most one graduate presents; other waiting history remains legal |
| Skip races selected presentation | One legal transition wins; no split state |
| Two returns of one skipped row | One return appends exactly once |
| Completion races replay | Completion remains authoritative and replay never resurrects state |
| Pause races automatic scan | Persisted lock order produces either a safe waiting scan or one legal initial presentation |
| Transaction throws | No partial queue, state, log, event, or success broadcast |
| Broadcast fails after commit | Command remains committed; client later retrieves HTTP state |
| Stage refresh/crash | Restore current state from PostgreSQL via HTTP |
| Stale/duplicate event | Ignore by version |
| Version gap | Reconcile through HTTP |
| Reverb stop/restart | PostgreSQL remains correct; reconnect and reconcile |
| Missing audio | Continue visual presentation |
| Zero/multiple active ceremonies | Reject operation; do not guess |

## 16. Security and privacy

- Laravel session, active-account, gate, and CSRF controls apply to HTTP commands.
- Private channels require Laravel authorization.
- Only the stage role and administrator may receive presentation broadcasts.
- PostgreSQL is not exposed to client laptops.
- `.env`, database passwords, Reverb secrets, and seed passwords remain untracked.
- QR tokens are opaque and never included in stage state, events, scan responses,
  logs, or Inertia props.
- Stage payloads use relative local media URLs so they work from the page's LAN
  host.
- Local-network deployment reduces exposure but is not a substitute for
  authorization, firewalling, and controlled physical access.
- The final local TLS, password policy, retention, backup encryption, and device
  credential policy remain unresolved.

## 17. Phase 3 acceptance criteria

Phase 3 is acceptable when all are true:

1. Only approved packages were added.
2. Laravel, PostgreSQL, and Reverb start predictably.
3. No queue worker is required.
4. `/scanner` and `/stage` access rules pass for all roles and guests.
5. All structured scan outcomes are verified.
6. A valid scan creates one queue item, stage state, scan log, and stage event.
7. Failed scans create no stage mutation or presentation event.
8. A forced transaction failure leaves no partial state.
9. Broadcast dispatch occurs after the action transaction.
10. The event/channel payload excludes prohibited fields.
11. Private-channel authorization accepts only the intended active users.
12. Two-process PostgreSQL race tests prevent double presentation.
13. Stage refresh restores current state.
14. Completion persists and clears correctly.
15. Echo live delivery and reconnect recovery are verified in browsers.
16. Local audio plays after one-time activation and missing audio is safe.
17. Pint, ESLint, Vite build, package audits, and Compose validation pass.
18. Physical three-laptop/scanner/projector/audio verification is either completed
    or explicitly deferred with a reproducible checklist.

## 18. Phase 4 ceremony and graduate administration

### 18.1 Routes and list behavior

The implemented administration routes are:

```text
/admin
/admin/ceremonies
/admin/ceremonies/create
/admin/ceremonies/{ceremony}
/admin/ceremonies/{ceremony}/edit
/admin/students
/admin/students/create
/admin/students/{student}
/admin/students/{student}/edit
/admin/audit-logs
```

Ceremony lists support name search, exact status and academic-year filters, and
15-row pagination with query-string preservation. Graduate lists support name,
announcement-name, and student-number search; ceremony, college, program,
eligibility, media readiness, and read-only stage-status filters; sequence/name/
student-number sorting; and 20-row pagination with query-string preservation.
Normal list and detail props do not include QR token values.

### 18.2 Ceremony validation and lifecycle

Ceremony forms validate:

- required name, maximum 255 characters;
- optional consecutive academic-year format such as `2026-2027`;
- optional venue, maximum 255 characters;
- optional ISO date input;
- approved status enum; and
- `automatic` or `controlled` presentation mode.

New ceremonies may begin as draft, prepared, or active. Supported status
transitions are deliberately conservative:

| From | Allowed next states |
| --- | --- |
| Draft | Prepared, active, archived |
| Prepared | Draft, active, archived |
| Active | Paused, completed |
| Paused | Prepared, active, completed, archived |
| Completed | Archived |
| Archived | None |

No ceremony field may change while its stage state references a presenting queue
item. Activation and completion timestamps are server-owned. Status changes do
not clear stage state or operational history.

Deletion is permitted only for a draft/prepared/paused ceremony with no
graduates, queue items, scans, stage events, or stage state. Active, completed,
and archived ceremonies are protected.

### 18.3 Graduate validation and data preservation

Graduate forms validate:

- an existing ceremony;
- student number, required and unique within that ceremony;
- a server-assigned globally unique QR token, never accepted from the ordinary
  create/edit form;
- required first, last, display, and announcement names;
- optional middle name and suffix;
- required program and positive sequence number unique within the ceremony;
- optional college, major, honors, and pronunciation guide within documented
  limits; and
- the approved pending/eligible/ineligible enum.

Separate name fields are persisted exactly as reviewed. Changing first/middle/
last/suffix does not recompute or overwrite `display_name` or
`announcement_name`. Editing never returns or replaces the current QR token.
Only the explicit Phase 6 administrator replacement action may change it.

Graduate deletion is permitted only before any scan, queue, or presentation
history exists. Phase 4 does not introduce soft deletion.

### 18.4 Local media

Photos accept actual JPEG, PNG, or WebP content up to 5 MB. Audio accepts actual
MP3, WAV, or OGG content up to 15 MB. Files are stored locally under generated
collision-resistant paths in:

```text
storage/app/public/graduates/photos
storage/app/public/graduates/audio
```

The database stores a relative `storage/...` path. The local `public/storage`
link serves that media to administration and stage pages on the dedicated
network. Replacements store the new file first, commit the database/audit change,
then delete the previous managed file. A failed transaction removes the new file
and preserves the former reference. Deletion/removal never deletes legacy or
unmanaged paths.

The native audio preview pauses and rewinds any other preview before playback.
Missing photo/audio is an explicit textual readiness state. Phase 4 does not add
text-to-speech, recording, normalization, cloud storage, or external assets.

The provisional preparation recommendation for reviewed announcement audio is a
2–10 second clip with excess leading/trailing silence removed, approximately
−16 LUFS integrated loudness, and a true peak no higher than −1 dBFS. These
targets are not automatically measured or treated as pronunciation approval.
The institution must confirm the final standard and review clips on the venue
sound system.

### 18.5 Audit and privacy

Important ceremony/graduate create, update, lifecycle, media add/replace/remove,
and allowed deletion actions create an `audit_logs` row in the same transaction
as the record change. Audit history is administrator-only and read-only.

Stored audit values are purpose-built and allowlisted. They exclude raw QR token
values, original filenames, media paths/content, passwords, and unnecessary
identity values. QR/identity editing is represented by changed field names or
booleans, not secret or prior personal values.

### 18.6 Phase 4 acceptance

- The authorization matrix is enforced by server policies.
- Search, filters, sorting, pagination, validation recovery, and no-result states
  work with realistic long data.
- Concurrent activation requests leave exactly one active ceremony.
- Presenting state and operational history cannot be silently changed/deleted.
- Local media upload, replacement, preview, removal, cleanup, and stage payload
  compatibility work.
- Administrative audit values are useful and redact secrets/media paths.
- Phase 3 scanning, post-commit broadcasting, Echo delivery, stage recovery, and
  concurrency tests remain unchanged and pass.
- Administration routes are visually inspected at 1440×900, 1366×768, and
  1024×768.

## 19. Phase 5 CSV import and data preparation

### 19.1 Access, routes, and ownership

The implemented workflow uses these server-authorized routes:

```text
GET  /admin/students/import
GET  /admin/students/import/template
POST /admin/students/import/inspect
GET  /admin/students/import/{studentImport}/mapping
POST /admin/students/import/{studentImport}/preview
GET  /admin/students/import/{studentImport}/preview
POST /admin/students/import/{studentImport}/confirm
GET  /admin/students/import/{studentImport}/summary
```

Administrators and data encoders may begin an import. A temporary import belongs
to the user who created it; another administrator or data encoder may not view,
remap, confirm, or summarize it. Guests, inactive accounts, scanner operators,
stage operators, and technical operators are denied.

Draft, prepared, active, and paused ceremonies may receive prepared records.
Completed and archived ceremonies are excluded from selection and rejected
again by request validation and confirmation.

### 19.2 Input contract and limits

- Input must be UTF-8 CSV; one leading UTF-8 BOM is accepted.
- The supported delimiters are comma, semicolon, and tab. Detection must produce
  one deterministic winner; ambiguous or single-column input is rejected.
- Maximum file size is 2 MiB.
- Maximum data rows is 2,000, excluding the header and blank records.
- Maximum columns is 40.
- Maximum header or cell length is 2,000 Unicode characters.
- Every column has a non-empty header. Headers are unique ignoring
  capitalization.
- Quoted delimiters, escaped quotes, CRLF/LF records, Unicode names, and blank
  lines are handled through PHP's native CSV parser.
- Binary/NUL content, unsupported control data, non-UTF-8 bytes, an empty file,
  a header without rows, oversized input, and invalid file types receive safe
  field-linked errors.
- Only the basename of the client filename, stripped of control characters and
  bounded to the database limit, may be retained for operator context.

The downloadable local template contains approved field headers and fictional
realistic rows. It excludes QR tokens, model IDs, media paths, stage state, and
other internal values.

### 19.3 Mapping and normalization

Mapping is stored by source-column position. Exact, unambiguous aliases may
suggest one target; labels such as `name`, `number`, and `status` are not guessed.
One target may be selected from only one source column.

Required targets are:

- student number;
- first name;
- last name;
- program;
- sequence number; and
- eligibility.

Optional approved targets are middle name, suffix, stage display name,
announcement name, pronunciation guide, college, major, and honors. Every
unmapped source column is explicitly reported as ignored.

Normalization trims mapped values, converts empty optional values to null,
removes Unicode whitespace from student numbers, parses a positive whole
sequence number, and recognizes documented eligibility aliases. Eligibility
ultimately persists only as `pending`, `eligible`, or `ineligible`.

If display or announcement name is absent but first/last name are valid, the
server generates it from the mapped name components and produces a visible
warning. It never silently replaces an explicitly supplied reviewed name.

### 19.4 Server preview and row states

The server reparses encrypted temporary content and applies the persisted mapping
on every preview. Browser-posted normalized rows or counts are never
authoritative.

Preview validates the Phase 4 graduate field lengths and enum rules, positive
sequence numbers, unsupported control characters, malformed column counts,
duplicate student/sequence numbers within the CSV, and current student/sequence
conflicts in the destination ceremony. Student-number conflict comparison
ignores whitespace consistently with import normalization.

Each row is classified with text as:

- `ready` when it has no findings;
- `warning` when it is valid but includes generated-field or other review
  warnings; or
- `blocked` when it has any error.

The preview shows 50 rows per page while retaining whole-file counts. Long names,
programs, honors, and messages wrap inside a table-owned horizontal container.
The confirm region is present only when the whole preview has zero blocked rows.

### 19.5 Atomic confirmation and QR custody

Phase 5 supports `create_only` mode. It does not update, merge, upsert,
synchronize, or delete an existing graduate.

Confirmation requires an explicit acknowledgment checkbox. The server then:

1. begins one PostgreSQL transaction;
2. obtains a fixed transaction-scoped import advisory lock;
3. locks the temporary import and verifies owner/expiry/unused state;
4. locks and revalidates the destination ceremony;
5. locks current graduates for that ceremony;
6. reparses and revalidates every row against current PostgreSQL state;
7. creates every graduate with a server-generated globally unique opaque QR
   token and a safe per-record audit; and
8. records a safe completion summary and clears temporary source content.

Any validation conflict, uniqueness race, database exception, or unexpected
failure rolls back every graduate and completion audit. A safe failed-import
audit may be written after rollback. A consumed import cannot be confirmed a
second time.

Raw generated QR tokens are never returned in import Inertia props, template
content, audit values, or completion summaries.

### 19.6 Temporary data, expiry, audit, and recovery

Temporary state is a PostgreSQL `student_imports` row identified by a random UUID
route key. It stores owner, ceremony, safe filename, delimiter, headers, mapping,
row count, expiry, and optional safe summary. Source CSV content uses Laravel's
encrypted cast and is never written to public storage or a retained temporary
file.

Unconsumed imports expire 30 minutes after inspection using explicit UTC
timestamps. An expired page deletes its temporary row and instructs the operator
to upload again. Successful confirmation nulls the encrypted source content and
retains only a short-lived safe summary.

Import completion/failure audits include safe mode, ceremony and count metadata,
filename, timestamps, and reason category. They exclude CSV row values, graduate
names, student numbers, raw QR values, content, arbitrary paths, and stack
traces. Only administrators may view audit history.

### 19.7 Phase 5 acceptance

- Upload, mapping, preview, confirmation, and summary are separate HTTP/Inertia
  steps with server policy and CSRF enforcement.
- Required formats, limits, malformed input, realistic Unicode/long content,
  mapping errors, ignored columns, row states, pagination, expiry, and ownership
  are covered by PostgreSQL-backed tests.
- Current conflicts are checked at preview and again inside the confirmation
  transaction.
- Valid confirmation creates the entire set exactly once; injected mid-import
  failure creates no graduates and no completion audit.
- Generated QR values and encrypted CSV content remain absent from browser and
  audit output.
- The compiled routes are inspected in Chrome at 1440×900, 1366×768, and
  1024×768 with visible focus, labels, contained overflow, long content,
  warning/blocked/summary states, reduced motion, safe template, and role denial.
- Existing Phase 3 scanner/stage automated and live-browser recovery behavior
  remains unchanged and passes.

## 20. Phase 6 QR generation, printing, and scanner hardening

### 20.1 Token and QR payload contract

- New manual and imported graduate records receive a Laravel-generated token in
  the existing `GSS-` plus 48 random uppercase alphanumeric format.
- Existing tokens are preserved unless an administrator explicitly replaces one.
- The QR payload is exactly the stored token. It contains no name, student
  number, program, ceremony data, database ID, URL, media path, or other personal
  information.
- BaconQrCode 3.1.1 renders SVG locally and deterministically with
  error-correction level Q, a four-module quiet zone, 512-unit view box, black
  modules, and an opaque white background.
- QR images are generated on demand. No QR image or duplicate readiness state is
  stored, no raw token is used in a filename/URL, and no internet service is
  called.
- QR responses are authenticated, role-authorized, `no-store`, `noindex`, and
  protected from content-type sniffing.

### 20.2 Access and readiness

Administrators and data encoders may use the QR preparation index, individual
review/image/print routes, and stateless batch print action. Scanner, stage, and
technical operators are denied. Only administrators may replace a token and see
replacement history.

The preparation index filters one ceremony by college, program, eligibility,
original/regenerated token state, and sequence range. It paginates 25 rows and
reports text-labeled readiness. Printing requires:

1. a current token;
2. `eligible` status; and
3. no completed presentation history.

Presenting state does not invalidate an already prepared print, but token
replacement is blocked while presenting. A completed graduate cannot be printed
or receive a replacement under the safe Phase 6 policy.

### 20.3 Individual and batch print contract

Print output is standalone same-origin HTML with embedded SVG data URIs and no
remote assets:

| Output | Paper | Margin | Cards/page | QR size |
| --- | --- | --- | ---: | ---: |
| Individual replacement | A4 portrait | 12 mm | 1 | 65 mm |
| Batch preparation | A4 portrait | 12 mm | up to 4, two columns | 55 mm |

Cards show only the ceremony, approved display name, program, sequence number,
and staff matching student number. The raw token is never visible. Cards use
physical CSS units, `break-inside: avoid`, deterministic sequence/name/ID order,
high contrast, a white QR background, and wrapping for long names/programs.

The batch action accepts at most 100 deduplicated graduate IDs. It locks and
revalidates every selected row, requires one ceremony, rejects missing,
cross-ceremony, ineligible, tokenless, or completed records, and records only
safe ceremony/filter/count audit data. Operators print at 100% scale with
**Fit to page** and browser headers/footers disabled.

### 20.4 Token replacement

Administrator replacement requires an explicit acknowledgment. Inside one
PostgreSQL transaction it:

1. locks the current graduate row;
2. compares an opaque HMAC fingerprint of graduate ID, current token, and record
   timestamp so same-second competing requests cannot both succeed;
3. rejects presenting or completed history;
4. generates and saves one new unique token; and
5. inserts a redacted audit row before commit.

The new token immediately becomes the only token accepted by `POST /scans`.
Graduate identity, ceremony, media, scan, queue, and presentation history remain
unchanged. An audit/database failure rolls back to the previous valid token.
Audit values contain state labels and ceremony/actor/request metadata, never
either token or image data.

### 20.5 Scanner hardening and fallback

The primary path remains USB HID keyboard input ending in Enter and
`POST /scans`. The synchronous submission lock prevents repeated Enter or trigger
input from creating a second request while processing. Input is trimmed and
uppercased on the server, cleared after every response, and refocused after
success/failure and browser-window focus. Raw input never enters a URL, response
message, console log, or browser persistence.

When a printed QR does not decode, the scanner operator may expand controlled
lookup. `POST /scanner/lookup` searches only the sole active ceremony, requires
at least two characters, escapes wildcard input, returns at most 10 results, and
returns only ID, display name, program, sequence, and eligibility label. After
the operator verifies one result, `POST /scans/manual` resolves that ID inside
the same active-ceremony PostgreSQL transaction and applies the same eligibility,
duplicate, completed, busy-stage, persistence, post-commit broadcast, and HTTP
recovery rules. It is not a queue-control or override feature.

### 20.6 Physical configuration and acceptance limitation

The approved device profile is USB HID keyboard mode, the scanner laptop’s
keyboard layout, QR/2D symbology enabled, no prefix, and exactly one Enter or
carriage-return suffix. Device beep confirms local decoding; the scanner page
confirms server acceptance. The print/scanner matrix must cover individual and
batch output, normal and grayscale print, documented physical sizes, long
content, trigger/Enter repetition, busy/duplicate/completed outcomes, and
old/new replacement cards.

No USB 2D scanner was attached and configured printers were offline during Phase
6 implementation. Automated PostgreSQL and Chrome checks prove generated SVG,
print media/PDF structure, focus, duplicate-request locking, role boundaries,
old/new token behavior, and stage regression. Physical paper, scanner beep,
suffix timing, scan distance, printer scaling, and venue hardware remain a
required pre-ceremony manual check; no physical verification is claimed.

### 20.7 Phase 6 acceptance

- Individual and bounded batch QR output are authenticated and local-only.
- The encoded payload remains server-token-only and absent from surrounding
  HTML, audit JSON, logs, URLs, and ordinary administration props.
- Print layouts use documented physical dimensions and handle long content.
- Replacement is administrator-only, confirmed, transaction-safe, concurrent
  stale requests are rejected, failures roll back, and operational history is
  preserved.
- Scanner focus, Enter input, immediate feedback, input clearing, HTTP flow, and
  bounded fallback behavior are covered by tests and browser checks.
- Phase 3 Reverb/Echo/state/audio and Phase 4–5 administration/import regression
  behavior remains operational.
- Physical scanner/printer certification is explicitly pending with reproducible
  steps.

## 21. Phase 7 persistent queue and stage control

### 21.1 Queue states and positions

The implemented transition graph is:

```text
scan → waiting
waiting → presenting → completed
waiting → skipped → waiting (new tail position)
```

`ready`, `announced`, and `cancelled` remain schema-compatible states but Phase 7
does not add operator commands that produce them. A non-null queue position is
unique within its ceremony. Positions are positive, monotonic, and never
renumbered or reused during normal operation. Skipping clears the position;
returning assigns the current maximum plus one.

### 21.2 Operator commands

`/stage-control` and `GET /stage/queue` expose a safe, recoverable view of the
current graduate, waiting order, skipped rows, recent actions, display mode,
automatic pause, connection, and local-audio readiness. They never expose QR
tokens or student numbers.

Stage operators and administrators may present next/selected, complete, skip,
return, replay, activate/deactivate holding, and pause/resume automatic initial
presentation. All buttons prevent duplicate submission; high-consequence
actions request confirmation and failures explain that the operator must
refresh authoritative state before retrying.

### 21.3 Mode behavior

Controlled mode never presents from a scan. Automatic mode is intentionally
limited to initial presentation of the newly scanned, earliest waiting graduate
when the stage is idle. It never preempts, bypasses backlog, or auto-advances
after completion. Pause is durable and scan-safe; resume does not consume the
backlog without an explicit present command.

### 21.4 Recovery, audience safety, and acceptance

- PostgreSQL row locks and partial unique indexes are the concurrency authority.
- Every successful mutation increments the stage version and records a stage
  event before any broadcast.
- Compact control events cause HTTP refetch; audience events remain
  presentation-safe.
- Refresh, subscription, reconnect, and version gaps reconcile via HTTP.
- Holding and transport failure show no technical details to the audience.
- Missing audio disables replay but never blocks visual presentation.
- The control layout is keyboard-operable and verified at 1440×900, 1366×768,
  and 1024×768; audience output is verified at 1920×1080 and 1366×768.

## 22. Phase 8 announcement audio reliability

### 22.1 Readiness model

Graduate announcement audio readiness is stored on `students` using:

| Status | Meaning |
| --- | --- |
| `missing` | No usable local announcement audio exists |
| `uploaded` | A file exists and awaits review |
| `needs_review` | Pronunciation, content, volume, or quality requires review |
| `approved` | A readable local file was approved by an administrator |
| `rejected` | The current file must not be used and requires replacement |

The database also stores approval timestamp/user, safe review notes, upload time,
MIME type, file size, and optional duration. It does not store audio binary,
waveform data, external IDs, secrets, raw QR values, or full editing history.

Existing Phase 3 tone fixtures are development/test media only. They are seeded
as `needs_review` when present and must not be treated as production approved
announcements unless a test harness explicitly marks them for regression use.

### 22.2 Access and workflow

Administrators may upload/replace, preview, mark needs review, approve, reject,
and remove graduate audio. Data encoders may upload/replace, preview, mark needs
review, and update pronunciation guidance, but do not grant final approval by
default. Stage operators may view current operational availability and replay
approved current audio only. Scanner and technical roles receive no audio
administration access.

Upload/replacement validates local MP3, WAV, or OGG files up to 15 MB. New
storage filenames are generated by Laravel and never use user-controlled paths.
Replacement stores the new file first, commits database/audit changes, then
removes the old managed file. Failure deletes the abandoned new file and
preserves the former valid path and approval metadata. Replacement always clears
approval and returns readiness to `uploaded`.

Approval locks the graduate row, confirms audio exists and is readable, requires
an administrator, stores approval timestamp/user/notes, and records safe audit.
Rejected audio is preserved for authorized review, stores safe notes, records
audit, and cannot be used automatically on stage.

### 22.3 Stage policy

Production playback is:

```text
approved and readable local audio -> included in stage payload, preloaded, played, replayable
missing/uploaded/needs_review/rejected/unreadable -> no audience audio URL
```

The audience page remains visually correct and exposes no audio technical state.
Stage control shows operator guidance such as `Live announcer required`.
Graduate presentation may continue while a human announcer reads the approved
announcement name unless institutional policy says otherwise.

The stage browser keeps one active audio object. New presentations stop previous
audio; replay stops and restarts the current approved clip; holding/completion
stop playback through the existing mode changes. Duplicate or stale Reverb
events are gated by stage version and HTTP reconciliation.

Current approved audio is preloaded. The HTTP stage state also includes one safe
same-origin next-audio preload URL when a waiting approved graduate is known.
The browser does not preload the full ceremony library.

### 22.4 Manual quality policy

Duration and loudness are reviewed manually in Phase 8. Recommended preparation:
2-10 seconds, clear speech, minimal noise, no clipped beginning/ending, consistent
silence padding, near -16 LUFS integrated loudness, and true peak at or below
about -1 dBFS. No automated duration/loudness certification is claimed because
no approved local inspection package or system binary was added.

### 22.5 Phase 8 acceptance

- Audio readiness is stored authoritatively in PostgreSQL.
- Upload/replacement, needs-review, approval, rejection, and removal are
  server-authorized and audited.
- Unapproved, rejected, missing, and unreadable audio is not sent to the audience
  stage for automatic playback.
- Approved audio plays and replays through the existing stage workflow without
  changing queue status.
- Stage control provides safe fallback guidance and the audience screen stays
  free of technical warnings.
- Existing Phase 3-7 scanner, QR, import, queue, Reverb/Echo, and recovery
  behavior remains operational.

## 23. Phase 9 operational support

Operational log pages are private administration routes:

- `/admin/scan-logs`;
- `/admin/stage-events`;
- `/admin/audit-logs`.

Scan-log output excludes raw QR tokens and token hashes. Stage-event output uses
a safe serializer and does not show unrestricted JSON payloads. Audit output
keeps allowlisted summaries and redacts secret, token, and storage-path fields.

CSV exports are UTF-8, filter-respecting, and formula-safe. Cells beginning with
`=`, `+`, `-`, `@`, tab, CR, or LF are prefixed with a single quote before
output.

Ceremony summary metrics are PostgreSQL-derived. Definitions:

- completed graduate: distinct graduate with a completed queue item for the
  selected ceremony;
- never scanned: eligible graduate with no active or completed queue history in
  the selected ceremony;
- duplicate scan attempt: scan log with result `duplicate`;
- rejected or unapproved audio: current audio readiness is not `approved`.

Backups are local-only and include PostgreSQL dump, local media, manifest, and
checksums. The approved Docker workflow is:

```bash
bash scripts/gss-backup-docker.sh storage/app/gss-backups
bash scripts/gss-restore-verify-docker.sh storage/app/gss-backups/gss-backup-YYYYMMDD-HHMMSS
```

Browser restore over production data is prohibited. Destructive restore remains
a documented CLI escalation requiring administrator approval, verified checksum,
target confirmation, and a current safety backup.

Operational reset is administrator-only. It cancels waiting/ready queue rows,
clears current stage state, turns off holding and automatic pause, records an
audit and stage event, and broadcasts after commit. It preserves graduate
records, completed history, skipped/cancelled history, scan logs, stage events,
and audit logs. Ordinary reset is blocked while a graduate is presenting.

## 24. MVP and deferred boundaries

The eventual MVP includes the completed ceremony/student administration,
import/validation, QR creation/custody, durable queue operation, automatic and
controlled modes, stage controls, local media, logs/reporting, backup/restore,
local deployment preparation, and rehearsal.

Phase 10 local deployment requirements now include:

- production deployment environment with `APP_ENV=production` and
  `APP_DEBUG=false`;
- compiled Vite assets served from `public/build` without the Vite development
  server;
- Docker Compose deployment override for Laravel, PostgreSQL, and Reverb;
- app and Reverb LAN ports documented and health-checked;
- PostgreSQL not exposed to scanner or stage devices;
- startup, shutdown, health-check, and release-preparation scripts;
- migration policy using `php artisan migrate --force`, never
  `migrate:fresh`;
- scanner, stage, and stage-control device setup checklists;
- local firewall, power, audio, clock, offline package, standby, and network
  fallback documentation.

Deferred beyond Phase 10:

- CSV update/synchronization/export;
- camera scanning;
- device registration/authentication;
- drag-and-drop/arbitrary queue reorder, bulk editing, and destructive queue
  deletion;
- mid-ceremony switching of the ceremony's configured presentation mode;
- browser-based recording, automatic normalization, and final media-production
  tooling;
- public hosting, reverse proxy/TLS, firewall automation, kiosk automation,
  automatic failover, and cloud integrations; and
- full physical rehearsal and event sign-off.

## 25. Assumptions and unresolved questions

Current assumptions:

- Tokens are compared case-insensitively after trim/uppercase normalization.
- The sole active ceremony and its stage state represent the current operation.
- A completed graduate cannot be re-presented in Phase 7.
- Skipped graduates require an explicit stage-control return; rescanning cannot
  silently requeue them.
- Queue positions are operational sequence identifiers, not dense row numbers.
- Stage, scanner, and administration browsers use appropriate authenticated
  accounts.
- A fixed or reserved server address and TCP `8000`/`8080` are acceptable for
  local event deployment.
- HTTP and generated-name public local media are acceptable on a controlled
  local ceremony LAN pending any later approved TLS and media-delivery policy.
- CSV import is create-only, belongs to one authenticated operator, and expires
  after 30 minutes.
- Student and sequence number uniqueness remains ceremony-scoped; generated QR
  tokens remain globally unique and server-controlled.
- A4 portrait at 100% scale, 12 mm margins, 65 mm individual QR, 55 mm batch QR,
  and four batch cards per page are the provisional local print standard.
- Printed student number is approved only for staff-held matching cards, never
  the audience stage.
- MP3 and WAV are the safest stage-browser targets; OGG remains accepted but
  must be checked on the final browser.
- Duration/loudness checks are manual until a local dependency is explicitly
  approved.

Still requiring approval:

1. Can more than one ceremony ever be active, or is the Phase 4 single-active
   rule permanent?
2. May presentation mode change mid-ceremony in a future phase, and what
   safeguards would that require?
3. Are scanner/stage device credentials required in addition to user sessions?
4. What final browser, kiosk, branding, safe-area, and assistive-technology
   standards apply?
5. What graduate fields and honors formatting are institutionally approved?
6. What final photo dimensions, audio loudness/duration, pronunciation, approval,
   retention, and protected-delivery process is required?
7. What exact fixed IP, hostname, wired/wireless topology, router model, and
   firewall subnet will be used at the venue?
8. What are the expected graduate count, scan burst, latency target, retention,
    privacy notice, RPO, and RTO?
9. Where will the completion/control view run in the final three-laptop setup?
10. Which standby laptop, UPS, and power-loss procedure will be physically
    available at the venue?
11. What physical scanner, projector/LED, and audio hardware is standardized?
12. Should a later phase support CSV update/synchronization, and if so, what
    immutable identifier and field-level conflict rules are approved?
13. What retention or immediate-deletion period is approved for completed safe
    import summaries and import audit metadata?

Phase 11 must not begin until the Phase 10 implementation and these documented
boundaries are reviewed.
