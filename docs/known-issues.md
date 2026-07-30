# Known Issues

Last updated: 2026-07-30

## Open before event certification

1. Physical USB scanner has not been verified in this Phase 11 software pass.
   Confirm HID keyboard mode, Enter suffix, scan distance, speed, and duplicate
   handling with final printed cards.
2. Three separate laptops and the final router were not physically tested in
   this session. Confirm LAN addressing, firewall rules, browser profiles, and
   power settings.
3. Projector/LED display was not physically tested. Confirm fullscreen stage
   display, audience-distance readability, color/contrast, and that no browser
   chrome or operator screen is visible.
4. Venue audio was not physically tested. Confirm output device, approved audio
   loudness, replay behavior, and live-announcer fallback.
5. Final ceremony data still requires human review for names, sequence, honors,
   eligibility, photos, audio approval, and QR card distribution.

## Fixed during Phase 11

1. Admin topbar horizontal overflow was detected during browser rehearsal and
   corrected with a constrained flex layout.

## Not considered blockers for software readiness

- Vite reports a bundle-size warning above 500 kB. The production build
  succeeds, assets are local, and no runtime internet dependency was introduced.
- Browser screenshots are generated as evidence but intentionally ignored by
  Git.
