# Graduation Stage System Requirements

Document status: **Phase 3 implementation baseline awaiting human approval**

Last updated: 2026-07-28

## 1. Objective

The Graduation Stage System must reliably move an eligible graduate from an
opaque QR scan to an audience presentation on a dedicated local network. The
system must preserve correct ceremony state under duplicate input, simultaneous
operators, browser refresh, WebSocket interruption, and missing media while
minimizing exposed personal information.

Phase 3 proves the critical vertical slice only. It does not implement the full
ceremony product.

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

## 3. Phase 3 roles and access

| Function | Allowed roles |
| --- | --- |
| `/scanner` and `POST /scans` | `scanner_operator`, `administrator` |
| `/stage`, `GET /stage/state` | `stage_operator`, `administrator` |
| `POST /stage/complete-current` | `stage_operator`, `administrator` |
| `private-stage.{ceremonyId}` subscription | Active `stage_operator` or `administrator` session for the sole active ceremony |

All access uses Phase 2 session authentication, CSRF protection, active-account
enforcement, and Laravel gates. Phase 3 does not introduce device keys or token
authentication.

React may use server-provided abilities to decide which links to show, but the
server remains the authorization boundary.

## 4. Active ceremony requirement

Exactly one PostgreSQL ceremony must have status `active`.

- Zero active ceremonies is an operational error.
- More than one active ceremony is an operational error.
- Scanning, stage state, completion, and private-channel authorization must not
  silently choose a ceremony when the state is zero or ambiguous.
- Phase 3 provides no activation or ceremony-management UI.

Both zero and multiple cases produce a safe `ceremony_inactive` scan response.
The operator message distinguishes missing from ambiguous configuration without
exposing graduate data.

## 5. Temporary presentation policy

The following policy is explicit and temporary:

1. When the stage is idle, one valid eligible scan immediately becomes
   `presenting`.
2. When the stage is presenting, another student is rejected as `stage_busy`.
3. A scan never replaces or preempts the current graduate.
4. A duplicate active graduate is rejected as `duplicate_active`.
5. A graduate with completed history is rejected as `already_completed`.
6. The only Phase 3 stage command is complete-current, which completes the current
   row and clears the stage.
7. There is no waiting queue behavior, present-next, present-selected, reorder,
   skip, return, replay command, mode switch, or automatic advancement.

Phase 7 must replace this temporary policy with the approved full queue and
automatic/controlled mode transition rules.

## 6. Scanner requirements

`/scanner` must:

- keep the token input focused on load and after each attempt;
- accept keyboard-wedge input ending in Enter;
- allow manual token entry for controlled testing and fallback;
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
| `accepted` | 201 | Eligible graduate committed and is now presenting |
| `invalid_token` | 422 | No graduate in the active ceremony matches |
| `ceremony_inactive` | 409 | Active ceremony state is zero or ambiguous |
| `student_ineligible` | 422 | Matching graduate is not eligible |
| `duplicate_active` | 409 | Graduate already has an active queue row |
| `already_completed` | 409 | Graduate has completed history |
| `stage_busy` | 409 | Another graduate is presenting |
| `unauthorized` | 403 | Authenticated user lacks scanner permission |

Malformed/missing/overlong input receives a structured `invalid_token` validation
response. No raw invalid token may be echoed back.

## 8. Transactional scan requirements

`ScanStudentForPresentation` must use one PostgreSQL transaction and:

1. Resolve and lock the exactly-one active ceremony state.
2. Lock or initialize its `stage_states` row.
3. Resolve the normalized token within that ceremony.
4. Verify eligibility.
5. check active and completed queue history.
6. Verify the stage is idle.
7. Insert one `stage_queue_items` row with status `presenting`.
8. Set `stage_states.current_queue_item_id`, display mode `graduate`, and increment
   the state version.
9. Insert one `student_presented` stage event.
10. Insert an accepted scan log.
11. Commit all changes.
12. Dispatch the safe synchronous broadcast only after the transaction returns.

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
the workflow and are not recorded as scanner attempts.

## 10. Real-time requirements

The success event is `StudentPresented`; the clear event is
`StagePresentationCleared`.

Both:

- implement synchronous Laravel broadcasting so no queue worker is required;
- are dispatched after the database transaction completes;
- publish on `private-stage.{ceremonyId}`;
- use Echo event names `.student.presented` and `.stage.cleared`; and
- may fail to deliver without rolling back committed PostgreSQL state.

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
| Two valid simultaneous scans | Exactly one presenting item; the other receives a safe conflict |
| Same graduate retried | One active item; later attempt receives `duplicate_active` |
| Completion races a scan | Row lock determines order; final queue/state/version remain consistent |
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

## 18. MVP and deferred boundaries

The eventual MVP includes ceremony/student administration, import and validation,
QR creation/custody, durable queue operation, automatic and controlled modes,
stage controls, local media, logs/reporting, backup/restore, deployment hardening,
and rehearsal.

Phase 3 includes only the critical scan-to-stage slice. Deferred work includes:

- ceremony and student CRUD;
- CSV import/export and preflight;
- QR generation, print, regeneration, and custody;
- activation UI;
- device registration/authentication;
- general queue and ordering;
- present-next/selected, reorder, skip, return, undo, and replay;
- automatic/controlled mode switching;
- final photo/audio workflows;
- report and audit interfaces;
- backup/restore and standby tooling;
- production process supervision, TLS, firewall automation, and kiosk setup; and
- full physical rehearsal.

## 19. Assumptions and unresolved questions

Phase 3 assumptions:

- Tokens are compared case-insensitively after trim/uppercase normalization.
- The sole active ceremony and its stage state represent the current operation.
- A completed graduate cannot be re-presented in this temporary slice.
- Cancelled/skipped historical rows do not occur because Phase 3 has no commands
  that create them.
- Stage and scanner browsers each use an appropriate authenticated account.
- A fixed server address and TCP `8000`/`8080` are acceptable for development.
- HTTP is acceptable on a controlled development LAN pending the local TLS
  decision.

Still requiring approval:

1. Can more than one ceremony ever be active?
2. What is the final activation/deactivation authority and workflow?
3. What are the final duplicate, retry, exception, and manual-override rules?
4. What triggers automatic mode, and may mode change mid-ceremony?
5. What is the legal queue transition table for Phase 7?
6. Are scanner/stage device credentials required in addition to user sessions?
7. What browser, kiosk, resolution, branding, safe-area, and accessibility rules
   apply to the stage?
8. What graduate fields and honors formatting are institutionally approved?
9. What photo/audio formats, sizes, loudness, duration, pronunciation, and approval
   process are required?
10. What fixed IP, hostname, wired/wireless, firewall, TLS, and backup-network
    design is approved?
11. What are the expected graduate count, scan burst, latency target, retention,
    privacy notice, RPO, and RTO?
12. Where will the completion/control view run in the final three-laptop setup?
13. What backup, restore, standby, UPS, and power-loss process is required?
14. What physical scanner, projector/LED, and audio hardware is standardized?

Phase 4 must not begin until the Phase 3 implementation and these documented
boundaries are reviewed.
