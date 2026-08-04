# Event Readiness Report

Status: **CONDITIONAL GO — ENH-TTS-8 software gate passed; hardware, venue, and
institutional announcement approval pending**

Release candidate: ENH-TTS-8 working-tree candidate based on
`cbfb873` (ENH-TTS-7 approved commit)

Git commit tested: `cbfb873` plus the approved ENH-TTS-8 working-tree
verification, recovery, and documentation changes.

Date: 2026-08-04

## Readiness summary

| Area | Status | Evidence |
| --- | --- | --- |
| Software workflows | Verified | 333 Laravel tests, 2,731 assertions; 121 focused announcement tests, 646 assertions; concurrency and browser rehearsal |
| PostgreSQL source of truth | Verified | Transaction, queue, interruption, backup, and restore tests |
| Reverb/Echo recovery | Verified | Browser rehearsal restarted Reverb and recovered via HTTP |
| Production assets | Verified | Vite production build and browser local-asset check passed |
| Local deployment package | Verified | Disposable production-style Compose deployment started and passed health checks |
| Backup and restore verification | Verified | Announcement-aware backup `gss-backup-20260804-122018` restored into a disposable database/media area |
| Disconnected ceremony runtime | Verified | Internal-only Docker network, Azure disabled, no TTS worker, external egress blocked |
| Automated-announcement preparation | Verified for software | Awards, composition, provider failures, review, invalidation, settings, batch, local playback, fallback |
| Security/privacy checks | Verified for software | Role checks, export privacy, no raw QR tokens in audience/control payloads |
| Live operator advancement/correction | Verified | Atomic combined command, expected-version race, wrong-current holding recovery |
| Local operator accounts/navigation | Verified | Administrator CRUD boundaries, self-protection, browser role navigation |
| Physical USB scanner | Pending | No physical device attached during this pass |
| Three-laptop layout | Pending | Simulated with browser profiles, not verified with separate laptops |
| Venue display and audio | Pending | No projector/LED or venue sound system tested |

## Final readiness status

`CONDITIONAL GO`

Exact reasons:

- Software release candidate checks passed.
- Deployment and standby simulation passed on a disposable local Compose project.
- Backup and disposable restore verification passed.
- The ENH-TTS-8 browser path passed with Azure disabled and external network
  egress blocked.
- Required browser routes and viewports were inspected.
- Phase 12 combined advancement, wrong-current correction, account
  administration, and technical-operator navigation passed automated and browser
  checks.
- A real admin layout overflow defect was found and fixed.
- Physical scanner, printed cards, three-device network, venue display, and
  venue audio were not available in this session and cannot be certified by
  software tests.
- Final spoken wording and difficult-name pronunciation require institutional
  reviewers listening on the selected venue voice and sound system.

## Defects found and fixed

- Admin navigation/account topbar overflowed horizontally during the browser
  rehearsal at the operational browser width.
- Fix: the identity row and a visibly wrapping navigation row stay inside the
  page instead of widening it or hiding links in horizontal scroll.
- The Automatic label implied unattended advancement even though only an
  idle-stage scan could present. It is now **Assisted automatic** with accurate
  operator guidance.
- Normal advancement required completion followed by a second present command.
  A version-guarded atomic **Complete and present next** action now performs the
  intended operator-confirmed transition.
- A wrongly presented current graduate had no non-completing recovery. The new
  correction action preserves the queue position and shows neutral holding.
- Technical-operator navigation could point to an unauthorized readiness page;
  the brand and visible navigation now resolve to authorized operational routes.
- Local operator accounts previously required CLI/database intervention; an
  administrator-only, audited account workspace now exists.

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
