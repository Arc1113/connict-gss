# UI Changelog

UI changes are recorded by project phase. Functional backend changes belong in
the main project documentation.

## Design.md desktop redesign — PWA and final consistency

Status: completed
Date: 2026-08-01

Completed:

- Updated the install manifest, browser theme, Inertia progress bar, and PWA
  notices to the shared institutional palette and bumped the static cache to
  `gss-static-v2`.
- Preserved the safety boundary: authenticated pages and mutations are never
  cached or queued; offline navigation receives the local stop-work page.
- Added graceful service-worker registration failure handling and retained the
  maintenance-window update prompt with post-activation reload.
- Kept the persistent collapsible desktop sidebar through 1024px and moved the
  drawer breakpoint below 896px; standalone mode retains internal navigation,
  status, safe-area spacing, and application identity.
- Standardized primary, secondary, danger, disabled, pressed, empty, focus, and
  reduced-motion states across all migrated pages.
- Corrected the sidebar toggle’s accessible Expand/Collapse name and added safe
  external-link relationship metadata.

Verification:

- ESLint passed and the production Vite bundle completed successfully.
- PWA configuration tests passed: 3 tests, 16 assertions.
- Pint passed, the service worker passed JavaScript syntax validation, the
  manifest parsed successfully, required 192/512/maskable icons were verified,
  and whitespace validation passed.
- Production source inspection found no remote HTTP assets; all interface assets
  remain local. The only URL-like CSS asset is an inline SVG data URI.

Environment-limited checks:

- The current managed sandbox cannot access the Docker socket, and host PHP does
  not have `pdo_pgsql`; therefore the full PostgreSQL suite and compiled-app
  Chromium rehearsal could not be rerun in this final pass.
- The unchanged scanner/stage backend suite and physical USB scanner,
  microphone, projector, venue audio, LAN reconnect, and reboot rehearsal still
  require the normal Compose/hardware verification environment.

## Design.md desktop redesign — Monitoring and system administration

Status: completed
Date: 2026-08-01

Completed:

- Standardized Scan Logs, Stage Events, and Audit Logs around compact filters,
  `/` search, result totals, sticky tabular evidence, safe event codes, and
  expandable before/after change summaries.
- Preserved filter-aware CSV exports and kept raw QR values and unsafe exception
  details out of operational evidence.
- Reworked Ceremony Reports into dense timing, count, and definition sections;
  operational reset now remains disabled until its phrase matches exactly.
- Added separate backup destination, latest creation, and latest verification
  states; creation and verification commands lock while running to prevent
  duplicate requests.
- Kept browser restore unavailable and distinguished “backup completed” from
  “restore verification passed” throughout the interface.
- Added operator-account totals, `/` search, current-account marking,
  role-specific permission guidance, and explicit confirmation for role or
  account-status changes.

Verification:

- ESLint, production Vite build, and whitespace validation passed.

Next: PWA, accessibility, responsive behavior, and final regression checks.

## Design.md desktop redesign — Ceremonies, import, and QR

Status: completed
Date: 2026-08-01

Completed:

- Added ceremony result context and `/` search, and made entry into or out of
  the live ceremony status require explicit confirmation with Scanner, Stage
  Control, and Audience Stage consequences stated in place.
- Rebuilt the audience-design editor around a wide 16:9 projector preview and a
  compact adjacent control panel while preserving ceremony-scoped local assets,
  presets, layouts, colors, and portrait policy.
- Standardized CSV import as a visible four-step upload, mapping, preview, and
  summary workflow with dense source context, semantic row findings, atomic
  acknowledgement, and clear recovery routes.
- Kept bulk media explicit: CSV filename columns reference files in local
  `photos/` and `audio/` folders inside one optional ZIP package; imported audio
  remains unapproved until review.
- Rebuilt QR preparation around a compact print checklist, sticky current-page
  batch selection, selected-row state, physical card preview, print blockers,
  testing guidance, and guarded token replacement.
- Corrected misleading selection copy: batch selection applies to the current
  result page and resets when filters or pagination change.

Verification:

- ESLint, production Vite build, and whitespace validation passed after each
  page group.

Next: Monitoring, reports, backups, and operator accounts.

## Design.md desktop redesign — Graduates and audio

Status: completed
Date: 2026-08-01

Completed:

- Kept name/student-number search, ceremony, eligibility, and media readiness
  visible while moving college, program, stage status, and sort into a native
  keyboard-accessible advanced-filter section.
- Added a result-count toolbar, denser sticky-header table treatment, compact
  media lines, visible row hover/focus behavior, and preserved `/` search focus.
- Standardized create/edit fieldsets, validation, media inputs, sticky save
  actions, and photo/audio panes with the shared spacing and surface tokens.
- Clarified the microphone workflow as record, preview, save, review, and
  approve; recording still creates a local file and uses the existing secure
  upload and approval path.
- Reworked graduate details into a compact identity/data workspace with a stable
  presentation-media rail, labeled metadata, review status, and protected
  destructive actions.
- Preserved server QR generation, upload validation, audio approval rules,
  local-only media storage, and stage-playback policy.

Verification:

- ESLint, production Vite build, and whitespace validation passed.
- Microphone permission, physical input quality, and venue playback remain
  hardware checks in the final rehearsal.

Next: Ceremonies, CSV import, and QR preparation.

## Design.md desktop redesign — Overview and readiness

Status: completed
Date: 2026-08-01

Completed:

- Replaced the role dashboard’s dark promotional-style action block with a
  restrained primary-workspace row that includes the practical purpose of the
  destination.
- Kept ceremony readiness first, followed by the authorized next action and a
  compact system list with explicit external-window indicators.
- Reframed the administrator overview around readiness exceptions, active
  ceremony identity, a dense twelve-value status matrix, and direct task rows.
- Highlighted non-zero eligibility, audio-review, missing-audio, rejected-audio,
  and missing-photo states while retaining explicit text labels and values.
- Converted operational destinations into a two-column row system on wide
  screens and a single keyboard-ordered list on narrow screens, without adding
  dashboard cards or decorative metrics.

Verification:

- ESLint, production Vite build, and whitespace validation passed.

Next: Graduates and announcement audio.

## Design.md desktop redesign — Audience Stage

Status: completed
Date: 2026-08-01

Completed:

- Preserved ceremony-scoped background, logo, color, preset, photo, and layout
  choices while removing a hard-coded opaque background layer that could mask a
  configured theme color.
- Replaced the unexplained floating audio button with a clear, one-time display
  setup notice that disappears after the required browser gesture or key press.
- Added projector safe-area spacing and tightened name, program, major, honors,
  portrait, logo, holding, idle, and loading typography across wide and 4:3
  displays.
- Added an explicit photo-presence state so split layouts recover to a centered
  composition when a graduate has no portrait instead of producing a broken
  two-column grid.
- Kept all connection recovery and audio playback behavior behind the audience
  canvas, with no QR values, server errors, queue data, or operator controls
  exposed during normal presentation.
- Applied reduced-motion protection to all audience-stage descendants.

Verification:

- ESLint, production Vite build, and whitespace validation passed.
- Theme persistence, projection viewports, live presentation, and audio replay
  remain included in the final Compose/Chromium regression pass.

Next: Overview.

## Design.md desktop redesign — Stage Control

Status: completed
Date: 2026-08-01

Completed:

- Reframed Stage Control around a sticky current-graduate decision panel and a
  larger scan-order queue instead of giving every supporting region equal
  visual weight.
- Added a visible “Safe next action” lane while keeping replay, holding-screen,
  correction, skip, return, and out-of-order presentation controls distinct.
- Clarified assisted automatic behavior in the interface: an accepted scan can
  present only when the stage is idle, while completion remains operator-led.
- Added a working `#queue` navigation target, selected-row treatment, compact
  sticky table headers, tabular queue numbers, and clearer empty queue space.
- Moved exceptional-action focus to Cancel, added Escape cancellation, and kept
  explicit confirmation before a wrong-graduate correction.
- Standardized the operational header, three-part live status strip, feedback,
  support history, responsive stacking, borders, spacing, and semantic state
  colors with the shared design tokens.

Verification:

- ESLint, production Vite build, and whitespace validation passed after the
  Stage Control interaction and layout changes.
- Compose browser and PostgreSQL test verification remains queued for the final
  pass because the current host sandbox cannot access Docker or `pdo_pgsql`.

Next: Audience Stage.

## Design.md desktop redesign — Scanner

Status: completed
Date: 2026-08-01

Completed:

- Rebuilt the scanner as a two-zone workstation with a dominant scan-and-result
  loop and a stable recent-session rail instead of one oversized form surface.
- Kept PostgreSQL-backed Scan Logs authoritative while showing up to six
  confirmed or rejected attempts temporarily in the current browser session.
- Preserved automatic refocus after every server response and added `/` as an
  explicit focus shortcut when the operator is not typing in another control.
- Added a direct recovery action for rejected or incomplete scans, clearer
  Enter-to-process guidance, locked processing state, and explicit automatic
  presentation versus queued-only status text.
- Reduced header height, standardized status strips and borders, restrained the
  specialized dark header, and applied compact desktop and stacked narrow-screen
  layouts from `design.md`.

Verification:

- ESLint, production Vite build, and whitespace validation passed.
- Scanner workflow tests were attempted outside Compose but could not run
  because the host PHP runtime lacks the PostgreSQL driver; no backend scanner
  code changed. Compose/Chromium regression remains part of final verification.

Next: Stage Control, queue operations, and Audience Stage.

## Design.md desktop redesign — Shared shell

Status: completed
Date: 2026-07-31

Completed:

- Reorganized authorized navigation into Ceremony, Data, Operations, and System
  groups while keeping Scanner and Stage Control one action away.
- Replaced accumulated monochrome/legacy color intent with semantic background,
  surface, text, border, accent, status, focus, spacing, radius, motion, layout,
  and z-index tokens from `design.md`.
- Added a persistent compact status bar for network, live-update, active
  ceremony, and local-time context without claiming unsupported device health.
- Added Alt workspace shortcuts, Ctrl/Cmd shortcut help, command-palette focus
  containment, Escape handling, and focus restoration.
- Removed application-toolbar blur, reduced page-heading scale, standardized
  shell measurements, and retained the accessible small-screen navigation
  drawer.

Verification:

- ESLint, production Vite build, and whitespace validation passed.
- The five-profile Chromium ceremony rehearsal passed, including shell routes,
  command center, mobile drawer, application restart, and Reverb recovery.
- Visually inspected the expanded shell and command center at 1440×900 and the
  drawer at 390×844; the required operational 1024×768 shell behavior remains
  covered by the browser layout assertions.

Next: Scanner.

## Phase 12 — Real-World Operations and UX Remediation

Status: completed
Date: 2026-07-30

Completed:

- Reworked stage-control priority around one operator-confirmed **Complete and
  present next** action while retaining a separate **Complete and clear**
  operation.
- Added an explicit **Correct wrong graduate** recovery that returns the current
  item to its preserved queue position and moves the audience to neutral holding
  before a verified replacement presentation.
- Renamed the visible automatic mode to **Assisted automatic** and explained
  that it presents only an eligible scan to an idle stage; it does not promise
  unattended continuous advancement.
- Added administrator-only local operator-account list, filters, create, and
  edit screens with role/status controls, strong confirmed passwords, field
  errors, and signed-in account protection guidance.
- Corrected administration navigation so technical operators land on authorized
  operational pages and do not receive a readiness link they cannot open.
- Replaced equal-sized readiness/task card grids with compact definitions and a
  vertical task list, and made the administration navigation visibly wrap
  instead of hiding links in horizontal overflow.
- Increased mode and account-status pointer targets, preserved visible focus,
  and kept all correction, queue, account, and technical content off the
  audience stage.
- Extended the Phase 7 browser rehearsal for correction, combined advancement,
  account routes, technical navigation, long content, required viewports, and
  reduced motion.

Architecture intentionally preserved:

- HTTP commands and recovery reads, PostgreSQL authority, post-commit Reverb
  events, private Echo channels, local media/audio, and local-network-only
  runtime.
- No timer-driven or audio-ended advancement, polling, external asset, new UI
  library, or cloud dependency was added.

Verification:

- Full Laravel suite: 188 tests, 1,858 assertions.
- Phase 12 and queue-concurrency focus: 20 tests, 147 assertions.
- Pint: 192 files passed.
- ESLint, Vite production build, Composer validation/audit, npm audit, and
  Compose configuration passed.
- The real compiled application passed the extended Chromium rehearsal at
  stage-control/account viewports 1440×900, 1366×768, and 1024×768 and audience
  viewports 1920×1080 and 1366×768.
- Generated browser PNGs remain local and ignored by Git.

Known limitations:

- Final USB scanner, projected display, three-device network, and venue-audio
  certification still require physical hardware.

## Phase 11 — Full-System Testing and Readiness Sign-Off

Status: completed as CONDITIONAL GO
Date: 2026-07-30

Completed:

- Added browser rehearsal evidence for scanner, stage-control, audience stage,
  and administration routes using realistic long names, missing audio, invalid
  QR, duplicate QR, ineligible graduate, Reverb restart, app restart, and HTTP
  recovery states.
- Fixed an admin topbar horizontal-overflow defect found during the Phase 11
  browser rehearsal by constraining the navigation/account flex layout.
- Added Phase 11 checklist coverage for full-system rehearsal, software versus
  hardware evidence, deployment simulation, and final venue-readiness review.

Known limitations:

- Physical USB scanner, three laptops, projected display, printed QR cards,
  router, and venue audio still require the final human rehearsal before the
  event can be marked GO.

## Phase 10 — Local Deployment and Device Setup

Status: completed
Date: 2026-07-30

Completed:

- Added local deployment, device setup, and network-plan documentation for the
  three-laptop ceremony layout.
- Added deployment scripts for release checks, startup, shutdown, and health
  verification without changing the existing scanner, queue, stage, audio,
  reporting, backup, or recovery interfaces.
- Added deployment-device checklist items for browser profiles, LAN URLs,
  fullscreen stage operation, audio activation, and audience-safety review.

Known limitations:

- Physical three-device, router, projector/LED, USB scanner, and venue audio
  certification still requires the actual event hardware and belongs to the
  final rehearsal/sign-off process.

## Phase 9 — Logs, Reports, Backup, Restore, and Operational Recovery

Status: completed
Date: 2026-07-30

Completed:

- Added operational log navigation and screens for scan attempts and stage
  events with compact filters, pagination, and safe CSV export actions.
- Improved audit history with actor/entity/date filters, safe summaries, IP
  visibility for administrators, and export support.
- Added ceremony completion summary with PostgreSQL-derived counts and metric
  definitions.
- Added backup status page with local-backup metadata and non-destructive
  restore-verification guidance.
- Added administrator-only operational reset form with typed confirmation copy.
- Preserved the restrained academic operations visual direction; no charts,
  fake metrics, cloud services, or UI library were added.

## Phase 8 — Announcement Audio Reliability

Status: completed
Date: 2026-07-30

Completed:

- Added audio-readiness status to graduate administration: uploaded, needs
  review, approved, rejected, and missing.
- Added graduate-detail pronunciation review with local preview, safe metadata,
  review notes, role-aware needs-review/approve/reject controls, and no storage
  path exposure.
- Updated graduate list filters and the admin readiness summary to distinguish
  approved audio from merely uploaded audio.
- Updated stage-control copy so replay is available only for approved readable
  audio and all other states give live-announcer fallback guidance.
- Updated audience stage preloading while keeping audio technical state,
  warnings, controls, and logs off `/stage`.

Verification:

- Phase 8 browser evidence is indexed under `docs/evidence/phase-8/`.
- Screenshots remain ignored by Git under `/docs/evidence/**/*.png`.

Known limitations:

- Duration, LUFS, true peak, noise, clipping, and venue loudness certification
  remain manual checks.
- OGG playback must be checked on the final approved stage browser.
- Physical venue audio and three-laptop rehearsal remain later readiness work.

## Phase 7 — Persistent Queue and Stage Control

Status: completed
Date: 2026-07-30

Completed:

- Reframed `/scanner` around durable queue acceptance, position-specific
  feedback, precise already-waiting/presenting/completed/skipped recovery, and
  automatic-presentation confirmation while preserving USB focus, Enter input,
  manual lookup, and one-request locking.
- Added `/stage-control` with one dominant current-presentation area, a
  deterministic queue table, selected and next presentation, completion,
  skip/return, replay readiness, holding/restore, automatic pause/resume, compact
  skipped/recent context, and textual connection recovery.
- Used the existing restrained navy/gold system, thin borders, moderate radius,
  minimal shadow, local fonts/assets, visible focus, and table-owned overflow.
  At 1024 px the current task stacks before queue detail.
- Extended `/stage` with a neutral formal holding state and local-audio replay
  handling while keeping every queue, operator, connection, and diagnostic
  detail off the audience output.
- Preserved HTTP commands, PostgreSQL source of truth, post-commit Reverb
  broadcasts, Echo delivery, local audio, and HTTP refresh/reconnect recovery.
  No UI library, remote asset, polling path, or internet runtime dependency was
  introduced.

Verification:

- The full PostgreSQL suite passes 148 tests with 1,217 assertions. Pint,
  ESLint, the Vite production build, Composer validation/security audit, npm
  audit, Compose validation, route inspection, and the local health endpoint
  pass.
- Authenticated Chromium used separate scanner, control, and audience profiles
  for controlled ordering, scan-without-preemption, next/selected presentation,
  completion, skip/return, replay, holding/restore, missing audio, automatic
  initial presentation, persisted pause/resume, role denial, focus, reduced
  motion, refresh, and deliberate Reverb outage/recovery.
- `/stage-control` was inspected at 1440×900, 1366×768, and 1024×768; `/stage`
  was inspected at 1920×1080 and 1366×768. Long names/programs, hierarchy,
  wrapping, overflow, audience safety, and compact stacking were visually
  reviewed.
- PNG evidence under `docs/evidence/phase-7/` is intentionally ignored by Git.

Known limitations:

- Physical USB scanner, projector/LED, venue audio, final kiosk startup, and
  three-laptop rehearsal remain manual venue checks.
- Audio recording, normalization, approval, and bulk preparation remain Phase 8.
- Phase 7 intentionally does not add arbitrary drag reorder, bulk queue edits,
  or mode switching during an active ceremony.

## Phase 6 — QR Generation, Printing, and Scanner Hardening

Status: completed
Date: 2026-07-30

Completed:

- Added a protected QR preparation workspace with one dominant task, compact
  ceremony/readiness filters, paginated graduate rows, text-labeled readiness,
  bounded batch selection, and explicit print guidance.
- Added individual QR review and A4 print output plus a two-column A4 batch
  layout. Long approved names/programs wrap without reducing the physical QR
  size, and regenerated cards carry an operational reprint warning.
- Kept raw tokens out of React props and visible page content. SVG output is
  local, protected, no-store, and generated only when an authorized user opens
  an image or print route.
- Added administrator-only replacement acknowledgment and history without
  exposing either old or new token. Data encoders retain preparation/printing
  access but cannot replace tokens or view replacement history.
- Extended `/scanner` with a secondary controlled lookup for unreadable paper
  cards. The primary focused USB/Enter field remains dominant; the fallback uses
  a bounded safe result set, verification step, the existing submission lock,
  and the existing scan transaction.
- Preserved the formal audience stage, Reverb/Echo delivery, HTTP recovery,
  local audio, administration, CSV workflow, and phase boundaries. No external
  asset, UI library, polling path, decorative dashboard, or internet runtime
  dependency was added.

Verification:

- The full PostgreSQL suite passes 124 tests with 1,028 assertions. Focused
  Phase 3, Phase 4, Phase 5, and Phase 6 runs pass 22/157, 23/251, 22/286, and
  16/160 respectively.
- Pint, ESLint, the Vite production build, Composer validation/platform/audit,
  npm audit, and Compose validation pass.
- Authenticated Chromium exercised the QR workspace, long individual output,
  batch output, regeneration warning, old/replacement scans, one-request
  repeated Enter, controlled lookup/acceptance, a genuinely imported graduate,
  data-encoder restrictions, focus recovery, reduced motion, same-origin
  resources, and print media.
- The QR workspace was inspected at 1440×900, 1366×768, and 1024×768; individual
  review at 1440×900 and 1024×768; scanner at 1440×900 and 1024×768; print
  preview at 1366×768 and A4 portrait print media.
- Chromium produced valid one-page A4 individual and batch PDFs. An independent
  temporary OpenCV check decoded the locally rendered 52-character payload and
  matched the expected token exactly. Visual evidence and the exact
  state/viewport index are under `docs/evidence/phase-6/`.

Known limitations:

- No physical USB 2D scanner was attached and configured printers were offline.
  Paper scale, grayscale, decode distance, trigger/suffix timing, device beep,
  projector/audio, and three-laptop venue rehearsal remain manual checks.
- Printer-specific unprintable margins and final approval of the student number
  on staff-held cards require institutional review.
- General queue controls and automatic/controlled presentation modes remain
  Phase 7 work.

## Phase 5 — CSV Import and Data Preparation

Status: completed
Date: 2026-07-29

Completed:

- Added the `/admin/students/import` upload, mapping, server-preview,
  confirmation, and summary workspace using the existing administration shell
  and local design tokens.
- Chose separate step pages over a single long wizard or compressed split
  workspace so every screen has one dominant decision at the 1024 px minimum.
- Added semantic progress, explicit ceremony/file/mode context, deterministic
  mapping samples, required-target errors, visible ignored columns, and 50-row
  preview pagination.
- Added text-labeled ready, warning, and blocked row states; long graduate,
  program, honors, and message wrapping; specific correction guidance; and an
  acknowledgment-gated atomic confirmation.
- Added a compact completion summary, ceremony-filtered graduate recovery link,
  CSV import navigation/task links, and import entries in the safe
  administrator audit filter.
- Kept CSV contents and QR tokens out of Inertia props, template output,
  summaries, and audit details. No external assets, cloud uploads, frontend CSV
  parser, polling, or new UI library were introduced.
- Preserved the Phase 3 scanner/stage pages and the Phase 4 ceremony, graduate,
  and local-media interfaces.

Verification:

- Phase 5 Laravel/unit coverage passes 22 tests with 286 assertions for
  authorization, owner isolation, parsing/limits, mapping, normalization,
  conflict states, pagination, expiry, encrypted storage, atomic rollback,
  internal QR custody, template privacy, and safe audits.
- The full PostgreSQL repository suite passes 107 tests with 860 assertions,
  including existing Phase 3 concurrency/recovery and Phase 4 administration
  coverage.
- ESLint and the Vite production build pass during implementation.
- Authenticated Chrome exercised upload, mapping, warning preview, blocked
  preview, confirmation, summary, graduate retrieval, safe audit, template
  download, reduced motion, visible focus, and denied scanner access at
  1440×900, 1366×768, and 1024×768. Evidence is indexed under
  `docs/evidence/phase-5/`.
- The existing live Phase 3 browser regression passes private Reverb delivery,
  HTTP refresh/reconnect recovery, persistence through a Reverb outage, local
  audio playback, rapid-input protection, and audience-safe output after the
  Phase 5 changes.
- The existing Phase 4 administration browser regression also passes local
  media delivery/playback, audit privacy, role navigation, and the three
  required laptop viewports with the extended navigation shell.

Known limitations:

- Physical assistive-technology checks, 200% zoom, very large venue data,
  operator rehearsal, and production LAN throughput require manual review.
- CSV update/synchronization/export, QR artifact generation/printing, queue
  controls, reports, and settings remain assigned to future phases.
- Temporary import completion-summary retention and any later update-mode merge
  rules still require institutional approval.

## Phase 4 — Ceremony and Graduate Administration

Status: completed
Date: 2026-07-28

Completed:

- Added a restrained administration shell with role-aware navigation, current
  account context, local flash feedback, visible keyboard focus, and no
  internet-hosted assets.
- Added `/admin` as a task/readiness page using real ceremony and graduate facts,
  without decorative metrics or stage controls.
- Added ceremony list, create, detail, and edit interfaces with name/status/year
  filters, pagination, explicit lifecycle guidance, activation-blocked
  validation, and visible deletion protection.
- Added graduate list, create, detail, and edit interfaces with realistic long
  content, search, ceremony/college/program/eligibility/media/stage filters,
  sequence/name/student-number sorting, pagination, missing-media states, and
  no raw QR value in list/detail/edit props.
- Added locally stored photo/audio preparation with preview, replacement,
  removal, actual-type/size guidance, and single-audio playback coordination.
- Added administrator-only administrative audit history with expandable safe
  changes; operational logs/reports remain deferred.
- Preserved the scanner/stage visual and real-time implementations and kept
  administration responsive at the approved laptop viewports.

Verification:

- Laravel: 85 tests passed with 574 assertions. Phase 4 PostgreSQL coverage
  includes authorization, CRUD, validation, activation concurrency and
  completion, protected deletion, awkward and oversized uploads, rollback
  cleanup, audit redaction, and stage-media compatibility.
- ESLint and the Vite production build pass.
- Authenticated Chromium checks passed for `/admin`, ceremony and graduate
  lists/forms/details, audit history, local media playback, empty and validation
  states, and data-encoder restrictions at 1440×900, 1366×768, and 1024×768.
  Evidence is indexed under `docs/evidence/phase-4/`.

Known limitations:

- Venue hardware, assistive-technology certification, final photo/audio
  production standards, protected production media delivery, and physical
  three-laptop rehearsal remain explicitly deferred.
- Phase 4 provides data preparation only; CSV import, QR generation/printing,
  general queue controls, operational reports, and settings are not present.

## Phase 3A — Updated Plan Reconciliation and UI/UX Remediation

Status: completed  
Date: 2026-07-28

Completed:

- Reconciled the updated UI/UX playbook with the completed Phase 3 repository
  in `docs/ui/PHASE_3A_UI_UX_RECONCILIATION.md`.
- Added the project-local UI design system, UX/content/accessibility/responsive
  rules, screen specifications, component guidance, testing/review checklists,
  agent guide, and three validated UI workflow skills.
- Reframed `/scanner` as a focused operational console with separate scanner,
  HTTP server, and live-update status; explicit actionable scan outcomes;
  synchronous rapid-submit locking; missing/long data handling; accurate paused
  state; and reliable focus/keyboard behavior.
- Refined `/stage` to a formal, high-contrast audience display with a genuine
  initial loading state, bounded name/program typography, quiet optional
  content, invisible reconnect recovery, and no visible operator or technical
  controls.
- Preserved `POST /scans`, transaction/scan logging rules, stage-state
  persistence, post-commit broadcast behavior, the private Echo channel, local
  audio, rejection rules, and HTTP recovery.
- Replaced short development-only sample identities with the approved realistic
  names/programs while retaining existing tokens, audio paths, and business
  rules.
- Extended the existing browser harness to cover native Enter submission,
  focus restoration, one-request rapid input, realistic content, failure
  states, Reverb outage/recovery, HTTP recovery, missing media/data, reduced
  motion, overflow assertions, and exact required viewports.

Verification:

- Laravel: 61 tests passed, 317 assertions, including Phase 3 concurrency and
  recovery coverage.
- ESLint, Vite production build, Pint, Composer validation, Compose validation,
  and all three project-skill validators passed.
- The authenticated `/scanner` route was inspected at 1440×900, 1366×768, and
  1024×768.
- The authenticated `/stage` route was inspected at 1920×1080 and 1366×768.
- Browser evidence and an index are under `docs/evidence/phase-3a/`.

Known limitations:

- The browser verified local audio `play`, `playing`, and `ended` events, but
  venue speakers and projector output require physical rehearsal.
- Missing-photo behavior was verified; no institution-approved local graduate
  photo was available for a photo-present visual check.
- Full assistive-technology, 200% zoom, physical USB scanner, and venue
  certification remain part of later accessibility/rehearsal phases.

## Phase 3 — Critical Vertical Slice

- Added the first functional scanner and audience stage.
- Added focused keyboard-wedge/manual QR input and operator feedback.
- Added live private-channel presentation, local audio, idle/clear behavior, and
  HTTP refresh/reconnect recovery.
- Verified the vertical slice in authenticated browser sessions.
