# Phase 12 — Real-World Operations and UX Corrections

Document status: **Implemented and verified**

Last updated: 2026-07-30

## 1. Purpose

Phase 12 corrects verified real-world usability gaps found after the Phase 11
software rehearsal. It does not replace the scan-to-stage architecture or add
unattended timing. The phase makes the live queue behavior understandable,
provides safe operator recovery for a wrongly presented graduate, reduces the
number of commands required for normal advancement, repairs role-aware
navigation, and gives administrators a local way to provision operator
accounts.

The permanent architecture remains:

- Laravel and React in one Inertia application;
- HTTP for commands and recovery reads;
- PostgreSQL as the authoritative source of truth;
- post-commit Reverb events and Echo recovery;
- local media and local-network-only operation.

## 2. Observed problems

1. The label `Automatic` implies unattended queue movement, but the implemented
   rule only auto-presents a new scan when the stage is idle, automation is not
   paused, and no earlier row is waiting.
2. Completion clears the stage and never presents the next waiting graduate.
   This is safe but requires two operator commands and appears broken when the
   mode label promises more.
3. A waiting graduate can be skipped and returned, but a wrongly presented
   current graduate has no safe correction. Completing that graduate creates
   permanent completed history.
4. The stage operator has controls, but the primary completion behavior and
   recovery path are not prominent or complete enough for ceremony pressure.
5. Technical operators can reach logs and backups directly but the shared
   administration brand and `Readiness` link send them to an unauthorized
   `/admin` route.
6. There is no administrator interface for creating, changing, deactivating, or
   resetting passwords for operator accounts.
7. The current administration index uses equal statistic/task card grids,
   contrary to the approved compact readiness/task hierarchy.
8. Queue radio controls do not meet the 44×44 px pressured-operation target.
9. Public and dashboard copy still contains development/foundation language.

## 3. Decisions

### 3.1 Presentation-mode language

The stored enum and database value remain `automatic`. The operator label
becomes **Assisted automatic**.

Assisted automatic means:

- an eligible new scan may appear immediately only when the stage is idle,
  automation is not paused, and no earlier graduate is waiting;
- an occupied stage is never replaced;
- a backlog is never consumed by resume alone;
- the operator explicitly confirms completion and the next presentation; and
- no timer, audio-ended trigger, polling loop, or unattended advancement exists.

Controlled means:

- every graduate presentation starts from Stage Control; and
- scans only add waiting rows.

Ceremony forms and stage control must show this distinction next to the mode
control. Renaming the label does not migrate or reinterpret existing records.

### 3.2 Complete and present next

Add an explicit `POST /stage/complete-and-present-next` command.

When a current graduate exists, the command uses one PostgreSQL transaction to:

1. lock the exactly-one active ceremony and stage state;
2. lock and complete the current presenting row;
3. lock the earliest waiting/ready row;
4. present that row when it exists, otherwise clear to idle;
5. increment the stage version once;
6. record the completed and next-presentation events before commit; and
7. broadcast the resulting audience state and compact queue update only after
   commit.

The audience must not briefly receive a clear event when another graduate is
presented in the same command. If no graduate is waiting, the command completes
and clears safely.

The existing `POST /stage/complete-current` contract remains available as
**Complete and clear**. It is not silently changed.

The combined command is available in either ceremony mode because it is an
explicit operator action. In assisted automatic mode it is the dominant normal
action. In controlled mode it is a clearly labeled efficiency option while
`Complete and clear` remains available.

### 3.3 Correct a wrongly presented graduate

Add `POST /stage/return-current`.

The command uses one transaction to:

1. lock the active ceremony, stage state, and current presenting/announced row;
2. return that row to `waiting` at its preserved queue position;
3. clear `presented_at` and `presented_by`;
4. detach it from the current stage state;
5. activate the neutral holding display and stop stage audio;
6. increment the stage version;
7. record a purpose-built stage event; and
8. broadcast holding and queue notifications after commit.

The preserved position is intentional. Presenting a row does not remove its
position, so returning the current graduate to that position restores the
pre-presentation order without inventing a new tail order.

The action is labeled **Correct wrong graduate** and requires confirmation. Its
copy must state that the audience display will move to holding and that the
graduate returns to the queue without being completed. The operator then
selects or presents the correct graduate. Restoring the holding display before
presenting is not required because the present command will remain a separate
deliberate recovery step after the current row has been corrected.

### 3.4 Account administration

Administrators may manage local accounts at `/admin/users`.

Scope:

- list/search/filter accounts;
- create an account with one approved role;
- edit name, email, role, and active state;
- set or replace a password;
- audit creation and updates without storing password values; and
- keep account deletion out of scope.

Safety rules:

- passwords are at least 12 characters and require confirmation;
- email remains globally unique;
- an administrator cannot deactivate or demote their own current account;
- the last active administrator cannot be deactivated or demoted;
- password audit data records only a boolean `password_changed`;
- ordinary pages and logs never expose password hashes;
- inactive-account middleware continues to terminate access.

This is local operator provisioning, not public registration, email delivery, or
cloud identity.

### 3.5 Role-aware navigation

The administration shell derives an allowed home route:

1. `/admin` for users who may view ceremony or graduate readiness;
2. `/admin/scan-logs` for a technical/operational user who may view logs; or
3. `/admin/backups` for a backup-only authorized user.

`Readiness` is rendered only when the user can open it. The brand link uses the
same allowed home route. Technical operations are linked from the account
dashboard.

## 4. Primary users, devices, and risks

| Workflow | Primary user | Device/viewport | Time pressure | Main risk |
| --- | --- | --- | --- | --- |
| Live advancement and correction | Stage operator | Protected operator laptop; 1440×900, 1366×768, 1024×768 | High | Completing or showing the wrong graduate |
| Account provisioning | Administrator | Administration laptop; same operator viewports | Low before event, high during recovery | Removing the final usable administrator or exposing credentials |
| Technical navigation | Technical operator | Server/technical laptop; same operator viewports | Medium | Being routed to a forbidden page during an outage |
| Mode configuration | Administrator | Ceremony create/edit | Low during preparation | Expecting unsafe unattended behavior |

The stage-control route must never be projected. `/stage` remains the only
audience-facing route.

## 5. Stage-control layout alternatives

### Option A — Current-first control with recovery strip (selected)

```text
┌──────────────────────────────────────────────────────────────────────┐
│ STAGE CONTROL  Ceremony  Assisted automatic · Connected             │
├──────────────────────────────────┬───────────────────────────────────┤
│ CURRENT GRADUATE                 │ WAITING QUEUE                     │
│ Christopher Emmanuel            │ ○ 18 Ana Marie dela Cruz          │
│ Villanueva-Santos                │ ○ 19 Ma. Lourdes P. Reyes         │
│ BS Business Administration      │                                   │
│                                  │ [Present selected]                │
│ [Complete and present next]      │                                   │
│ [Complete and clear] [Replay]    │                                   │
│ [Correct wrong graduate]         │                                   │
├──────────────────────────────────┴───────────────────────────────────┤
│ MODE: Assisted automatic — scans present only while idle.           │
│ RECENT ACTIONS / SKIPPED GRADUATES                                  │
└──────────────────────────────────────────────────────────────────────┘
```

Advantages:

- keeps the current graduate and normal next action dominant;
- places wrong-graduate recovery beside the state it corrects;
- reuses the verified Phase 7 responsive structure; and
- adds no new navigation or command mode.

Risk:

- several current-graduate actions require careful primary/secondary/danger
  hierarchy.

### Option B — Queue-first dispatch console

```text
┌──────────────────────────────────────────────────────────────────────┐
│ CURRENT: Christopher...  [Complete] [Correct]                       │
├──────────────────────────────────────────────────────────────────────┤
│ FULL WAITING QUEUE                                                   │
│ ○ 18 Ana...       ○ 19 Ma. Lourdes...       [Dispatch selected]     │
├──────────────────────────────────────────────────────────────────────┤
│ AUDIO / MODE / RECENT ACTIONS                                        │
└──────────────────────────────────────────────────────────────────────┘
```

Advantages:

- supports operators who primarily think in queue order;
- exposes more rows without scrolling.

Risks:

- demotes the audience's current state;
- makes a live mistake easier to miss; and
- departs from the approved current-first hierarchy.

Option A is selected. It is the smaller and safer correction.

## 6. Account-administration textual wireframe

```text
┌──────────────────────────────────────────────────────────────────────┐
│ ACCOUNTS                                      [Add operator account] │
│ Create local accounts before rehearsal. Passwords are never shown.  │
├──────────────────────────────────────────────────────────────────────┤
│ Search [____________]  Role [All]  Status [All]  [Apply] [Clear]    │
├──────────────────────────────────────────────────────────────────────┤
│ Name / email         Role               Status             Action   │
│ Maria Santos         Stage operator     Active             Edit     │
│ technical@local      Technical operator Active             Edit     │
└──────────────────────────────────────────────────────────────────────┘
```

Create/edit forms use the existing administration form, validation-summary, and
field conventions. The password field is required on create and optional on
edit. Edit copy explains that leaving password fields blank preserves the
current password.

## 7. Required states and recovery

### Stage control

- loading authoritative queue and stage state;
- idle and empty queue;
- idle with waiting queue;
- current graduate with no waiting row;
- current graduate with waiting rows;
- combined completion pending/succeeded/failed;
- correction pending/succeeded/failed;
- holding after correction;
- assisted automatic active and paused;
- controlled mode;
- disconnected and HTTP-recovered;
- long graduate/program names;
- missing/unapproved audio; and
- concurrent conflict with refresh guidance.

### Account administration

- list with active/inactive accounts;
- no matching results;
- create pending/saved/validation failure;
- edit pending/saved/validation failure;
- optional password unchanged;
- password changed;
- own-account deactivation/demotion blocked;
- final active administrator protection; and
- unauthorized role denied.

## 8. Accessibility and responsive behavior

- Every action remains a native button or link with a specific label.
- Risky commands require confirmation and remain reachable by keyboard.
- Queue selection uses a label/target region at least 44×44 px; selection is not
  communicated by color alone.
- Pending commands disable every mutation and provide a live textual status.
- Validation errors remain linked to account fields and summarized.
- Long names, email addresses, programs, and role labels wrap safely.
- Stage control keeps current graduate/actions before the queue at 1024×768.
- Tables own horizontal overflow; the page body does not.
- Reduced motion removes non-essential transitions.

## 9. Security, privacy, and failure behavior

- Existing APIs, events, channels, scan validation, QR privacy, audio rules, and
  database constraints remain intact.
- New stage mutations require the existing `operate-stage` gate.
- User administration requires `manage-users` at every route.
- New user writes and audit rows share one transaction.
- Unexpected failures return safe recovery text and never include stack traces,
  password data, raw QR data, or connection credentials.
- Broadcast failure after a committed stage command does not roll back
  PostgreSQL; clients recover by HTTP.
- Concurrent combined-completion or correction commands must leave one legal
  queue/stage state.

## 10. Out of scope

- timer-driven advancement;
- advancement triggered by audio completion;
- unattended queue consumption;
- polling;
- arbitrary drag-and-drop queue reorder;
- re-presenting completed graduates;
- deleting user accounts;
- public registration, email invitation, or password-reset email;
- device registration/credentials;
- phone camera scanning;
- new UI frameworks or internet runtime services; and
- physical scanner/projector/sound certification without attached hardware.

## 11. Acceptance criteria

1. `Automatic` is presented to users as `Assisted automatic` with concise,
   accurate behavior guidance on ceremony forms and Stage Control.
2. A current graduate and earliest waiting graduate can transition atomically
   through `Complete and present next` with one stage version and no clear
   flicker.
3. If no row waits, the combined command completes and clears safely.
4. `Complete and clear` preserves the established completion contract.
5. `Correct wrong graduate` returns the current row to its preserved queue
   position, shows holding, stops audio, records history, and does not mark the
   graduate completed.
6. Concurrent or stale commands produce one legal committed result and an
   actionable conflict for the loser.
7. Stage HTTP recovery and post-commit event delivery remain authoritative and
   audience-safe.
8. Administrators can create and edit local accounts while self-lockout and
   final-active-administrator protections pass.
9. Non-administrators cannot access or mutate user administration.
10. Technical operators can navigate to authorized logs/backups without an
    unauthorized brand/readiness link.
11. Administration readiness and tasks use compact lists rather than equal card
    grids.
12. Stage-control selection and all new actions meet keyboard, focus, target,
    long-content, and responsive requirements.
13. Public/dashboard copy no longer claims a development foundation or exposes
    the implementation stack as product content.
14. Relevant Laravel tests, concurrency tests, Pint, ESLint, and production Vite
    build pass.
15. `/stage-control`, `/admin/users`, affected ceremony forms, `/dashboard`, and
    `/stage` are inspected in the real browser at their required viewports and
    states.
16. No external runtime dependency or audience-facing technical information is
    introduced.
