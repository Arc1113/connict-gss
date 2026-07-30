# Event Readiness Report

Status: **CONDITIONAL GO — software release candidate ready; hardware and venue
certification pending**

Release candidate: `GSS-RC1`

Git commit tested: `872fc3bebce9ce256d8899a438b6567a8e5d600e` plus approved
Phase 11 verification/documentation changes.

Date: 2026-07-30

## Readiness summary

| Area | Status | Evidence |
| --- | --- | --- |
| Software workflows | Verified | Full Laravel tests, Phase 11 tests, browser rehearsal |
| PostgreSQL source of truth | Verified | Transaction, queue, interruption, backup, and restore tests |
| Reverb/Echo recovery | Verified | Browser rehearsal restarted Reverb and recovered via HTTP |
| Production assets | Verified | Vite production build and browser local-asset check passed |
| Local deployment package | Verified | Disposable production-style Compose deployment started and passed health checks |
| Backup and restore verification | Verified | Backup `gss-backup-20260730-171515` restored into a disposable database |
| Security/privacy checks | Verified for software | Role checks, export privacy, no raw QR tokens in audience/control payloads |
| Physical USB scanner | Pending | No physical device attached during this pass |
| Three-laptop layout | Pending | Simulated with browser profiles, not verified with separate laptops |
| Venue display and audio | Pending | No projector/LED or venue sound system tested |

## Final readiness status

`CONDITIONAL GO`

Exact reasons:

- Software release candidate checks passed.
- Deployment and standby simulation passed on a disposable local Compose project.
- Backup and disposable restore verification passed.
- Required browser routes and viewports were inspected.
- A real admin layout overflow defect was found and fixed.
- Physical scanner, printed cards, three-device network, venue display, and
  venue audio were not available in this session and cannot be certified by
  software tests.

## Defects found and fixed

- Admin navigation/account topbar overflowed horizontally during the browser
  rehearsal at the operational browser width.
- Fix: constrained the admin nav/account flex layout so navigation scrolls
  inside the topbar instead of widening the page.

## Known limitations

- Physical USB scanner timing and suffix behavior remain unverified.
- Printed QR card scanning distance and paper contrast remain unverified.
- Projector/LED readability at the actual stage distance remains unverified.
- Venue audio output device, loudness, and fallback-announcer workflow remain
  unverified.
- Router DHCP reservations, firewall rules, and multi-laptop browser profiles
  remain human setup tasks.

## Recommended human action

Run the physical event rehearsal using `docs/event-day-checklist.md` on the
actual venue hardware. If the physical rehearsal passes, the project can move
from `CONDITIONAL GO` to `GO` for the event.
