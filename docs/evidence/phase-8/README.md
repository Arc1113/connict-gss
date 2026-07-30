# Phase 8 Evidence

Status: completed for Phase 8 implementation

This folder records Phase 8 browser verification evidence. PNG screenshots are
ignored by Git under `/docs/evidence/**/*.png`; this text index is the tracked
record of routes, states, and viewport checks.

Browser harness:

- `tests/Support/phase8-browser-check.mjs`

Verified coverage:

- graduate administration upload, preview, needs-review, approval, replacement,
  approval reset, and rejection states;
- stage-control approved and missing-audio replay guidance;
- audience stage approved playback, approved-only preload, visual-only missing
  audio, Reverb outage recovery, and reconnect recovery;
- no audience-facing technical audio details, Reverb/WebSocket text, student
  numbers, QR tokens, controls, or debug output;
- reduced-motion media preference recognition;
- horizontal-overflow checks for administration, stage-control, and stage;
- viewports 1440×900, 1366×768, and 1024×768 for administration/stage-control;
- viewports 1920×1080 and 1366×768 for the audience stage.

Generated PNG files are local visual-review artifacts only and must not be
committed.
