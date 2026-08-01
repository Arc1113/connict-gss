# Responsive Rules

Status: Phase 3A foundation  
Updated: 2026-07-28

Mobile support is approved only for the authenticated phone-camera Scanner and
the restricted phone onboarding page. Stage and administration remain
desktop-first.

## Required targets

| Screen | Required viewports |
| --- | --- |
| Scanner | 1440×900, 1366×768, 1024×768, 430×932, 390×844 |
| Stage | 1920×1080, 1366×768 |
| Future operator/admin screens | 1440×900, 1366×768, 1024×768 |

## Scanner

- USB operation targets 1024px and wider. Camera operation also supports current
  phone portrait and landscape viewports.
- Readiness, active ceremony, token input, current result, and recovery action
  must remain visible without horizontal scrolling.
- At 1440px and 1366px, input and process action may share a row.
- At 1024px, retain the shared row if controls remain readable; otherwise stack
  only the action while keeping the result near the input.
- Supporting status may wrap into multiple columns/rows, but labels and values
  must remain paired.
- The page may scroll vertically. The input and current result must appear
  before secondary history.
- Long names and messages use natural wrapping and `overflow-wrap` as a final
  safeguard.
- On a phone, capture mode, camera preview, current result, and recovery action
  appear before recent scan history.
- Camera controls use at least 44px touch targets and remain usable after phone
  rotation.

## Stage

- Minimum supported viewport: 1366×768.
- The stage must never show horizontal or vertical scrollbars.
- Ceremony identity, name, program, and honors must fit the viewport together.
- Name scale must respond to both width and height.
- Prefer two or three natural name lines over character-level breaking.
- Program may wrap to two lines.
- Major and honors may collapse spacing but must not overlap.
- Missing photo must remove its layout region.
- An approved photo must shrink under height pressure before the graduate name.
- No audience-critical text may be clipped.

## Overflow ownership

- Scanner page owns vertical scrolling.
- Stage owns no scrolling; content is bounded within safe-area padding.
- Individual labels, buttons, and result regions must not create nested scroll
  areas.

## Verification rule

An affected viewport is verified only after the real authenticated route is
opened at the exact dimensions and the required content state is inspected.
Resizing a design mockup is insufficient.
