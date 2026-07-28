# Phase 3A Browser Evidence

Captured: 2026-07-28  
Routes: authenticated `/scanner` and `/stage`  
Data: development-only sample ceremony and graduates

The evidence was produced by:

```bash
node tests/Support/phase3-browser-check.mjs
```

The harness uses the real Laravel application, PostgreSQL state, authenticated
roles, private Reverb/Echo delivery, local audio, HTTP recovery, and exact
viewport emulation. It temporarily changes only known development fixture
states and restores ceremony/student mutations if a check fails.

## Scanner evidence

- Required viewports: `scanner-ready-1440x900.png`,
  `scanner-ready-1366x768.png`, `scanner-ready-1024x768.png`
- Input behavior: `scanner-processing-1024x768.png`
- Results: invalid, duplicate, stage busy, ineligible, missing student
  information, paused ceremony, and server failure
- Live recovery: disconnected, saved during outage, and reconnected
- Realistic success: `scanner-success-long-name-1440x900.png`

## Stage evidence

- Required viewports: empty, long-name, and recovered states at 1920×1080 and
  1366×768
- Other states: loading, HTTP refresh recovery, missing display name, missing
  audio, and cleared
- Missing honors/photo are represented by the long-name presentation, where
  neither optional element is rendered.

The automated checks also confirm no horizontal overflow, bounded stage
content, no audience-facing technical/operator content, native Enter
submission, focus restoration, one request under rapid repeat submission,
local audio lifecycle, HTTP refresh/reconnect recovery, and reduced-motion
recognition.

These screenshots use no production or personal data. Physical USB scanner,
projector, speaker, and approved-photo checks still require venue hardware and
assets.
