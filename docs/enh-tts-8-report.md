# ENH-TTS-8 Final Readiness Report

Status: **CONDITIONAL GO**

Date: 2026-08-04

Baseline: approved ENH-TTS-7 commit `cbfb873`

The automated-announcement software gate is **GO**. Overall event readiness is
**CONDITIONAL GO** because physical hardware, venue conditions, and final
institutional wording/pronunciation approval cannot be certified by software.

## Scenario matrix

| Scenario | Result | Evidence |
| --- | --- | --- |
| No awards | Passed | Deterministic formatter/composer feature and unit tests |
| One award | Passed | Deterministic formatter/composer feature and unit tests |
| Multiple and long award lists | Passed | Ordering, grammar, length-warning, and blocking tests |
| Difficult Filipino name | Passed in software | Unicode tests and backed-up fictional `Jose Mari Dela Peña` fixture |
| Spoken-name correction | Passed | Override precedence and invalidation tests |
| Voice comparison | Passed with mocked provider | Allowlist, fictional-only input, expiry, privacy, and usage tests |
| Generate, reject, regenerate, and approve workflow | Passed | Candidate generation and administrator review tests |
| Award edit makes candidate stale | Passed | Source invalidation and stage guard tests |
| Manual replacement supersedes generated audio | Passed | Compensated media replacement and source-metadata tests |
| Bounded batch generation | Passed | Ordering, idempotency, pause/resume/cancel, worker interruption, and authorization tests |
| Azure outage | Passed with HTTP fakes | Safe unavailable/timeout mapping and bounded retries |
| Invalid Azure key | Passed with HTTP fakes | Non-retrying authentication failure and secret redaction |
| Azure rate limit | Passed with HTTP fakes | Bounded retry and batch-pause behavior |
| Internet disconnected | Passed | Internal-only Docker network; application-container egress attempt blocked |
| QR through approved local playback | Passed | Isolated Chromium scan, queue, present, same-origin WAV fetch, replay, and completion |
| Stale generated audio refused | Passed | Recomposition/hash and unreadable-media runtime guard tests |
| Live-announcer fallback | Passed | Missing-audio browser path kept the visual presentation operational |
| Backup and disposable restore | Passed | Real backup and exact announcement database/media verification |
| Original Phase 3–12 regression | Passed | Full PostgreSQL suite and isolated browser workflow |

## Verification results

- Clean PostgreSQL migrations passed; the final enhancement migration was
  rolled back and reapplied successfully.
- Full Laravel suite: **333 tests, 2,731 assertions**.
- Focused ENH-TTS suite: **121 tests, 646 assertions**.
- Pint: **327 files passed**.
- ESLint and the Vite production build passed; Vite built **612 modules**.
- Strict Composer validation and Composer advisory audit passed.
- npm audit passed with zero vulnerabilities after updating `brace-expansion`
  in the lockfile from 5.0.8 to 5.0.9.
- Base, deployment, and internal-network Compose configurations passed.
- The isolated Chromium rehearsal passed role authentication, invalid/valid/
  duplicate QR handling, queueing, presentation, replay, completion, HTTP
  recovery, same-origin approved audio, missing-audio fallback, and layout
  checks without an Azure or external browser request.

## Backup and restore evidence

Verified backup:
`storage/app/gss-backups/gss-backup-20260804-122018`

The disposable restore reported 3 ceremonies, 17 graduates, 34 media files, 2
awards, 1 template, 1 generation, 1 batch, 1 batch item, 1 private candidate,
and 1 active generated-audio file. Temporary fictional voice-comparison row
data and media were excluded.

The backup now requires a quiet announcement-preparation snapshot and exact
database-to-private-candidate file agreement. Restore verification checks entity
counts, source-hash shape, active generated references, protected paths,
readability, non-empty media, and orphan absence.

## Defects corrected during the gate

- Docker backup previously looked for candidates outside Laravel's configured
  private local-disk root; it now copies from `storage/app/private`.
- Docker database dumps now exclude temporary voice-comparison row data as the
  application backup path already did.
- Restore verification previously checked only broad row/media counts; it now
  verifies announcement relationships, hashes, and exact protected media.
- Backup could race active preparation; both application and Docker workflows
  now fail safely while a generation or batch item is in flight.
- A restore with zero candidates could exit on a legitimately absent candidate
  directory; it now treats that state as zero and still checks exact equality.
- The npm high-severity `brace-expansion` advisory was cleared in the lockfile.

## Security and privacy disposition

Tracked source and Git-history scans found no non-empty Azure key assignment.
The current runtime configuration reports Azure disabled and unconfigured.
No actual key appeared in browser assets. Private candidate paths, SSML,
provider bodies, and credentials remain outside browser props and audits.
Remote-looking strings left in compiled vendor code are inert library metadata
or documentation references; the isolated browser made no remote runtime
request.

## Remaining human gates

- Rehearse with the actual USB scanner, printed QR cards, three laptops, and
  dedicated router/switch.
- Verify projector/LED readability and the venue audio device, loudness, and
  live-announcer handoff.
- Have authorized institutional reviewers approve award wording, default
  voice/locale, and each difficult-name pronunciation.
- Copy the verified backup to the approved standby laptop or external drive and
  repeat restore verification there.

When these checks pass, overall event readiness may move from CONDITIONAL GO to
GO without another automated-announcement software phase.
