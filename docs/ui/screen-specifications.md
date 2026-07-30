# Screen Specifications

Status: Phase 8 screens implemented
Updated: 2026-07-30

`/scanner`, `/stage`, Phase 4 administration, and the Phase 5 CSV import
workspace are implemented. Later specifications preserve future intent and are
not implementation authorization.

## Scanner

| Item | Specification |
| --- | --- |
| Route | `/scanner` |
| Primary user | Scanner operator |
| Device | Scanner laptop; 1024px minimum supported width |
| Goal | Submit one QR token and immediately know the confirmed result |
| Dominant information | Scanner readiness/input and current result |
| Primary action | USB scanner Enter submission; manual process button remains fallback |
| Secondary information | Active ceremony, server state, live-update transport, last success |
| Time pressure | Continuous ceremony operation |
| Common mistakes | Repeat scan, wrong QR, scanning while stage busy, assuming an unconfirmed request succeeded |
| Prohibited content | Analytics, graduate table, queue controls, charts, decorative dashboard panels |

### Required states

- Ready
- Processing
- Successful scan
- Invalid QR
- Duplicate active QR
- Already completed
- Ineligible graduate
- Stage busy
- Ceremony inactive/paused or ambiguous
- Server request failed
- Reverb disconnected
- Reconnected
- Missing graduate display information
- Long graduate name

### Keyboard and recovery

- Focus token input on load, browser-window return, and after a completed attempt.
- Enter submits.
- Tab reaches the process button and dashboard link normally.
- Pending requests cannot submit again.
- Never tell the operator to rescan when the server response is ambiguous;
  instruct them to check connection first.
- Reverb disconnection must say that HTTP scanning remains available.

### Selected textual wireframe

```text
┌──────────────────────────────────────────────────────────────────────┐
│ SCANNER STATION                     [Return to operations]           │
│ Ready to scan                                                       │
│ Sample Graduation Ceremony · Active                                 │
├──────────────────────────────────────────────────────────────────────┤
│ SYSTEM STATUS                                                        │
│ Scanner ready     Server available     Live updates connected        │
├──────────────────────────────────────────────────────────────────────┤
│ Scan graduate QR                                                   │
│ [ focused token input                                  ] [Process]   │
│ USB scanner: scan once and wait for the confirmed result.            │
├──────────────────────────────────────────────────────────────────────┤
│ CURRENT RESULT                                                       │
│ QR code not recognized                                               │
│ Scan the assigned QR again. If it still fails, ask data support.     │
├──────────────────────────────────────────────────────────────────────┤
│ LAST SUCCESS  Christopher Emmanuel Villanueva-Santos                 │
└──────────────────────────────────────────────────────────────────────┘
```

This single-console layout is selected over equal status cards because it makes
readiness and the current result dominant while retaining compact supporting
state.

### Acceptance criteria

- Focus, Enter, pending lock, clear, and refocus still work.
- Each server status produces specific plain-language recovery.
- Readiness, ceremony, server, and live-update state are readable without color.
- Long names wrap without horizontal overflow.
- Required layouts fit 1440×900, 1366×768, and 1024×768.

### Phase 6 scanner hardening

The QR field remains the dominant control. A compact, initially closed manual
lookup follows the current result and last success; it is recovery rather than a
second primary workflow.

- Clicking non-interactive scanner-console space restores focus to the QR field.
  Interacting with links, buttons, or manual-lookup controls does not have focus
  stolen.
- One Enter suffix submits once. A second empty Enter immediately after a
  completed attempt is ignored without replacing the confirmed result.
- The pending lock remains synchronous and server concurrency rules remain
  authoritative.
- Manual lookup searches only the active ceremony and returns at most ten
  minimal matches: name, program, sequence, and eligibility. It never returns a
  token.
- Processing a lookup result delegates to the unchanged scan transaction and
  produces the same accepted, duplicate, completed, ineligible, and busy
  outcomes.
- Both paths clear sensitive input after processing. Neither places a token in a
  URL, log message, browser console message, or rendered result.

## Audience stage

| Item | Specification |
| --- | --- |
| Route | `/stage` |
| Primary users | Audience and presented graduate |
| Device | Projector/LED display; 1366×768 minimum |
| Goal | Present the authoritative graduate identity clearly at distance |
| Dominant information | Graduate display name |
| Secondary information | Program, major, honors, ceremony identity, optional photo |
| Primary action | None on the visible audience display |
| Prohibited content | Navigation, controls, tokens, student numbers, timestamps, logs, connection labels, technical warnings |

### Required states

- Initial loading
- No graduate/idle
- Graduate presented
- Long graduate name
- Long program
- Missing photo
- Missing honors
- Missing audio
- Reverb disconnected
- State recovered through HTTP
- Stage cleared

Connection and recovery states must preserve a clean audience view. Their
technical state is internal and must not become visible copy.

### Selected textual wireframe

```text
┌──────────────────────────────────────────────────────────────────────┐
│                                                                      │
│                    SAMPLE GRADUATION CEREMONY                        │
│                    ─────────────────────────                         │
│                                                                      │
│          Christopher Emmanuel Villanueva-Santos                      │
│                                                                      │
│          Bachelor of Science in Electronics Engineering             │
│                         Magna Cum Laude                              │
│                                                                      │
└──────────────────────────────────────────────────────────────────────┘
```

When an approved photo exists, it may sit above or beside the identity only if
the name remains dominant. Missing media does not reserve blank space.

### Acceptance criteria

- No audience-visible technical or operator UI remains.
- Loading is neutral and does not flash an incorrect idle state.
- The last authoritative state remains during transient transport loss.
- Long names/programs fit 1920×1080 and 1366×768 without scrolling.
- Clearing returns to the approved idle ceremony identity.
- Optional content disappears cleanly.

## Stage control — Phase 7 implemented

| Item | Specification |
| --- | --- |
| Route | `/stage-control` |
| Primary user/device | Stage operator on the stage laptop |
| Goal | Present and complete the correct graduate without losing queue context |
| Dominant information | Current graduate and one approved next action |
| Primary actions | Present next/selected, complete, skip/return, replay, holding/restore, pause/resume automatic |
| Secondary information | Upcoming queue, ceremony mode, compact connection/approved-audio status, recent actions |
| Required states | Loading, empty queue, queued, presenting, paused, command pending/failed, disconnected, recovered |
| Keyboard | Predictable tab order and shortcuts only when documented and non-conflicting |
| Responsive targets | 1440×900, 1366×768, 1024×768 |
| Accessibility | Named controls, visible focus, confirmation for risky actions, textual status |
| Recovery | Disable duplicate commands while pending; reconcile current queue/stage state through HTTP |
| Prohibited content | Audience styling, hidden destructive shortcuts, fake throughput metrics, student QR tokens |

```text
┌──────────────────────────────────────────────────────────────────────┐
│ STAGE CONTROL   Sample Graduation Ceremony     Controlled · Connected│
├─────────────────────────────────────┬────────────────────────────────┤
│ CURRENT GRADUATE                    │ UPCOMING QUEUE                 │
│ Christopher Emmanuel               │ 1  Ana Marie dela Cruz         │
│ Villanueva-Santos                   │ 2  Ma. Lourdes P. Reyes        │
│ BS Business Administration         │ ...                            │
│                                     │                                │
│ [Complete presentation]             │ [Present selected]             │
├─────────────────────────────────────┴────────────────────────────────┤
│ RECENT ACTIONS                                                       │
└──────────────────────────────────────────────────────────────────────┘
```

The selected layout uses a dominant current-presentation area beside the
deterministic queue at wide viewports, followed by compact skipped/recent
history. At 1024 px the current area stacks above the table so the primary
action remains visible before secondary queue detail. Automatic mode exposes
only a clearly labeled persisted pause/resume control; it does not imply
auto-advance. Pending commands disable every mutation until HTTP reconciliation
finishes. Holding and transport warnings stay operator-only.

Acceptance requires safe empty/loading/pending/failure/disconnected/recovered
states; long names/programs without horizontal page overflow; visible focus and
textual status; explicit confirmation for completion, skip, out-of-order
selection, and holding; no token/student-number content; and audio guidance that
distinguishes approved audio from uploaded, needs-review, rejected, missing, or
unreadable files. Replay is disabled unless the current graduate has approved
readable audio.

## Administration index — Phase 4 implemented

| Item | Specification |
| --- | --- |
| Route | `/admin` |
| Primary user/device | Administrator or data encoder on an operator laptop |
| Goal | Reach ceremony and graduate data tasks and see blocking readiness issues |
| Dominant information | Current ceremony readiness and unresolved data problems |
| Primary action | Open the task that needs attention |
| Required states | Loading, no ceremony, draft/prepared/active ceremony, data warning, access denied |
| Prohibited content | Decorative KPIs, trend charts, marketing welcome panel, live stage controls |

Use a compact task list/readiness summary, not an equal card dashboard. Detailed
status counts are database facts for the selected active/latest ceremony and lead
to ceremony or graduate records. Administrator-only audit history appears as a
task link; the data encoder never receives that ability.

## Ceremony administration — Phase 4 implemented

| Item | Specification |
| --- | --- |
| Route | `/admin/ceremonies` |
| Primary user/device | Administrator manages; data encoder has read-only list/detail access |
| Goal | Create, review, and safely change ceremony configuration |
| Dominant information | Ceremony identity, date, venue, status, and presentation mode |
| Primary actions | Add/edit ceremony; activation controls require explicit status rules |
| Required states | Loading, empty, list, validation error, saved, activation blocked, active/paused/completed |
| Keyboard/accessibility | Native form flow, errors linked to fields, status conveyed in text |
| Responsive targets | 1440×900, 1366×768, 1024×768; table owns horizontal adaptation |
| Recovery | Preserve entered values on validation failure; never imply activation before confirmation |
| Prohibited content | Stage operator controls, inline QR tokens, silent status changes |

Implemented pages are index, create, detail, and edit. The list supports ceremony
name search, status/year filters, and 15-row pagination. The form fields are name,
academic year, venue, event date, status, and presentation mode. Activation
conflicts, invalid transitions, and presenting-state changes return linked field
errors. Detail makes graduate/queue/scan/event/stage-state deletion protection
visible. Only administrators see create/edit/delete controls.

## Graduate administration — Phase 4 implemented

| Item | Specification |
| --- | --- |
| Route | `/admin/students` |
| Primary user/device | Administrator or data encoder on an operator laptop |
| Goal | Find and correct a graduate record before ceremony operation |
| Dominant information | Searchable graduate records and data-quality status |
| Primary actions | Add/edit graduate within role permissions |
| Secondary information | Ceremony, sequence, eligibility, honors, local media availability |
| Required states | Loading, empty, results, no results, form, validation errors, saved, duplicate student/token conflict, missing media |
| Keyboard | Search, table, form, and pagination follow DOM order; no row action is hover-only |
| Responsive targets | 1440×900, 1366×768, 1024×768 |
| Accessibility | Table headers, explicit row actions, field errors, textual eligibility/media status |
| Recovery | Retain submitted values; identify the exact conflicting field/record without exposing QR tokens in lists |
| Prohibited content | Graduate profile cards, stage presentation actions, raw token display, decorative statistics |

```text
┌──────────────────────────────────────────────────────────────────────┐
│ GRADUATES                                      [Add graduate]        │
│ Sample Graduation Ceremony                                          │
├──────────────────────────────────────────────────────────────────────┤
│ [Search name or student number                         ] [Filters]   │
├──────────────────────────────────────────────────────────────────────┤
│ NAME                         PROGRAM               STATUS    ACTION  │
│ Ana Marie dela Cruz          BS Computer Science   Eligible  Edit    │
│ Christopher Emmanuel ...     BS Business Admin.    Eligible  Edit    │
│ Ma. Lourdes P. Reyes         BS Electronics Eng.   Review    Edit    │
├──────────────────────────────────────────────────────────────────────┤
│ 3 results                                      [Previous] [Next]    │
└──────────────────────────────────────────────────────────────────────┘
```

Implemented list filters are ceremony, college, program, eligibility, media
readiness, and read-only stage status. Search covers reviewed names and student
number; sorting covers ceremony/sequence, display name, and student number;
pagination uses 20 rows and preserves filters.

The form uses explicit sections:

1. assignment: ceremony, student number, positive sequence, hidden QR token;
2. names: separate first/middle/last/suffix plus reviewed display,
   announcement, and pronunciation fields;
3. academic/eligibility: college, program, major, honors, eligibility;
4. local media: JPEG/PNG/WebP photo and MP3/WAV/OGG audio with current preview,
   replacement, missing state, readiness status, and explicit removal.

Administrators and data encoders may create/edit and manage media. Only
administrators may delete, and history blocks deletion. Current QR values never
appear in page props; edit offers an optional hidden replacement field.

### Phase 8 audio review addition

Graduate detail is the focused pronunciation-review surface. It shows:

- stage display name, approved announcement name, and pronunciation guide;
- native local preview control;
- readiness status;
- safe filename label, MIME type, file size, uploaded time, approval time, and
  approver when available;
- current review notes;
- data-encoder/admin `Mark needs review`;
- administrator-only `Approve audio` and `Reject audio`.

The review panel must stay inside the existing administration shell, use native
textarea/buttons, and avoid waveform previews, analytics, decorative audio
visualizers, or cloud/AI speech controls. Rejection requires a short note.
Approval requires a readable local file and never happens automatically from
upload success.

The graduate list media filter includes audio uploaded, needs review, approved,
rejected, and missing. The admin dashboard includes a focused readiness summary:
total, approved, uploaded, needs review, missing, and rejected. It is not an
analytics dashboard.

## Administrative audit history — Phase 4 implemented

| Item | Specification |
| --- | --- |
| Route | `/admin/audit-logs` |
| Primary user | Administrator only |
| Goal | Review important ceremony, graduate, media, and import changes |
| Dominant information | Time, actor, action, entity ID, expandable safe before/after values |
| Filters | Action text and ceremony/graduate/import entity type |
| Required states | Results, empty, no matches, access denied |
| Privacy | No raw QR values, original filenames, media paths/content, passwords, or serialized models |
| Prohibited content | Operational scan logs, stage events, raw tokens, report charts, edit controls |

This page is administrative change history required by Phase 4. General
operational log investigation, reporting, and exports remain Phase 9.

## CSV import — Phase 5 implemented

| Item | Specification |
| --- | --- |
| Routes | `/admin/students/import` with separate inspect, mapping, preview, confirm, summary, and template endpoints |
| Primary user/device | Administrator or data encoder on an operator laptop |
| Goal | Review and atomically create graduate records from one local CSV |
| Dominant information | Current step, selected ceremony, mapping or row-level validation results |
| Primary action | Inspect file, review mapping, or confirm import according to the current step |
| Secondary information | Supported limits, safe filename, ignored columns, counts, and recovery guidance |
| Required states | No file, reading, mapping, mapping error, preview valid, preview warnings, row errors, ready, importing, complete, expired, ownership denied, failed |
| Keyboard | Native file/select/checkbox/button order; field errors are linked; no hover-only actions |
| Responsive targets | 1440×900, 1366×768, 1024×768; mapping and preview tables own horizontal overflow |
| Recovery | Mapping remains server-managed; return to mapping; invalid/failed confirmation imports zero rows |
| Prohibited content | Automatic commit on upload, browser-trusted row state, raw QR tokens, cloud file services, update/sync controls, analytics |

### Layout alternatives considered

1. A single long wizard page keeps every step visible but makes upload and
   confirmation feel insufficiently separate and becomes difficult to scan at
   1024 px.
2. Separate upload, mapping, preview, and summary pages give each decision one
   dominant task, create a clear HTTP boundary before confirmation, and allow
   focused recovery.
3. A split mapping/preview workspace makes comparison immediate but compresses
   long headings, samples, and error messages on the minimum laptop viewport.

Option 2 is selected. A short semantic progress line relates the pages without
turning them into a decorative stepper.

### Selected textual wireframes

```text
UPLOAD
┌──────────────────────────────────────────────────────────────────────┐
│ IMPORT GRADUATES                         Step 1 of 4 · Select CSV    │
│ Prepare create-only graduate records for one ceremony.              │
├──────────────────────────────────────────────────────────────────────┤
│ Ceremony *  [Sample Graduation Ceremony                         ▾]  │
│ CSV file *  [Choose file]  No file selected                         │
│                                                                      │
│ [Inspect columns]                         [Download CSV template]    │
├──────────────────────────────────────────────────────────────────────┤
│ FORMAT AND LIMITS                                                   │
│ UTF-8 · comma/semicolon/tab · 2 MiB · 2,000 rows · 40 columns       │
└──────────────────────────────────────────────────────────────────────┘

MAPPING
┌──────────────────────────────────────────────────────────────────────┐
│ MAP CSV COLUMNS                         Step 2 of 4 · Review mapping │
│ Sample Graduation Ceremony · graduates-july.csv                     │
├──────────────────────────────────────────────────────────────────────┤
│ SOURCE HEADER          SAMPLE VALUE                    MAP TO        │
│ Student Number         2027-0101                       Student no. ▾ │
│ Full Name              Ana Marie dela Cruz             Ignore      ▾ │
│ Program                BS Computer Science             Program     ▾ │
├──────────────────────────────────────────────────────────────────────┤
│ [Choose another file]                         [Preview 128 rows]     │
└──────────────────────────────────────────────────────────────────────┘

PREVIEW
┌──────────────────────────────────────────────────────────────────────┐
│ REVIEW IMPORT                           Step 3 of 4 · Validate rows  │
│ Sample Graduation Ceremony · Create new graduates only              │
├──────────────────────────────────────────────────────────────────────┤
│ 128 total · 120 valid · 8 warnings · 0 errors                       │
│ Ignored: Adviser email                                               │
├──────────────────────────────────────────────────────────────────────┤
│ ROW  RESULT   STUDENT / PROGRAM                         MESSAGES      │
│ 2    Warning  Ana Marie dela Cruz · BS Computer Science Generated…  │
│ 3    Valid    Christopher Emmanuel Villanueva-Santos    None        │
├──────────────────────────────────────────────────────────────────────┤
│ [Return to mapping]                            [Previous] [Next]     │
│ [ ] I reviewed the ceremony, mapping, warnings, and row count.       │
│                                      [Import 128 graduates]          │
└──────────────────────────────────────────────────────────────────────┘
```

The confirm region appears only when there are no blocking errors. With errors,
the same position contains a concise correction instruction and no confirm
button. Summary is a compact definition list of total/created/warnings/times
with links to the ceremony-filtered graduate list and a new import.

### Behavior and acceptance criteria

- Upload and confirmation are separate server-authorized HTTP requests.
- Temporary content is encrypted, owner-bound, expiring, and never returned to
  the browser as confirmation authority.
- Mapping is by source-column position. Duplicate targets and missing required
  targets receive linked errors; ambiguous columns remain visibly ignored.
- Preview shows 50 rows per page with row number, text result, normalized values,
  generated/existing indicators, and actionable messages.
- Long names/programs/messages wrap inside a table-owned horizontal container.
- Processing disables duplicate submissions and communicates the active action.
- Confirmation requires a checkbox and revalidates every row through
  PostgreSQL; failure changes no graduate record.
- Required layouts are inspected at 1440×900, 1366×768, and 1024×768.

The compiled authenticated workflow passed browser checks for local template
download, upload/no-file, automatic mapping, long realistic samples,
warning/confirm, blocked duplicate/eligibility, summary, filtered graduate
retrieval, safe audit, reduced motion, and scanner-role denial. Screenshots and
the verification boundary are indexed under `docs/evidence/phase-5/`.

## QR preparation and print — Phase 6

| Item | Specification |
| --- | --- |
| Routes | `/admin/students/qr-codes` plus protected individual image/preview/print and batch-print actions |
| Primary user/device | Administrator or data encoder on an operator laptop; 1024px minimum |
| Goal | Generate and print local QR material for confirmed graduate records |
| Dominant information | Ceremony scope, eligible record selection, generation/print readiness |
| Primary action | Print the explicitly selected graduate or batch |
| Secondary information | College/program/eligibility/readiness/sequence filters, physical print guidance, safe regeneration status |
| Required states | No ceremony, empty ceremony, no matches, selectable records, empty selection, over-limit selection, ready, print response, regeneration confirmation/conflict/blocked |
| Keyboard | Native filters, checkboxes, links, and confirmation controls in DOM order; no hover-only action |
| Responsive targets | 1440×900, 1366×768, and 1024×768; the selection table owns horizontal overflow |
| Recovery | Keep filters after validation; reject the whole batch when any selected record is invalid; regenerate only through an explicit administrator action |
| Prohibited content | Internet QR service, exposed token text, public image route, uncontrolled bulk regeneration, PDF/cloud dependency, decorative card dashboard |

### Layout alternatives considered

1. Adding QR checkboxes and print controls to the general graduate list reduces
   navigation, but mixes data correction with print custody and makes that
   already dense table harder to use at 1024 px.
2. A dedicated readiness workspace with one selection table and a separate
   individual preparation page keeps ceremony scope, filtering, batch custody,
   replacement printing, and administrator-only regeneration explicit.
3. A multi-step print wizard can force review, but introduces state and
   navigation overhead for a deterministic server-validated print request.

Option 2 is selected. It keeps one dominant task per page and requires no
temporary batch model.

### Selected textual wireframes

```text
QR READINESS
┌──────────────────────────────────────────────────────────────────────┐
│ QR PREPARATION                                  [Graduate records]   │
│ Select verified graduates for local print output.                   │
├──────────────────────────────────────────────────────────────────────┤
│ Ceremony * [Sample Graduation Ceremony                         ▾]  │
│ College [All ▾]  Program [All ▾]  Eligibility [Eligible ▾]          │
│ Sequence [from] [to]  QR readiness [All ▾]            [Apply]       │
├──────────────────────────────────────────────────────────────────────┤
│ [ ] NAME / PROGRAM                         SEQ  ELIGIBLE  QR         │
│ [ ] Christopher Emmanuel Villanueva-...    18   Eligible  Ready      │
│ [ ] Ma. Lourdes P. Reyes                   19   Pending   Ready      │
├──────────────────────────────────────────────────────────────────────┤
│ 2 selected · maximum 100                       [Print selected QR]   │
└──────────────────────────────────────────────────────────────────────┘

INDIVIDUAL PREPARATION
┌──────────────────────────────────────────────────────────────────────┐
│ INDIVIDUAL QR CARD                              [Back to selection] │
│ Sample Graduation Ceremony · Sequence 18                            │
├────────────────────────────────────┬─────────────────────────────────┤
│ Christopher Emmanuel              │                                 │
│ Villanueva-Santos                  │        protected QR image       │
│ BS Electronics Engineering        │                                 │
│ Student number: 2027-0018          │        QR token not shown       │
│ QR readiness: Ready                │                                 │
├────────────────────────────────────┴─────────────────────────────────┤
│ [Print individual card]                                             │
│ ADMIN ONLY: Replace QR token [confirmation] [Replace token]         │
└──────────────────────────────────────────────────────────────────────┘
```

### Print contract

- Output is protected print-optimized HTML with dynamic same-origin SVG images.
- Individual: A4 portrait, one centered staff card, 65 mm QR.
- Batch: A4 portrait, 12 mm margins, two columns by two rows, four cards per
  page, 55 mm QR.
- QR images are opaque black on white with a four-module quiet zone, no logo,
  decoration, transparency, or visible raw token.
- Cards use physical CSS units and `break-inside: avoid`.
- Long names and programs wrap; QR dimensions never shrink.
- Recommended settings are 100% scale, fit-to-page disabled, background graphics
  unnecessary, and browser headers/footers disabled.
- Maximum batch size is 100; ordering is sequence number, display name, then ID.

### Behavior and acceptance criteria

- Administrators and data encoders may view and print; only administrators may
  regenerate.
- All selected IDs are deduplicated and revalidated against the submitted
  ceremony, eligibility, token availability, and maximum size.
- Dynamic QR output encodes only the authoritative opaque token and stores no
  image file.
- Ordinary list/detail props and supporting print text never contain the raw
  token.
- Regeneration uses confirmation, a stale-page guard, a PostgreSQL row lock,
  server-only token generation, and a safe audit. Presenting or completed
  graduates cannot be regenerated.
- Previously issued output becomes invalid immediately after commit; the
  replacement output uses the new token.
- Required operator viewports and Chromium print output are inspected with
  long realistic names and programs.

## Operational logs and reports — Phase 9 implemented

| Item | Specification |
| --- | --- |
| Route | `/admin/scan-logs`, `/admin/stage-events`, `/admin/reports/ceremony-summary`, `/admin/backups` |
| Primary user | Administrator or technical operator |
| Goal | Investigate ceremony actions and scan outcomes |
| Dominant information | Filtered chronological records with actor, action/result, and time |
| Primary action | Filter, export when authorized, verify backup, or reset current operations when explicitly confirmed |
| Required states | Loading, empty, results, no matches, retrieval/export failure |
| Recovery/privacy | Preserve filters; redact QR secrets and avoid audience/student-sensitive excess |
| Prohibited content | Live stage controls, raw stack traces, raw QR tokens, invented analytics |

Use working tables and compact filters rather than dashboards or charts. Backup
restore remains a CLI procedure; the browser shows metadata and
non-destructive verification status only.

## Deployment-device usage — Phase 10 implemented

Phase 10 does not add new browser screens. It defines where existing screens are
allowed to run during local deployment:

| Route | Deployment placement |
| --- | --- |
| `/scanner` | Scanner laptop using the server LAN URL |
| `/stage` | Stage laptop in fullscreen on the projector/LED display |
| `/stage-control` | Protected operator device or separate protected browser profile; never projected |
| `/admin/*` | Administrator or technical device only |

All Phase 10 browser checks must use compiled production assets and must not
depend on the Vite development server. Audience-facing `/stage` remains free of
navigation, operator controls, QR tokens, technical logs, terminals, desktop
notifications, and server/debug details.

## Future settings — post-Phase 10, confirmation required

| Item | Specification |
| --- | --- |
| Route | `/settings` |
| Primary user | Administrator or technical operator |
| Goal | Manage only approved local operational configuration |
| Dominant information | Current setting value, effect, validation, and restart/rehearsal impact |
| Required states | Loading, saved, validation error, permission denied, restart required |
| Recovery | Preserve last valid value and explain how to safely apply/revert a change |
| Prohibited content | Cloud accounts, CDN/font configuration, secrets in browser output, unrelated preferences |

Ownership and phase remain unresolved because some settings may be application
records while network, kiosk, service, and firewall settings belong to operator
deployment procedures. Do not implement until that boundary is approved.

## Existing login and dashboard

Keep calm and functional. Phase 3A does not redesign them beyond shared token
compatibility required by scanner/stage CSS.
