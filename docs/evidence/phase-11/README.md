# Phase 11 Evidence Index

Status: Phase 11 software evidence captured

Release candidate: `GSS-RC1`

Git commit tested: `872fc3bebce9ce256d8899a438b6567a8e5d600e` plus the
approved uncommitted Phase 11 verification/documentation changes.

## Automated evidence

- Phase 11 full-system readiness tests: 4 tests, 297 assertions.
- Phase 7 queue concurrency regression: 7 tests, 28 assertions.
- Full Laravel suite: 175 tests, 1,741 assertions.
- Pint style check: passed.
- ESLint: passed.
- Vite production build: passed.
- Composer validation: passed.
- Composer audit: no advisories found.
- npm audit: 0 vulnerabilities.
- Docker Compose development and deployment configuration validation: passed.
- Release-build script: passed with a disposable deployment env file.

## Browser rehearsal evidence

The browser rehearsal used authenticated Chromium profiles for:

- administrator;
- data encoder;
- scanner operator;
- stage-control operator;
- audience stage.

Routes exercised:

- `/scanner`;
- `/stage-control`;
- `/stage`;
- `/admin`;
- `/admin/ceremonies`;
- `/admin/students`;
- `/admin/students/import`;
- `/admin/students/qr-codes`;
- `/admin/scan-logs`;
- `/admin/stage-events`;
- `/admin/audit-logs`;
- `/admin/reports/ceremony-summary`;
- `/admin/backups`.

Captured PNG files are intentionally ignored by Git under
`/docs/evidence/**/*.png`.

Screenshots captured during the passing run:

- `scanner-ready-1440x900.png`
- `stage-control-empty-1440x900.png`
- `stage-idle-1920x1080.png`
- `stage-idle-1366x768.png`
- `stage-presented-approved-audio-1920x1080.png`
- `stage-long-name-program-1366x768.png`
- `stage-holding-1920x1080.png`
- `stage-control-missing-audio-1024x768.png`
- `scanner-1366x768.png`
- `scanner-1024x768.png`
- `stage-control-1440x900.png`
- `stage-control-1366x768.png`
- `stage-control-1024x768.png`
- `stage-final-1920x1080.png`
- `stage-final-1366x768.png`
- `stage-reduced-motion-1366x768.png`

## Viewports inspected

- Scanner: 1440×900, 1366×768, 1024×768.
- Stage control: 1440×900, 1366×768, 1024×768.
- Audience stage: 1920×1080, 1366×768, and reduced-motion 1366×768.
- Administration routes: no horizontal overflow at the rehearsal browser width.

## Rehearsal data

The browser rehearsal used fictional graduate records only, including long
names, long programs, missing honors, missing audio, rejected audio, invalid QR,
duplicate QR, ineligible graduate, controlled mode, automatic pause/resume,
Reverb restart, app restart, and HTTP state recovery.

Final rehearsal database counts:

- graduates: 9;
- queue items: 8;
- scan logs: 11;
- stage events: 21.

## Operational evidence

- Reverb interruption and recovery: passed through browser rehearsal.
- Application restart and browser recovery: passed through browser rehearsal.
- PostgreSQL interruption simulation: isolated deployment returned a safe
  failure while PostgreSQL was stopped and recovered after PostgreSQL became
  healthy.
- Deployment/standby simulation: isolated `gss-phase11` Docker Compose project
  built, migrated, started, passed health checks, and was removed with its
  disposable volumes.
- Backup created: `storage/app/gss-backups/gss-backup-20260730-171515`.
- Disposable restore verification: passed; restored 2 ceremonies, 14 graduates,
  and 15 media files into a disposable database/media directory.

## Hardware and venue evidence

Not completed in this software-only pass:

- physical USB 2D scanner;
- three separate laptops;
- dedicated event router;
- projector or LED output;
- venue audio system;
- actual printed QR cards;
- audience-distance readability in the venue.

These checks remain mandatory before final event certification.
