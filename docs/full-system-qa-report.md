# Graduation Stage System full-system QA report

Date: 2026-08-01 (Asia/Manila)  
Application: Laravel 13, React 19, Inertia, Vite, PostgreSQL 16, Reverb/Echo  
QA target: `http://127.0.0.1:8002`  
QA database: `graduation_stage_system_full_qa_20260801`

## Executive result

The critical browser gate is green after remediation: administrator/data-encoder authorization, USB-style scanning, queue control, audience presentation, HTTP persistence, real-time propagation, refresh recovery, keyboard operation, supported desktop viewports, accessibility checks, local backup creation, and disposable restore verification all have direct browser evidence.

This is not an unconditional ceremony-readiness sign-off. No Critical, High, Medium, or Low software defects found in this audit remain open: all five High findings, the Medium Reverb recovery finding, the four original Low UX/layout findings, and one additional Low dashboard-semantics finding were fixed and reverified individually. Physical scanner, camera, audio, projector, printer, venue LAN, and installed-PWA checks still require an on-site hardware rehearsal.

| Severity | Found | Fixed | Open |
| --- | ---: | ---: | ---: |
| Critical | 0 | 0 | 0 |
| High | 5 | 5 | 0 |
| Medium | 1 | 1 | 0 |
| Low | 5 | 5 | 0 |

## Scope and preparation

The audit read `AGENTS.md`, `design.md`, `docs/requirements.md`, `docs/architecture.md`, `docs/event-readiness-report.md`, `docs/known-issues.md`, the existing PHPUnit/browser-support tests, and the Phase 11 rehearsal. The requested root `plan.md` does not exist in this repository and could not be reviewed.

An isolated runtime copy and dedicated database were used. The pre-existing deployment stack and its data were not mutated. The QA stack ran Laravel on port 8002, Vite on 5174, Reverb on 8082, PostgreSQL 16, and a queue worker. QA users and seed graduates were rebuilt with `migrate:fresh --seed` only against the database named above.

The pre-execution page/control/state matrix is in the [QA coverage inventory](evidence/full-system-qa/qa-coverage-inventory.md). It includes every discovered page, meaningful control, expected result, and at least two off-happy-path scenarios for each critical flow. A second [remaining-UX remediation inventory](evidence/full-system-qa/remaining-ux-remediation-inventory.md) was prepared before the follow-up fixes and records their direct execution results.

The requested Playwright interactive skill was used. Its native `js_repl` facility was not exposed in this environment, so the same workflow was operated through persistent Node Playwright REPL sessions instead of reducing the audit to isolated scripts.

## Browser execution results

| Area | Direct browser result |
| --- | --- |
| Authentication and roles | Valid administrator and data-encoder sign-in worked. Invalid login remained on the form with an associated error, cleared the password, and returned focus to the email field after both button and Enter submission, including repeated identical errors. The encoder could access graduates/import/QR preparation and received server-side `403` responses for users, backups, audit logs, scanner, and stage control. |
| Administration navigation | Dashboard, admin overview, ceremonies, graduates, import, QR preparation, scan logs, stage events, audit logs, ceremony summary, backups, users, and device connections were opened and operated. Ctrl+K palette search, Escape, filters, clear actions, pagination states, and no-result states were exercised. The complete administrator navigation—including **Connect devices**—fit above the footer without scrolling at both 1366x768 and 1536x864. |
| Forms and protected actions | Required/duplicate validation, graduate creation, audio approval, user create/edit, blank-password preservation, self-demotion protection, protected graduate/ceremony deletion, operational reset, and QR regeneration were exercised. |
| CSV import | File-disabled, invalid mapping, valid preview, acknowledgement, atomic confirmation, and success-summary states were exercised with dedicated QA CSV data. |
| QR and exports | Five-card batch print, individual print, protected SVG behavior, token regeneration, four CSV exports, formula-safe headers, and absence of raw QR tokens were checked. |
| USB scanner | The hidden/token input began focused and regained focus after success, invalid, duplicate, empty Enter, and offline failure. Keyboard events simulated USB input. Valid, invalid, ineligible, duplicate, skipped, and rapid triple-Enter cases were exercised; rapid input emitted one scan POST. |
| Camera fallback | Permission denial was recoverable. Switching back to USB released active media tracks and restored scanner focus. A successful camera decode was not run because no fake/physical camera was available in this session. |
| Stage queue | Empty queue, controlled Present, selected out-of-order Present, Skip, Return, correction, Complete-and-present-next, replay, missing-audio replay-disabled, Holding, automatic first-idle scan, pause/resume, and backlog behavior were exercised. |
| Double actions and atomicity | Replay double-click emitted one POST. Complete-and-present-next double-click emitted one POST and changed the audience directly from one graduate to the next, without an intermediate idle flash. |
| Persistence | Scanner and stage mutations were issued over HTTP. Refreshing scanner, control, and audience contexts restored PostgreSQL-authoritative queue/current/holding state. |
| Realtime | Separate scanner, control, and audience contexts received committed Reverb/Echo changes. During a Reverb outage, an HTTP pause returned 200 and persisted; all three already-open clients then reconnected and reconciled without refresh. A second restart held scanner network offline and recovered that same document 2.3 seconds after network returned. |
| Backup and restore | The administrator created a local PostgreSQL/media backup in the browser, saw `completed`/`passed`, then ran disposable restore verification and saw the success result. The disposable database was dropped; the primary QA database was not restored over. |
| Accessibility | `@axe-core/playwright` ran on login, administrator, import/data-encoder, scanner, stage control, and stage display. The final result was zero violations on the checked critical screens. |
| Viewports | Scanner, stage control, and audience were checked at 1366x768, 1536x864, and 1920x1080. The open fallback tray did not increase scanner document height, automatic state/action text did not clip, critical controls remained visible, and the audience had no document scroll. |

## Functional bugs

### Critical

None found.

### High — fixed and reverified

#### H-01: Stage correction confirmation leaked keyboard focus

- Reproduction: focus **Correct wrong graduate**, open the alert dialog, press Tab/Shift+Tab repeatedly, then Escape.
- Expected: focus stays within the dialog; Escape closes it and returns focus to the invoking control; the action label describes the exact command.
- Baseline actual: focus could escape into the page, closure did not restore the trigger, and the generic confirmation copy was ambiguous.
- Fix: added an alert-dialog focus trap, Escape handling, trigger restoration, `aria-modal`, and action-specific headings/buttons.
- Reverification: focus order cycled `Cancel → Return graduate to queue → Cancel`; Escape restored **Correct wrong graduate**.
- Evidence: [baseline dialog](evidence/full-system-qa/screenshots/baseline-correction-dialog-1536x864.png), [fixed dialog](evidence/full-system-qa/screenshots/fixed-stage-control-focus-dialog.png.png), stage-control trace.

#### H-02: Assisted-automatic Pause/Resume was below the 1366x768 fold

- Reproduction: set the ceremony to assisted automatic, open stage control at 1366x768, and inspect the first viewport.
- Expected: Pause/Resume is immediately visible with other critical state controls.
- Baseline actual: the control appeared below the fold, requiring scrolling during live operation.
- Fix: moved assisted-automatic state/action into the primary status strip and adjusted the compact layout.
- Reverification: Pause/Resume began near y=120; the lowest critical action edge was approximately y=680 at 1366x768.
- Evidence: [baseline dense control](evidence/full-system-qa/screenshots/baseline-stage-control-dense-1536x864.png), [fixed 1366 control](evidence/full-system-qa/screenshots/fixed-stage-control-1366x768.png.png).

#### H-03: Systemic muted-text contrast failures

- Reproduction: run axe on administration, import, scanner, and stage control.
- Expected: no serious contrast violation on critical operator text.
- Baseline actual: serious `color-contrast` violations appeared on administration (6 nodes), import (5), scanner (16), and stage control (5).
- Fix: strengthened the shared muted-text token from `#7b8490` to `#5e6670` and rechecked affected surfaces.
- Reverification: axe returned zero violations on the final critical screens.
- Evidence: [fixed admin](evidence/full-system-qa/screenshots/fixed-contrast-admin-summary.png.png), [fixed scanner](evidence/full-system-qa/screenshots/fixed-contrast-scanner.png.png), [fixed control](evidence/full-system-qa/screenshots/fixed-contrast-stage-control.png.png).

#### H-04: Audience display did not expose realtime health

- Reproduction: open `/stage`, then interrupt Reverb while keeping HTTP/application service available.
- Expected: a small, audience-safe indicator differentiates Live from Syncing without exposing technical details or obscuring the graduate.
- Baseline actual: the audience gave no visible indication that realtime data was stale. Axe also reported an inappropriate landmark-role issue on the audio setup element.
- Fix: added a discreet bottom-right Live/Syncing status and corrected the audio setup semantics.
- Reverification: Live was visible at every viewport; Syncing appeared during interruption; long names remained readable; refresh returned Live; final axe result was zero.
- Evidence: [Live](evidence/full-system-qa/screenshots/fixed-stage-live-1366x768.png.png), [Syncing](evidence/full-system-qa/screenshots/fixed-stage-syncing-1366x768.png.png), [recovered](evidence/full-system-qa/screenshots/fixed-stage-reconnected-after-refresh.png.png).

#### H-05: Browser backup and restore verification could not run in the application container

- Reproduction: as administrator, open Backups and click **Create local backup**; in the baseline image, the PHP process tried `docker compose exec` from inside its own container.
- Expected: a PostgreSQL/media backup is written locally, checksummed, recorded as completed, and can be verified through a disposable restore without mutating the primary database.
- Baseline actual: creation persisted a safe failed record because Docker CLI/socket and PostgreSQL client tooling were unavailable. The verification service had the same Docker-in-container dependency. During remediation, HTTP execution also exposed a relative child-process output path that CLI execution did not.
- Fix: package PostgreSQL 16 `pg_dump`, `psql`, `createdb`, and `dropdb` in development/deployment images; execute them with argument arrays and password/SSL values in process environment variables; canonicalize dump paths; restore/count/drop a random disposable database; log bounded diagnostic stderr on failure. Docker build contexts now exclude all binary QA evidence.
- Reverification: CLI dump and disposable restore passed; both development and deployment images built and reported PostgreSQL 16.14 clients; the browser showed `completed` and `passed`, then a successful disposable restore message. The Playwright administrator journey now covers both buttons.
- Evidence: [baseline failure](evidence/full-system-qa/screenshots/baseline-backup-result-1536x864.png), [fixed backup](evidence/full-system-qa/screenshots/fixed-backup-created-and-verified-1536x864.png).

### Medium — fixed and reverified

#### M-01: Already-open Echo clients did not all reconnect promptly after Reverb restart

- Reproduction: open scanner/control/audience, tag each document, stop Reverb, wait for disconnected/Syncing, issue an HTTP command, restart Reverb, and observe without navigation or refresh.
- Expected: all existing clients reconnect and reconcile without operator action.
- Baseline actual: the UI remained safe and HTTP persistence continued, but not every already-open client returned to connected in the approximately 40-second observation window. Refresh reconciled PostgreSQL state.
- Fix: added an Echo recovery policy with a five-second unavailable threshold, capped exponential retry delay, forced transport reinitialization for stalled terminal states, and immediate retry when browser network/visibility returns. Existing stage-control reconciliation runs after connection recovery.
- Reverification: scanner, control, and audience all reached `unavailable`; Pause returned HTTP 200 during the outage; after Reverb restarted, all three original documents reconnected in 10.1 seconds and displayed the persisted paused state. A second restart kept scanner offline, then reconnected it 2.3 seconds after network restoration. All three document UUIDs stayed unchanged in both probes.
- Evidence: [outage scanner](evidence/full-system-qa/screenshots/fixed-reverb-outage-scanner-1366x768.png), [outage control](evidence/full-system-qa/screenshots/fixed-reverb-outage-control-1366x768.png), [outage audience](evidence/full-system-qa/screenshots/fixed-reverb-outage-stage-1366x768.png), [recovered scanner](evidence/full-system-qa/screenshots/fixed-reverb-recovered-scanner-1366x768.png), [recovered control](evidence/full-system-qa/screenshots/fixed-reverb-recovered-control-1366x768.png), [recovered audience](evidence/full-system-qa/screenshots/fixed-reverb-recovered-stage-1366x768.png).

### Low — fixed and reverified

- **L-01 invalid-login focus:** an Inertia error callback now clears the password and restores email focus after every invalid submission, even when consecutive responses contain the same error text. Button and Enter paths passed; axe returned zero. Evidence: [invalid login](evidence/full-system-qa/screenshots/fixed-login-error-focus-1366x768.png).
- **L-02 scanner fallback scrolling:** the controlled lookup is now a bounded desktop tray that opens above its summary with tray-local overflow. Opening it kept document heights exactly equal to 768, 864, and 1080 pixels at the three required viewports; lookup focus and close-to-scanner focus both passed. Evidence: [1366](evidence/full-system-qa/screenshots/fixed-scanner-lookup-1366x768.png), [1536](evidence/full-system-qa/screenshots/fixed-scanner-lookup-1536x864.png), [1920](evidence/full-system-qa/screenshots/fixed-scanner-lookup-1920x1080.png).
- **L-03 assisted-automatic readability:** the automatic cell receives proportionally more strip width, displays explicit `Running — idle scans present` or `Paused — scans wait in queue` copy, and retains a 36px Pause/Resume action. Text and action bounds passed at all three viewports; double-click emitted one Pause POST; paused state survived refresh. Evidence: [running 1366](evidence/full-system-qa/screenshots/fixed-stage-control-automatic-running-1366x768.png), [paused 1366](evidence/full-system-qa/screenshots/fixed-stage-control-automatic-paused-1366x768.png).
- **L-04 administrator sidebar fit:** short-height desktop spacing now preserves every 34px destination target while reducing only inter-group/panel spacing. At 1536x864 the last row ended at y=712 above the y=819 footer; at 1366x768 it also fit without overflow. Evidence: [1366](evidence/full-system-qa/screenshots/fixed-admin-sidebar-1366x768.png), [1536](evidence/full-system-qa/screenshots/fixed-admin-sidebar-1536x864.png).
- **L-05 dashboard list semantics:** follow-up axe testing found invalid `role="listitem"` use on dashboard links. The workspace group now uses native `ul`/`li` semantics; dashboard axe returned zero with no visual layout change.

## UI/UX findings by severity

### High — resolved

- Exceptional stage actions now use specific language, trapped focus, and focus restoration.
- Automatic pause/resume is now co-located with primary stage state rather than below the operational fold.
- Muted operator text now meets the automated contrast check on the audited screens.
- The audience now communicates Live versus Syncing without showing implementation details.
- Backup recovery is now an operable browser workflow rather than a persistent dead end.

### Medium — resolved

- Reverb recovery is automatic, bounded, visible, and reconciles PostgreSQL-authoritative state in already-open clients. A refresh remains an operator fallback, not the required recovery path.

### Low — resolved

- Invalid-login correction is now one keyboard step shorter because focus returns to email and the password clears.
- Controlled lookup opens without shifting the scanner workstation or adding document travel.
- Assisted-automatic state and action are readable at a glance across supported desktop widths.
- Every administrator destination is immediately discoverable without sidebar scrolling at both short-height desktop viewports.

### Positive operational observations

- Scanner focus retention, token clearing, distinct invalid/duplicate feedback, and one-request rapid-scan locking are suitable for HID operation.
- Current graduate, next graduate, queue action, Holding, Replay, and completion controls have clear grouping and pending-disabled behavior.
- The audience remains visually calm: no dialogs, technical error dumps, queues, or raw connection details appear.
- Holding and current presentation survive refresh, and atomic completion avoids a distracting audience flash.

## Accessibility and keyboard evidence

Baseline axe results were: login 0; administration 6 serious contrast nodes; import 5 serious; scanner 16 serious; stage control 5 serious; stage display 1 minor ARIA issue. Follow-up testing also found five invalid dashboard list-role nodes. After the shared contrast, audience semantics, and dashboard native-list fixes, login, administrator dashboard, scanner fallback-open, automatic stage control, and audience each returned zero violations.

Keyboard checks included skip links, Ctrl+K, command search, Escape close, Enter submit, USB token typing, Tab/Shift+Tab dialog wrapping, dialog focus restoration, and scanner focus after every tested result. Automated axe checks are a supplement to—not a substitute for—on-site keyboard and assistive-technology rehearsal.

## Visual evidence

There are 77 locally retained screenshots. Representative evidence:

| Surface/state | Evidence |
| --- | --- |
| Scanner at all required sizes | [1366](evidence/full-system-qa/screenshots/baseline-scanner-1366x768.png), [1536](evidence/full-system-qa/screenshots/baseline-scanner-1536x864.png), [1920](evidence/full-system-qa/screenshots/baseline-scanner-1920x1080.png) |
| Scanner failure/recovery | [invalid](evidence/full-system-qa/screenshots/baseline-scanner-invalid-1366x768.png), [duplicate](evidence/full-system-qa/screenshots/baseline-scanner-duplicate-1366x768.png), [offline](evidence/full-system-qa/screenshots/baseline-scanner-network-offline-1366x768.png), [reconnected](evidence/full-system-qa/screenshots/baseline-scanner-reconnected-1366x768.png) |
| Stage control operations | [empty](evidence/full-system-qa/screenshots/baseline-stage-control-empty-1536x864.png), [presenting](evidence/full-system-qa/screenshots/baseline-stage-control-presenting-1536x864.png), [skip/return](evidence/full-system-qa/screenshots/baseline-stage-control-skip-return-1536x864.png), [holding](evidence/full-system-qa/screenshots/baseline-stage-control-holding-1536x864.png) |
| Audience | [idle](evidence/full-system-qa/screenshots/baseline-stage-idle-1920x1080.png), [presenting](evidence/full-system-qa/screenshots/baseline-stage-presenting-approved-1920x1080.png), [long name 1366](evidence/full-system-qa/screenshots/baseline-stage-long-name-1366x768.png), [holding](evidence/full-system-qa/screenshots/baseline-stage-holding-1920x1080.png) |
| Administration | [command palette](evidence/full-system-qa/screenshots/baseline-command-palette-filtered-1536x864.png), [import mapping](evidence/full-system-qa/screenshots/baseline-import-mapping-1366x768.png), [import preview](evidence/full-system-qa/screenshots/baseline-import-preview-ready-1366x768.png), [import success](evidence/full-system-qa/screenshots/baseline-import-summary-success-1366x768.png), [QR batch print](evidence/full-system-qa/screenshots/admin-qr-batch-print.png) |
| Follow-up UX/layout fixes | [login focus](evidence/full-system-qa/screenshots/fixed-login-error-focus-1366x768.png), [scanner tray](evidence/full-system-qa/screenshots/fixed-scanner-lookup-1366x768.png), [automatic paused](evidence/full-system-qa/screenshots/fixed-stage-control-automatic-1366x768.png), [full sidebar](evidence/full-system-qa/screenshots/fixed-admin-sidebar-1366x768.png) |
| Reverb restart | [outage](evidence/full-system-qa/screenshots/fixed-reverb-outage-control-1366x768.png), [recovered](evidence/full-system-qa/screenshots/fixed-reverb-recovered-control-1366x768.png) |

Binary screenshots, traces, and videos are deliberately ignored by Git and Docker image builds but remain in `docs/evidence/full-system-qa/` in this workspace. The [console/network/log summary](evidence/full-system-qa/console-network-log-summary.md) distinguishes deliberate negative-path failures from product defects and maps each persistent context trace.

## Maintainable browser coverage added

- `tests/Browser/full-system-critical.spec.js` now has five serial journeys covering invalid-login focus, role boundaries, full sidebar fit, audio approval, local backup/disposable verification, scanner focus and fallback height, empty/invalid/valid/duplicate/rapid scans, Skip/Return, Present/Replay/Holding, correction dialog focus, atomic complete-and-next, refresh persistence, realtime updates, assisted-automatic readability/pause locking/persistence, axe, and three desktop viewports.
- `tests/Browser/__screenshots__/stage-idle-1366x768.png` is the only visual baseline. It is limited to the stable, critical idle audience screen; dynamic operator tables were intentionally not snapshotted.
- `playwright.config.js` uses one worker for stateful critical journeys and retains trace, screenshot, and video on failure. `npm run test:browser` is the entry point.
- `tests/Support/fixtures/full-system-qa-import.csv` is dedicated non-production import data.
- `tests/Support/phase11-browser-rehearsal.mjs` now tags scanner/control/audience documents before restarting Reverb and requires a post-restart event to reach the already-open control without navigation or refresh.

## Final verification

| Check | Result |
| --- | --- |
| Playwright critical suite | `5 passed (41.1s)` |
| PHPUnit on PostgreSQL testing database | `207 passed (2043 assertions, 43.56s)` |
| Pint | passed |
| Frontend ESLint | passed |
| Playwright/config ESLint | passed |
| Vite production build | passed; 609 modules transformed |
| Development PHP image | built; PostgreSQL clients 16.14 present |
| Deployment PHP image | built; PostgreSQL clients 16.14 present |

The repository-prescribed Compose app image does not include development-only PHPUnit dependencies. The full suite was therefore run inside the isolated QA PHP container with the repository vendor tree and explicit PostgreSQL testing environment; SQLite was never used.

## Untested or partially tested areas

- Physical USB HID scanner timing, printed/damaged QR cards, and real scanner prefix/suffix configuration: keyboard events were used instead.
- Successful phone-camera decoding in this session: permission denial, USB fallback, focus restoration, and media-track release were tested; no fake/physical camera device was available.
- Real announcement loudness, audio-device switching, auditorium feedback, and simultaneous venue playback.
- Projector/LED scaling, overscan, brightness, color, and long-name readability at audience distance.
- Physical printer output and QR readability: browser print surfaces were inspected only.
- Actual venue LAN/router/firewall, multiple physical laptops, power loss, cold standby, and HTTPS certificate enrollment on a phone.
- Copy-to-clipboard on device onboarding under HTTPS: the isolated QA origin was HTTP, so the secure-context-dependent action was unavailable; Print and the public certificate response were exercised.
- Installed-PWA OS launch, install prompt, update lifecycle, and service-worker upgrade across application versions.
- Destructive restore over a primary database: intentionally not performed. Verification restored only into a random disposable PostgreSQL database and dropped it afterward.
- Prolonged PostgreSQL outage from the browser: targeted backend interruption tests cover rollback; browser interruption focused on offline HTTP and Reverb loss.

## Release recommendation

Proceed to an on-site event rehearsal, not directly to ceremony operation. All software findings from this audit are closed with direct browser evidence, but the exact scanner, phones, audio interface, projector/LED, printer, network, HTTPS certificates, and operator seating still need venue rehearsal. The software critical browser gate is green; the full ceremony system cannot be declared passed until those hardware and venue checks are recorded.
