# UI Changelog

UI changes are recorded by project phase. Functional backend changes belong in
the main project documentation.

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
