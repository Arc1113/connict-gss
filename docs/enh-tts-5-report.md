# ENH-TTS-5 Implementation Report

Status: implementation and verification complete; awaiting human review and commit

Date: 2026-08-04

Baseline commit: `c4bc6f8`

## Delivered

- administrator-only candidate approval and note-required rejection;
- nested ownership, live source-hash, and private MP3 revalidation;
- compensated promotion into managed graduate audio storage;
- persisted Azure source type, generation reference, and source hash;
- safe review audit history without SSML, credentials, or storage paths;
- automatic invalidation for spoken student data, awards, templates, and voices;
- stage/preload/replay refusal of stale or unreadable generated audio;
- manual-upload precedence over generated audio;
- protected private-candidate inclusion in checksum-covered backups; and
- administrator review controls in the graduate announcement workspace.

## Schema and provider impact

No migration, package, environment-variable, or provider-call change was
required. Azure remains disabled by default and is never contacted during
review, stage serialization, replay, backup, or restore verification.

## Rollback

This phase is code-only. Reverting its commit removes the review routes and
guards without a database rollback. Promoted files use the existing managed
graduate-audio directory; database rows retain sufficient source metadata for
manual reconciliation. Do not delete active media during rollback.

## Verification

Focused feature coverage includes authorization, approval, rejection, stale
approval, source/template/award invalidation, stage exclusion, database-failure
compensation, manual override, backup inclusion, and audit redaction.

- PHPUnit: 314 passed, 2,528 assertions;
- Pint: 293 files passed;
- ESLint: passed;
- Vite production build: passed;
- Playwright review rehearsal: passed at 1440 px with no overflow or application
  JavaScript errors; approve/reject actions and active-audio state verified;
- known environment note: the temporary direct-HTTP rehearsal reported two
  Reverb WSS handshake warnings from the pre-existing proxy/runtime mismatch.
