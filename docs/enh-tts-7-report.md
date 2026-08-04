# ENH-TTS-7 Phase Report

Status: implemented; awaiting review and approval

Date: 2026-08-04

## Before

ENH-TTS-6 provided versioned ceremony settings and controlled fictional voice
comparison. Candidate generation remained one graduate per synchronous browser
command, with no persistent bounded batch workflow, cancellation, resume, or
worker-owned progress.

## Delivered

- Administrator-only batch workspace and private progress channel.
- Server-side 1–50 distinct graduate limit, ceremony ownership and eligibility
  checks, real preview readiness checks, and deterministic sequence ordering.
- Proposed character total, projected monthly usage, and required local-budget
  warning acknowledgement.
- PostgreSQL batches and ordered items with idempotent active-selection hashes,
  persisted counts, safe failures, cancellation, and timestamps.
- Dedicated `tts-preparation` database worker at deployment concurrency one,
  one graduate per job, and one Laravel job attempt.
- Explicit pause/resume for rate limits, timeouts, temporary provider outages,
  or worker interruption; queued-item cancellation with safe in-flight finish.
- Automatic remaining-work cancellation when ceremony settings are versioned.
- Reuse of the existing single-candidate action, private atomic storage, and
  candidate fingerprint rules. Results remain `needs_review` and are never
  approved or promoted by the batch.
- Reverb progress notifications backed by authorized HTTP recovery reads and a
  manual refresh control; no browser polling.
- Compose startup/shutdown integration while keeping the worker outside
  ceremony-critical health and dependencies.

## Security and operational boundary

Batch browser props and events expose no SSML, provider bodies, credentials,
private paths, QR tokens, or active-audio internals. Stored failures remain
allowlisted and bounded. The worker has no listening port. Stopping the worker
or disabling Azure leaves Laravel, PostgreSQL, Reverb, scanner, stage, replay,
approved local playback, and recovery operational.

## Schema and rollback

Migration `2026_08_04_000022_create_announcement_generation_batches.php` adds
the persistent batch and batch-item tables. Rollback removes only batch progress
and queued preparation metadata; generated candidates retain their existing
generation history and private files.

## Verification

- ENH-TTS-6/7 focused suite: 16 tests and 184 assertions passed.
- Full PostgreSQL PHPUnit suite: 330 tests and 2,712 assertions passed.
- Pint: all 326 PHP files passed.
- ESLint passed.
- Vite production build passed with 612 modules transformed.
- Migration `000022` applied successfully to the running PostgreSQL stack.
- The dedicated `tts-worker` container started and remained independent from
  ceremony-critical service health.
- Deployment Compose rendering passed with the production environment file.
- Browser rehearsal passed at 1440×900 and 390×844: administrator access,
  preparation-only messaging, provider-disabled start/resume guards, ready-only
  selection and clear cycles, blocked-row handling, estimates, persistent
  completed/paused/queued histories, deterministic item order, safe failure
  display, queued-item cancellation, manual refresh, and horizontal fit.
- Browser monitoring observed no Azure provider request and no unexpected page
  or console error. Temporary QA records were removed afterward.

## Deferred

ENH-TTS-8 full backup/restore and disconnected ceremony rehearsal is not
started. This phase stops for review, commit, and explicit approval.
