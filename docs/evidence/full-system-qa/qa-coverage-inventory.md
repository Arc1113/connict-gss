# Full-system QA coverage inventory

Prepared before interactive workflow execution on 2026-08-01. The target is the isolated QA database `graduation_stage_system_full_qa_20260801` at `http://127.0.0.1:8002`.

## Shared claims and evidence

| Claim | Functional check | Visual state | Evidence |
| --- | --- | --- | --- |
| PostgreSQL is authoritative | Mutate through HTTP, refresh every participating browser, compare visible state | Post-scan, presenting, holding, corrected, completed | Playwright trace, HTTP responses, database summary |
| Echo/Reverb delivers committed changes | Keep scanner, control, and audience contexts open; act in one and observe the others without refresh | Connected and reconnected | Screenshots, console/network log, trace |
| Operators recover after refresh or transport loss | Refresh current presentation; separately interrupt Reverb, act over HTTP, restore Reverb, and require already-open clients to reconcile without refresh | Reconnecting and recovered | Screenshots, document IDs, trace, Reverb/app logs |
| Scanner is safe for repeated HID input | Type tokens and Enter as keyboard input; inspect pending lock, clearing, and focus | Ready, accepted, invalid, duplicate, server failure | Screenshots, focus measurements, request counts |
| Stage control preserves legal queue history | Exercise Present, selected Present, Skip, Return, Replay, Holding, correction, complete/advance, pause/resume | Empty, waiting, presenting, skipped, holding, automatic paused | Screenshots, HTTP/database evidence |
| Critical desktop surfaces fit | Inspect screenshots and region/document bounds at each required size | 1366x768, 1536x864, 1920x1080 | Viewport screenshots and fit JSON |
| Role boundaries are visible and enforced | Sign in through the UI as administrator and data encoder; visit allowed and denied routes | Admin, encoder, 403 | Trace and HTTP status log |
| Keyboard and accessibility basics work | Tab/Shift+Tab, Enter, Escape, shortcuts, focus restoration; axe scan | Login, shell, scanner, control, dialog | Focus log and axe JSON |

## Page and interaction inventory

### Public and authentication

| Page | Controls and states | Expected result |
| --- | --- | --- |
| `/login` | Email, password, keep-signed-in checkbox, submit, Enter, skip link | Valid role opens its assigned workspace; invalid credentials remain on the form with an associated error; inactive account is denied; focus order is logical |
| `/connect-phone` | Certificate download, HTTPS Scanner link, copyable/check-code guidance | Contains no credentials or ceremony commands; certificate is no-store; operator is directed to HTTPS before camera/auth use |

### Shared authenticated shell

| Page/surface | Controls and states | Expected result |
| --- | --- | --- |
| `/dashboard` | Sidebar toggle, mobile navigation/scrim, role-filtered navigation, primary operation links, user menu, overview link, sign out | Navigation stays inside the viewport, only authorized destinations appear, focus is visible, logout invalidates the session |
| Command palette | Command button, Ctrl+K, search, commands/shortcuts tabs, arrow/Tab navigation, result activation, close button, Escape | Opens with search focused, traps focus, runs only authorized commands, closes and restores focus |
| PWA status | Install/update/dismiss actions when available | Never interrupts live operations or claims offline mutation support |

### Administration and preparation

| Page | Controls and states | Expected result |
| --- | --- | --- |
| `/admin` | Blocker links, ceremony/student/import/user/log/report/backup task links | Gives an actionable readiness summary without fake analytics; data encoder sees only allowed work |
| `/admin/ceremonies` | Search, status/year filters, apply, clear, pagination, create, view, edit | Query is preserved, no-result state is clear, active ceremony is unmistakable |
| Ceremony create/edit | Name, academic year, venue, date, status, mode, colors, logo/background, portrait, theme/layout, submit/cancel | Validation stays field-linked and preserves input; valid save persists; dangerous lifecycle changes are guarded |
| Ceremony detail | Graduates, edit, operational status/history, delete | Links work; protected ceremony deletion is rejected; disposable unused QA ceremony can be deleted after confirmation |
| `/admin/students` | Search, ceremony/college/program/media/eligibility/stage filters, sort/direction, apply, clear, pagination, create/import/QR, view/edit | Filters and sort persist, rows remain readable, role restrictions hold |
| Graduate create/edit | Identity, ceremony, sequence, display/announcement names, program/major/honors, eligibility, pronunciation, photo/audio upload, recorder controls, remove media, submit/cancel | Required/unique validation is recoverable; server owns QR; media stays local; pending buttons lock |
| Graduate detail | QR/edit links, audio notes, audio preview, needs-review/approve/reject/remove, delete | Readiness transition persists and is role-authorized; previews do not overlap; deletion with history is blocked |
| Import upload | Ceremony, CSV file, optional ZIP, inspect, template | Invalid/missing/oversize input is rejected; accepted CSV advances without importing |
| Import mapping | Mapping selects, ignored columns, preview, restart | Required targets map once; invalid mapping is blocked; valid mapping advances |
| Import preview | Pagination, back to mapping, acknowledgement, confirm | Blocking rows prevent confirmation; warnings remain visible; confirmation is explicit and atomic |
| Import summary | Graduate list, start another import | Counts match committed rows; no QR tokens/source data leak |
| QR preparation | Filters, select visible/all rows, per-row selection, clear, batch print, individual review | Only printable eligible rows select; bounded batch output is local and deterministic |
| QR detail | Print, regenerate acknowledgement and submit | QR remains protected/no-store; stale/unacknowledged replacement fails; successful replacement invalidates old card |
| `/admin/users` | Search/role/status filters, apply/clear, create, edit | Password data never appears; filtering is bounded; only admin can access |
| User create/edit | Name, email, role, active checkbox, password/confirmation, submit/cancel | Validation is linked; blank edit password preserves hash; self/last-admin protections prevent unsafe changes |
| Scan logs | Ceremony/result/outcome/operator/text/date filters, apply/clear, Stage Events link, CSV export | Filters apply; export is formula-safe and excludes raw token/hash |
| Stage events | Ceremony/type/actor/text/date filters, apply/clear, Scan Logs link, CSV export | Safe event summaries only; filters and export match |
| Audit logs | Text/entity/date/action filters, apply/clear, export | Admin-only; secret, token, password, and media path values remain redacted |
| Ceremony summary | Ceremony filter, apply, export, reset acknowledgement/submit, event link | Metrics match PostgreSQL; reset is blocked while presenting and preserves history |
| Backups | Create backup, per-record verify | Status is persistent; failures are actionable; no browser restore mutates current data |
| Device connections | Copy address/check code, Print guide | Copies the safe local address, prints without clipping, exposes no credential/session token |

### Live ceremony operations

| Page | Controls and states | Expected result |
| --- | --- | --- |
| `/scanner` | Command menu, Phone setup, Operations link, Camera/USB switch, token input, Enter, Process, retry/focus action, fallback details, search, result radios, verified processing | USB input starts and returns focused; token clears; one pending request; accepted/rejected/duplicate states differ; manual lookup is bounded and requires selection; camera releases on switch |
| `/stage-control` | Command menu, open audience, operations link, queue radios, Present next, Present selected, Skip, Return, Replay, Complete and clear, Complete and present next, Correct wrong graduate dialog, Holding toggle, assisted-auto pause/resume | Every command is HTTP-confirmed, pending-locked, version-safe, visibly reconciled, and keyboard operable; current/next/actions remain visible |
| `/stage` | Enable audio only | Idle/holding/presenting/recovering visuals contain no controls or technical data; refresh restores current graduate; long names fit; audio failure remains visually safe |

## Critical off-happy-path scenarios

| Flow | Scenario A | Scenario B | Additional probes |
| --- | --- | --- | --- |
| Login/authorization | Invalid password | Data encoder opens admin-only users/backups | Inactive session and logout |
| USB scanning | Invalid token leaves stage unchanged and input focused | Duplicate/rapid Enter creates one active item | Overlong input, network failure, scan while stage busy |
| Manual fallback | Query shorter than two characters cannot submit | Select ineligible/already queued graduate | No results, server interruption |
| Queue/presentation | Present with empty queue is disabled | Double click/parallel present commands cannot present two | Selected item stability during a new scan |
| Completion | Complete while idle is unavailable/rejected | Double Complete-and-present-next with one version advances once | Empty-tail completion clears safely |
| Correction | Cancel dialog changes nothing | Confirm returns current graduate without completion and shows holding | Refresh from holding then present correct row |
| Skip/return | Skip races presentation legally | Duplicate return appends once | Rescan skipped item is rejected |
| Replay/audio | Missing/unapproved audio disables or rejects Replay | Replay followed by completion never resurrects presentation | Repeated Replay does not overlap |
| Assisted automatic | Paused scan waits | Resume does not consume backlog | Two rapid idle scans present at most one |
| Realtime recovery | Stop Reverb, issue HTTP command, restart and reconcile | Refresh audience/control while a graduate is current | Duplicate/stale event resistance via version checks |
| Administration writes | Validation failure preserves form | Duplicate identity/sequence is rejected | Pending submit double click |
| Destructive operations | Reset while presenting is blocked | Protected ceremony/graduate deletion is blocked | Backup failure remains persistent |

## Required visual states and viewports

- Scanner: ready, processing/disabled, accepted, invalid, duplicate, manual lookup expanded, disconnected; 1366x768, 1536x864, 1920x1080.
- Stage control: loading, empty, waiting, selected, presenting, skipped, holding, confirmation, command error, reconnected; 1366x768, 1536x864, 1920x1080.
- Stage display: idle, presenting short name, presenting long name, missing media, holding, syncing/recovering; 1366x768, 1536x864, 1920x1080.
- Administration: overview, dense student list, forms with errors, no-results, command palette; 1366x768 and 1536x864, with 1920x1080 spot checks.
