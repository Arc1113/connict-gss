# UI Testing Checklist

Use this checklist for functional frontend verification. Record actual commands
and results in the phase report.

## Before changes

- [ ] Read playbook, requirements, architecture, relevant screen specs.
- [ ] Inspect current implementation and existing evidence.
- [ ] Run relevant backend baseline.
- [ ] Run current lint and build.
- [ ] Preserve unrelated worktree changes.

## Scanner function

- [ ] Authorized route opens; unauthorized roles remain denied.
- [ ] Token input is focused at load.
- [ ] USB-style input plus Enter submits once.
- [ ] Empty input is rejected locally.
- [ ] Processing state locks repeat submission.
- [ ] Input clears and refocuses after response.
- [ ] Valid scan confirms graduate.
- [ ] Invalid, duplicate, completed, ineligible, inactive, and busy results are
      specific and actionable.
- [ ] Network failure does not claim success.
- [ ] Reverb loss does not disable HTTP scanning.
- [ ] Reconnected state is communicated in text.
- [ ] Missing/long graduate information remains safe.
- [ ] Tab reaches navigation and action controls without a focus trap.

## Stage function

- [ ] Initial HTTP loading is neutral.
- [ ] Idle state is correct.
- [ ] Valid event presents the graduate.
- [ ] Refresh restores PostgreSQL state.
- [ ] Stale events do not replace newer state.
- [ ] Version gap/reconnect triggers HTTP reconciliation.
- [ ] Reverb loss preserves a clean last-known display.
- [ ] Clear returns to idle.
- [ ] Audio activates through one-time interaction and does not overlap.
- [ ] Missing audio/photo/honors is safe.
- [ ] No technical or operator information is visible.

## Regression

- [ ] Relevant Laravel tests pass.
- [ ] Phase 3 concurrency tests pass.
- [ ] Pint passes.
- [ ] ESLint passes.
- [ ] Production build succeeds.
- [ ] Live browser harness passes.
- [ ] No external runtime request/dependency was introduced.
- [ ] `CHANGELOG_UI.md` is updated.
