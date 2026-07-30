# UI Testing Checklist

Use this checklist for functional frontend verification. Record actual commands
and results in the phase report.

## Before changes

- [ ] Read playbook, requirements, architecture, relevant screen specs.
- [ ] Inspect current implementation and existing evidence.
- [ ] Run relevant backend baseline.
- [ ] Run current lint and build.
- [ ] Preserve unrelated worktree changes.

## Scanner function

- [ ] Authorized route opens; unauthorized roles remain denied.
- [ ] Token input is focused at load.
- [ ] USB-style input plus Enter submits once.
- [ ] Empty input is rejected locally.
- [ ] Processing state locks repeat submission.
- [ ] Input clears and refocuses after response.
- [ ] Valid scan confirms graduate.
- [ ] Invalid, duplicate, completed, ineligible, inactive, and busy results are
      specific and actionable.
- [ ] Network failure does not claim success.
- [ ] Reverb loss does not disable HTTP scanning.
- [ ] Reconnected state is communicated in text.
- [ ] Missing/long graduate information remains safe.
- [ ] Tab reaches navigation and action controls without a focus trap.

## Stage function

- [ ] Initial HTTP loading is neutral.
- [ ] Idle state is correct.
- [ ] Valid event presents the graduate.
- [ ] Refresh restores PostgreSQL state.
- [ ] Stale events do not replace newer state.
- [ ] Version gap/reconnect triggers HTTP reconciliation.
- [ ] Reverb loss preserves a clean last-known display.
- [ ] Clear returns to idle.
- [ ] Audio activates through one-time interaction and does not overlap.
- [ ] Missing audio/photo/honors is safe.
- [ ] No technical or operator information is visible.

## CSV import function

- [ ] Administrator and data encoder can open the workspace; other roles are
      denied.
- [ ] Local template is UTF-8/BOM and contains no QR or media fields.
- [ ] No-file, invalid type/content, limit, and expired states give recovery.
- [ ] Exact mapping suggestions are reviewable; required and duplicate mappings
      receive linked errors.
- [ ] Ignored columns, samples, safe filename, ceremony, mode, and whole-file
      counts remain visible.
- [ ] Ready, warning, and blocked rows use text as well as color.
- [ ] Long names/programs/messages wrap inside table-owned overflow.
- [ ] Confirmation is absent with errors and requires acknowledgment when valid.
- [ ] Rapid/repeat confirmation is locked and server revalidation remains
      authoritative.
- [ ] Success summary and audit expose counts but no source rows or QR values.
- [ ] Upload, mapping, preview, blocked, and summary states are checked at
      1440×900, 1366×768, and 1024×768.

## QR preparation and printing

- [ ] Administrator and data encoder can view and print; other roles are denied.
- [ ] Only an administrator can regenerate a token.
- [ ] Ceremony, college, program, eligibility, readiness, and sequence filters
      preserve their values and select the intended graduates.
- [ ] Empty, duplicate, cross-ceremony, ineligible, missing-token, and over-limit
      selections are rejected server-side with recovery text.
- [ ] Individual and batch SVG images are protected, local, black on opaque
      white, and contain no visible raw token.
- [ ] Individual and batch print documents use A4 physical units, fixed QR
      dimensions, deterministic order, and unsplit cards.
- [ ] Long names/programs wrap without shrinking or overlapping a QR.
- [ ] Regeneration confirmation explains invalidation; stale, presenting,
      completed, unauthorized, failed, and concurrent attempts are safe.
- [ ] Old output fails after regeneration and new output succeeds through the
      unchanged scanner transaction.
- [ ] Audit values contain safe counts/context and no token, payload, SVG, or
      selected-ID list.
- [ ] QR workspace and individual view are checked at 1440×900, 1366×768, and
      1024×768; individual and batch documents are checked through Chromium
      print rendering.

## Phase 6 scanner hardening

- [ ] Non-interactive pointer use restores QR focus without stealing focus from
      links, buttons, or manual lookup.
- [ ] Rapid characters plus Enter submit once; duplicate Enter and manual double
      submission do not create a second request.
- [ ] Manual lookup is bounded to the active ceremony and returns minimal data.
- [ ] A lookup selection uses the same scan statuses and transaction.
- [ ] Token/manual-query input clears after processing and tokens never appear in
      URLs, console output, or rendered feedback.
- [ ] USB HID mode, keyboard layout, Enter suffix, enabled 2D symbology, beep,
      prefix, timeout, and printed-output checks are documented.

## Phase 7 queue and stage control

- [ ] Controlled scans append one durable waiting row and never preempt.
- [ ] Assisted automatic mode presents only the first idle/unpaused scan with no earlier
      waiting row; pause is persisted and resume does not consume backlog.
- [ ] Present next and selected use the intended row; completion either clears
      intentionally or advances once through the explicit combined command.
- [ ] Skip preserves history, return appends to the tail, and repeated commands
      produce a safe conflict rather than duplicate state.
- [ ] Replay stops/restarts approved local audio without changing queue status;
      missing audio disables replay with operator guidance.
- [ ] Holding hides the current graduate from the audience and restore recovers
      the same persisted presentation.
- [ ] Pending controls cannot double-submit; risky actions confirm; failures
      instruct refresh/recovery.
- [ ] Queue/control HTTP responses and audience content contain no QR token,
      student number, credentials, debug text, or technical logs.
- [ ] Refresh, version gap, Reverb stop/restart, and reconnect recover both
      queue and audience state from HTTP.
- [ ] Scanner, control, and stage remain keyboard-operable and do not overflow
      with realistic long names/programs.
- [ ] `/stage-control` is checked at 1440×900, 1366×768, and 1024×768;
      `/stage` is checked at 1920×1080 and 1366×768.

## Phase 8 announcement audio

- [ ] Data encoder can upload or replace MP3/WAV/OGG audio and preview it.
- [ ] Upload creates `uploaded` or review-required state, not approval.
- [ ] Replacement invalidates previous approval.
- [ ] Data encoder can mark audio needs review but cannot approve/reject.
- [ ] Administrator can approve readable local audio with notes.
- [ ] Administrator can reject audio with a reason.
- [ ] Stage control shows approved, needs-review, rejected, and missing audio in
      plain text.
- [ ] Replay is enabled only for approved current audio.
- [ ] Audience stage receives no technical audio status and no unapproved audio
      URL.
- [ ] Current approved audio preloads; next approved audio preloads where HTTP
      state provides it.
- [ ] Refresh and Reverb reconnect do not replay stale audio.
- [ ] Keyboard focus reaches preview controls, notes, approve/reject, and
      replacement controls.
- [ ] Admin and stage-control routes are checked at 1440×900, 1366×768, and
      1024×768; stage is checked at 1920×1080 and 1366×768.

## Phase 9 operational support

- [ ] Scan logs show filtered safe fields and no raw QR token/hash.
- [ ] Stage events show safe descriptions and no raw event JSON.
- [ ] Audit logs show safe old/new summaries and redact paths/secrets.
- [ ] CSV exports preserve Unicode and neutralize formula-leading cells.
- [ ] Ceremony summary counts match seeded PostgreSQL data.
- [ ] Backup page shows status/manifest metadata without secrets or full paths.
- [ ] Operational reset requires typed confirmation, blocks presenting, and
      preserves history.
- [ ] Technical, stage, encoder, scanner, inactive, and guest access rules match
      Phase 9 requirements.

## Phase 10 deployment and devices

- [ ] Runtime browser checks use compiled production assets, not the Vite dev
      server.
- [ ] Scanner, stage-control, and stage URLs use the server LAN address.
- [ ] Scanner browser profile is separate from admin/stage profiles.
- [ ] Stage browser is fullscreen, zoom 100%, notifications disabled, and audio
      activation is performed.
- [ ] Stage-control is not opened on the projected audience display.
- [ ] Reverb outage/restart recovers through HTTP state refresh.
- [ ] Application restart recovers scanner, stage-control, and stage after
      browser refresh.
- [ ] No audience-facing route exposes terminal output, admin navigation, QR
      tokens, server paths, or raw connection diagnostics.
- [ ] Three physical devices, router, projector/LED, USB scanner, and venue
      audio are checked before event certification.

## Phase 11 full-system rehearsal

- [ ] Browser rehearsal uses separate authenticated profiles for administrator,
      encoder, scanner, stage-control, and audience stage.
- [ ] Scanner, stage-control, and stage use realistic long names, long programs,
      missing honors, missing audio, duplicate QR, invalid QR, and ineligible
      graduate states.
- [ ] Reverb restart and application restart recover through HTTP state
      retrieval.
- [ ] PostgreSQL interruption produces safe failures and recovery is verified
      before scanning resumes.
- [ ] Administration routes have no horizontal page overflow in the rehearsal
      viewport.
- [ ] Export checks confirm QR tokens and storage paths are not exposed.
- [ ] Browser resource checks show no remote runtime assets.
- [ ] Physical USB scanner, three laptops, projector/LED, and venue audio are
      marked separately as hardware/venue evidence, not software evidence.

## Phase 12 real-world operations

- [ ] Assisted automatic is named accurately on ceremony forms and stage control.
- [ ] A current graduate plus waiting queue shows **Complete and present next**
      as the dominant action and **Complete and clear** as a separate choice.
- [ ] The combined action completes exactly one current item and presents
      exactly one earliest waiting item without an audience clear flash.
- [ ] Two commands with the same expected version produce one success and one
      safe stale-state response.
- [ ] **Correct wrong graduate** requires confirmation, preserves the queue
      position, shows neutral holding, and permits an explicit correct
      presentation.
- [ ] No correction path marks the wrong graduate completed or deletes history.
- [ ] Administrator account list filters work; create/edit validation is
      field-linked; blank edit password preserves the hash.
- [ ] Password data is absent from Inertia and audit payloads.
- [ ] An administrator cannot demote or deactivate their own signed-in account.
- [ ] Technical-operator navigation has no link to an unauthorized readiness
      page and all visible links open successfully.
- [ ] Navigation wraps visibly without page-level horizontal overflow.
- [ ] Mode choices and account status controls provide at least a 44×44 px
      pointer target, visible focus, and text labels.
- [ ] `/stage-control` and `/admin/users` are checked at 1440×900, 1366×768,
      and 1024×768 using realistic long names.
- [ ] `/stage` remains audience-safe at 1920×1080 and 1366×768.
- [ ] Reduced motion, refresh recovery, Reverb restart recovery, and local-only
      assets remain working.

## Regression

- [ ] Relevant Laravel tests pass.
- [ ] Phase 3 concurrency tests pass.
- [ ] Pint passes.
- [ ] ESLint passes.
- [ ] Production build succeeds.
- [ ] Live browser harness passes.
- [ ] No external runtime request/dependency was introduced.
- [ ] `CHANGELOG_UI.md` is updated.
