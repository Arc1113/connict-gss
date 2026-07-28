# Screen Specifications

Status: Phase 3A foundation  
Updated: 2026-07-28

Only `/scanner` and `/stage` are implemented in Phase 3A. Other specifications
preserve future intent and are not implementation authorization.

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

## Future stage control — Phase 7

| Item | Specification |
| --- | --- |
| Route | `/stage-control` |
| Primary user/device | Stage operator on the stage laptop |
| Goal | Present and complete the correct graduate without losing queue context |
| Dominant information | Current graduate and one approved next action |
| Primary actions | Present next/selected, complete, clear; final action set belongs to Phase 7 |
| Secondary information | Upcoming queue, ceremony mode, compact connection/audio status, recent actions |
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

Phase 7 must confirm controlled/automatic mode behavior, command permissions,
queue recovery, and destructive-action safeguards before implementation.

## Future administration index — Phase 4

| Item | Specification |
| --- | --- |
| Route | `/admin` |
| Primary user/device | Administrator on an operator laptop |
| Goal | Reach ceremony and graduate data tasks and see blocking readiness issues |
| Dominant information | Current ceremony readiness and unresolved data problems |
| Primary action | Open the task that needs attention |
| Required states | Loading, no ceremony, draft/prepared/active ceremony, data warning, access denied |
| Prohibited content | Decorative KPIs, trend charts, marketing welcome panel, live stage controls |

Use a compact task list/readiness summary, not an equal card dashboard. Detailed
status counts must be real database facts and must lead to corrective records.

## Future ceremony administration — Phase 4

| Item | Specification |
| --- | --- |
| Route | `/admin/ceremonies` |
| Primary user/device | Administrator on an operator laptop |
| Goal | Create, review, and safely change ceremony configuration |
| Dominant information | Ceremony identity, date, venue, status, and presentation mode |
| Primary actions | Add/edit ceremony; activation controls require explicit status rules |
| Required states | Loading, empty, list, validation error, saved, activation blocked, active/paused/completed |
| Keyboard/accessibility | Native form flow, errors linked to fields, status conveyed in text |
| Responsive targets | 1440×900, 1366×768, 1024×768; table owns horizontal adaptation |
| Recovery | Preserve entered values on validation failure; never imply activation before confirmation |
| Prohibited content | Stage operator controls, inline QR tokens, silent status changes |

## Future graduate administration — Phase 4

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

Phase 4 must define exact form fields, filters, role permissions, pagination,
and safe media editing before implementation.

## Future CSV import — Phase 5

| Item | Specification |
| --- | --- |
| Route | `/admin/import` |
| Primary user | Administrator or data encoder |
| Goal | Validate a local CSV before any graduate record is committed |
| Dominant information | File, column mapping, validation summary, and row errors |
| Primary action | Confirm import only after preview passes |
| Required states | No file, reading, mapping, preview, warnings, row errors, ready, importing, complete, failed |
| Recovery | Preserve mapping/error details; failed validation creates no partial import |
| Prohibited content | Automatic commit on upload, vague aggregate error, cloud file service |

The screen must use local files, show representative rows, support keyboard
mapping, and keep the confirmation action visually separate from file selection.

## Future QR preparation — Phase 6

| Item | Specification |
| --- | --- |
| Route | `/admin/qr-codes` |
| Primary user | Administrator or data encoder |
| Goal | Generate and print local QR material for confirmed graduate records |
| Dominant information | Ceremony scope, eligible record selection, generation/print readiness |
| Primary action | Generate or print the explicitly selected set |
| Required states | Empty ceremony, no eligible records, selectable list, generating, ready, print failure |
| Recovery | Regeneration remains tied to the authoritative stored token; test with physical scanner |
| Prohibited content | Internet QR service, exposed token text in normal lists, uncontrolled bulk regeneration |

## Future logs and reports — Phase 9

| Item | Specification |
| --- | --- |
| Route | `/logs` |
| Primary user | Administrator or technical operator |
| Goal | Investigate ceremony actions and scan outcomes |
| Dominant information | Filtered chronological records with actor, action/result, and time |
| Primary action | Filter/export only when Phase 9 requirements approve it |
| Required states | Loading, empty, results, no matches, retrieval/export failure |
| Recovery/privacy | Preserve filters; redact QR secrets and avoid audience/student-sensitive excess |
| Prohibited content | Live stage controls, raw stack traces, raw QR tokens, invented analytics |

The updated playbook does not currently approve a separate `/reports` page.
Phase 9 must decide whether reporting remains within `/logs` before adding a
route.

## Future settings — Phase 9 or 10, confirmation required

| Item | Specification |
| --- | --- |
| Route | `/settings` |
| Primary user | Administrator or technical operator |
| Goal | Manage only approved local operational configuration |
| Dominant information | Current setting value, effect, validation, and restart/rehearsal impact |
| Required states | Loading, saved, validation error, permission denied, restart required |
| Recovery | Preserve last valid value and explain how to safely apply/revert a change |
| Prohibited content | Cloud accounts, CDN/font configuration, secrets in browser output, unrelated preferences |

Ownership and phase are unresolved because some settings may be application
records (Phase 9) while network/kiosk/service settings belong to deployment
(Phase 10). Do not implement until that boundary is approved.

## Existing login and dashboard

Keep calm and functional. Phase 3A does not redesign them beyond shared token
compatibility required by scanner/stage CSS.
