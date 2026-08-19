# Pronunciation reliability and first-time guidance

## Implementation target

- Branch: `feature/windows-client-distribution`
- Audited base: `0cc3db1`
- The wrong-branch stash `codex/wrong-branch-pronunciation-onboarding` remains isolated and must not be applied.
- The Windows `.exe` is a release output, not the next implementation step. It remains blocked by the verification gates below.

## Pronunciation reliability

The application keeps canonical display/export spelling separate from speech text. Existing exact `tts_spoken_name` and award `spoken_text` overrides retain highest priority. Reusable rules then transform only the unprotected speech stream.

Rules support common or ceremony scope, announcement-section targeting, Azure/Kokoro variants, and optional locale/voice restrictions. Matching is Unicode-aware, case-insensitive, whole-word/phrase based, longest-first, punctuation-preserving, and non-cascading. Ceremony and section-specific rules win equal-length ties. User regular expressions, HTML, SSML, and angle-bracket syntax are rejected.

The existing `source_hash` remains authoritative. It now includes transformed speech text and the applied rule/variant revisions. Generations persist immutable display text and a pronunciation manifest. Rule changes use the existing invalidation workflow: affected generated candidates become outdated, active generated audio returns to review, and dictionary-only changes do not stale human recordings. Canonical wording, award, or student changes still return human recordings to review.

Administrators can manage common and ceremony rules. Data encoders can view common rules and manage ceremony rules plus the existing exact graduate/award corrections. The dictionary includes scoped/provider/voice/section/status filters, before/after text, real-provider preview when available, usage impact, revisions, enable/disable behavior, and audit records. The graduate workspace exposes canonical display text, actual TTS text, applied rules, exact correction paths, private generation, review, and approval.

Only deterministic expansions such as `Ph.D.`, `GPA`, and `Dr.` are initially enabled. Latin-honor phonetic spellings are intentionally not bundled by assumption. `Magna Cum Laude`, `Summa Cum Laude`, and `Cum Laude` must be added as provider- or voice-specific rules after calibration with the packaged voices.

## First-time guidance

Authenticated users receive one non-blocking, versioned guide for their role. Progress is isolated per user and supports start, pause, resume, skip, completion, and Help-menu restart. Tours use stable `data-tour` targets, skip missing/hidden/unauthorized elements, move across pages, scroll targets into view, trap and restore focus, support Escape, and respect reduced motion. Audience and unauthenticated pages receive no tour data. Automatic scanner, stage, and technical guidance is suppressed during an active ceremony while manual Help access remains available.

Role registries cover administrator setup, graduate/audio preparation, scanner rehearsal, stage/audio recovery, and technical readiness. Checklist completion uses real application state where reliable and labels rehearsal-only tasks separately.

## Windows first-run experience

The four sequential prompts are replaced by a six-step WinForms wizard: welcome, institution, administrator validation, password-free review, setup/readiness progress, and completion/recovery. It validates before requesting elevation, requests setup elevation once, protects the payload with DPAPI, zeroes temporary byte buffers, removes the payload in `finally`, clears the password field, verifies all local services, allows retry, preserves installed data, exports diagnostics, and opens the web application only after successful verification.

## Verification and release gates

Automated coverage includes normalization/precedence/non-cascading rules, unchanged canonical text, authorization/validation/auditing, targeted invalidation, human-audio isolation, onboarding isolation/suppression/reset, composer integration, and Windows wizard contracts.

Before packaging:

1. Run PostgreSQL-backed Laravel tests and Pint.
2. Run ESLint, the Vite production build, and browser accessibility/onboarding tests.
3. Build and exercise the .NET launcher, including UAC cancellation, retry, cleanup, diagnostics, and handoff.
4. Resolve and re-verify every pinned Windows runtime checksum before using release inputs.
5. Complete `docs/pronunciation-rehearsal.md` with actual Kokoro/Azure voices and venue audio equipment.
6. Generate, listen to, and explicitly approve representative graduate candidates; never infer audio readiness from text transformation alone.
