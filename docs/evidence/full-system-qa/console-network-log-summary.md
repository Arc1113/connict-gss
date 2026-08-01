# Console, network, and application-log evidence

Captured during the isolated full-system QA session on 2026-08-01 (Asia/Manila). Binary traces, screenshots, and videos are retained locally beside this file and intentionally excluded from Git and Docker build contexts.

## Browser telemetry

- 105 console/network telemetry entries were reviewed across persistent administrator, data-encoder, scanner, stage-control, stage-display, and guest contexts.
- No uncaught browser `pageerror` or JavaScript exception remained in final verification. A provisional scanner focus callback exception was caught during the first remediation probe, fixed before sign-off, and did not recur.
- Expected negative-path HTTP responses were present: five role-boundary `403` responses, scanner `422` invalid/ineligible responses, scanner `409` duplicate responses, and deliberately failed requests while the scanner context was offline.
- WebSocket connection failures were present only during the deliberate Reverb interruption and startup/restart windows.
- One `404` was caused by the auditor navigating to the incorrect `/admin/qr-codes` path; the actual application route `/admin/students/qr-codes` worked.
- One early administrator `500` occurred while the isolated QA database credentials were being corrected before baseline execution. It was not reproduced after the QA environment was rebuilt.

## Reverb and HTTP recovery evidence

- During the deliberate Reverb outage, scanner, stage-control, and stage-display clients changed from connected/Live to disconnected/Syncing.
- HTTP stage commands still committed to PostgreSQL and returned `broadcast_delivered: false`; the audience did not invent a state it had not received.
- Baseline: restarting Reverb did not reconnect every already-open client within the approximately 40-second observation window; refresh restored them.
- Remediation: the final scanner, stage-control, and stage-display clients all reached `unavailable`, then reconnected without navigation or refresh in 10.1 seconds after Reverb was recreated. Their in-document UUIDs remained unchanged.
- The stage-control Pause request returned HTTP 200 during the outage. Reconnection reconciled and displayed the persisted paused state in all relevant clients.
- A second restart kept the scanner browser context offline while control/audience recovered. Restoring browser network reconnected the same scanner document in 2.3 seconds.
- WebSocket errors during those deliberate outage windows were expected and stopped after recovery. Finding `M-01` is closed.

## Laravel and service logs

- Queue `jobs`-table errors occurred only inside the short `migrate:fresh` windows used to rebuild the dedicated QA database while the queue worker was running. The worker recovered when migrations completed.
- `testing.ERROR` entries for an injected import failure and deliberate database interruption came from regression tests whose purpose is to prove rollback and safe failure behavior.
- Early database-authentication errors belong to the discarded QA bootstrap attempt before the dedicated credentials were corrected.
- The baseline backup UI produced a persistent, safe failure record. Diagnostic logging then identified a missing PostgreSQL client/Docker-in-container dependency and, after that was removed, a relative child-process output path. Final browser evidence shows a completed dump and passed disposable restore verification.

## Trace map

| Context | Trace | Primary coverage |
| --- | --- | --- |
| Administrator | `traces/admin-full-qa.zip` | Administration pages, forms, import, QR, logs, users, reports, backup baseline |
| Data encoder | `traces/encoder-full-qa.zip` | Allowed preparation routes and denied system/operations routes |
| Scanner | `traces/scanner-full-qa.zip` | USB keyboard scans, invalid/duplicate/rapid/offline states, focus and camera fallback |
| Stage control | `traces/control-full-qa.zip` | Queue commands, confirmation, holding, correction, automatic pause/resume, outage |
| Stage display | `traces/stage-full-qa.zip` | Idle/presenting/holding/long-name/syncing display states |
| Guest/public | `traces/guest-full-qa.zip` | Login failures, phone onboarding, local certificate response |

Open a trace with `npx playwright show-trace docs/evidence/full-system-qa/traces/<name>.zip`.
