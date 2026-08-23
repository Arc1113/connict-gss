# Phase 15 — Ceremony Preparation Usability Evidence

Date: 2026-08-21

## Rehearsal scope

The end-to-end ceremony-preparation goal was exercised from the perspective of
a nontechnical staff member:

> Prepare this graduation using GSS.

The rehearsal covered creating a ceremony, choosing guided setup, skipping setup
without changing readiness, skipping an unresolved preparation step, exiting and
returning to the ceremony workspace, opening Preparation, and opening Run
Ceremony while keeping the selected ceremony separate from the live ceremony.

The available environment did not include an independent human participant, so
this record is an agent-operated usability rehearsal and is not a substitute for
real staff sign-off. The task language and assertions intentionally avoid module
names and implementation details.

## Evidence

- `tests/Browser/full-system-critical.spec.js` passed all 25 tests through the
  isolated Playwright + Axe harness.
- The rehearsal used the disposable PostgreSQL database
  `graduation_stage_system_testing` and the repository's seeded accounts.
- Migration `2026_08_21_000001_create_ceremony_setup_progress_table` was applied
  before the rehearsal. Setup progress is persisted per ceremony and user;
  skipping a step leaves the domain readiness issue unresolved.
- Covered viewport checks include 1366×768, 1536×864, and 1920×1080 desktop
  layouts plus the mobile navigation scenario.
- Axe checks passed on the exercised ceremony, preparation, guided setup, run
  ceremony, operator, and workspace screens.

## Acceptance result

The software acceptance rehearsal passed. A follow-up session with an actual
registrar, teacher, or data encoder should still be scheduled before release to
capture human comprehension and time-on-task feedback.
