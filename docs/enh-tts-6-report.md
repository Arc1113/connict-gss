# ENH-TTS-6 Phase Report

Status: implemented; awaiting review and approval

Date: 2026-08-04

## Before

ENH-TTS-5 provided review, promotion, invalidation, local stage guards, and
backup coverage. Ceremony templates existed in the domain but had no dedicated
administrator settings workflow, draft fictional preview, or controlled voice
comparison interface.

## Delivered

- Administrator-only ceremony announcement settings and navigation.
- Server-enforced voice/locale allowlist, speaking-rate and pause bounds, and
  coherent character limits.
- Immutable template publication with sequential versions, retained history,
  one active version, safe audit metadata, and candidate invalidation.
- Side-effect-free draft preview using a fixed fictional graduate and the real
  deterministic composer/SSML pipeline.
- Explicit one-to-four voice comparison using the same fictional content,
  pacing, and output format.
- Private, validated, expiring sample MP3s with nested administrator-only,
  no-store playback and pruning on the next comparison command.
- Local usage estimates include successful comparison characters.
- Backup dumps omit temporary sample row data and backup media omits their
  separate private directory.

## Security and runtime boundary

The browser receives no credentials, private paths, SSML, or provider response
bodies. Comparison samples cannot become graduate audio. Settings reads and
draft previews do not call Azure, and no ceremony-time path was changed to call
Azure. Provider use remains an explicit preparation-only command and fails
safely when disabled or unconfigured.

## Schema and rollback

Migration `2026_08_04_000021_create_announcement_voice_samples_table.php` adds
only expiring comparison metadata. Rolling it back drops that disposable table;
existing templates, generations, approved audio, and stage behavior remain.

## Verification

Passed on 2026-08-04:

- `php artisan migrate --force` applied migration `000021` to the running
  PostgreSQL stack.
- Focused ENH-TTS-5/6 regression: 13 tests, 120 assertions.
- Full PostgreSQL PHPUnit regression: 320 tests, 2,608 assertions.
- `vendor/bin/pint --test`: 308 files passed.
- `npm run lint`: passed, including the browser rehearsal.
- `npm run build`: passed; 611 modules transformed.
- `GSS_BROWSER_EMAIL=... GSS_BROWSER_PASSWORD=... node
  tests/Support/enh-tts-6-browser-rehearsal.mjs <fixture-id>`: passed
  administrator access, fictional draft/toggle behavior, voice and pacing
  changes, version publication/history, invalid pacing feedback,
  provider-disabled protection, desktop/mobile fit, and browser-error checks.

Visual inspection covered the desktop initial form, post-preview comparison
state, dense version history, and 390 × 844 mobile layout. No horizontal
overflow, clipped controls, broken hierarchy, or unexpected browser errors were
found. The expected HTTP 422 from the intentional invalid-rate check was the
only rejected browser request. The rehearsal made no provider call, and its
temporary administrator, ceremonies, templates, and audits were removed.

## Deferred

ENH-TTS-7 bounded batch generation is not started. This phase stops for review,
commit, and explicit approval.
