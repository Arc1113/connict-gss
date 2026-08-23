# GSS Ceremony-First UX & Workflow Refactor — AI Agent Master Execution Playbook V2

**Repository:** `Aracena18/connict_gss`
**Source baseline:** `feature/windows-client-distribution`
**Working branch:** `feature/gss-usability-workflow-refactor`
**Purpose:** Refactor the Graduation Stage System (GSS) into a ceremony-first, guided, resumable workflow that is simple for teachers, registrars, data encoders, and operators while preserving all existing safety, authorization, realtime, transactional, and recovery behavior.

---

# 0. HOW TO USE THIS FILE

This file is the authoritative implementation plan for AI agents.

Small agents should:

1. Read `AGENTS.md`.
2. Read only:
   - Section 0
   - Section 1
   - Section 2
   - the assigned task section
3. Execute the assigned task exactly.
4. Do not redesign beyond the task.
5. Run the listed verification commands.
6. Report only:
   - files changed;
   - verification results;
   - blocker if one exists.

This playbook intentionally removes most design ambiguity.

---

# 1. NON-NEGOTIABLE ENGINEERING RULES

Every agent MUST obey all rules below.

## 1.1 Architecture

Do NOT replace or bypass:

- Laravel;
- React;
- Inertia;
- PostgreSQL;
- Reverb/Echo;
- HTTP command/recovery flow.

PostgreSQL remains authoritative.

Commands and recovery reads continue over HTTP.

Committed realtime updates continue through Reverb/Echo.

Do NOT add:

- SQLite fallback;
- polling;
- browser speech synthesis;
- external CDN dependencies;
- cloud media storage;
- a new frontend framework;
- Tailwind solely for this refactor.

## 1.2 Safety

Do NOT change unless the assigned task explicitly requires it:

- authorization semantics;
- role permissions;
- scan acceptance rules;
- duplicate-scan protection;
- stage queue state machine;
- presentation state transitions;
- backup verification;
- announcement approval rules;
- stale-audio behavior;
- database constraints;
- transaction boundaries;
- live ceremony safety.

## 1.3 Refactor strategy

Prefer:

- presentation changes;
- navigation changes;
- composition changes;
- derived workflow data;
- contextual grouping;
- progressive disclosure.

Avoid:

- deleting existing capabilities;
- rewriting working backend flows;
- changing URLs unnecessarily;
- giant one-shot CSS rewrites;
- duplicating existing import/QR/TTS/stage functionality.

## 1.4 Accessibility

Preserve:

- keyboard navigation;
- visible focus;
- semantic labels;
- screen-reader text where needed;
- no color-only status;
- reduced-motion support;
- accessible dialogs;
- accessible form errors.

## 1.5 Testing

Before declaring a task complete, run the task-specific checks.

For larger phases, also run:

```bash
docker compose exec -T app php artisan test
docker compose exec -T app vendor/bin/pint --test
npm run lint
npm run build
```

Run browser tests when the environment is configured:

```bash
npm run test:browser
```

or:

```bash
npx playwright test
```

---

# 2. FINAL PRODUCT ARCHITECTURE

GSS must use this mental model:

```text
PREPARE
   ↓
RUN
   ↓
REVIEW
```

The selected ceremony is the center of the experience.

Do NOT organize the everyday UI around internal modules.

---

# 3. FINAL STARTUP FLOW

Use role-aware startup behavior.

```text
OPEN GSS
   │
   ├── Scanner Operator ─────────────→ Scanner
   │
   ├── Stage Operator ───────────────→ Stage Control
   │
   ├── Technical Operator ───────────→ System / Technical Workspace
   │
   └── Administrator / Data Encoder
                    │
                    ▼
             Ceremony Resolver
                    │
        ┌───────────┼───────────┐
        │           │           │
      NONE         ONE        MULTIPLE
        │           │           │
        ▼           ▼           ▼
      CREATE      CONTINUE      CHOOSE
      CEREMONY    CEREMONY      CEREMONY
        │           │           │
        └───────────┴───────────┘
                    │
                    ▼
            CEREMONY WORKSPACE
```

Do not ask users to choose a ceremony on every page.

---

# 4. TWO DIFFERENT CEREMONY CONTEXTS

This distinction is mandatory.

## 4.1 Workspace ceremony

Definition:

> The ceremony currently being prepared or reviewed by an administrator/data encoder.

Used by:

- Overview;
- Graduates;
- Preparation;
- Reports;
- ceremony editing.

Recommended naming:

```text
workspaceCeremony
```

## 4.2 Live ceremony

Definition:

> The operationally active ceremony used by Scanner/Stage.

Existing live ceremony state must remain authoritative.

The existing operational `ceremonyContext` must NOT be replaced by workspace selection.

## 4.3 Rule

A selected workspace ceremony MUST NEVER silently change the live ceremony.

A live ceremony MUST NEVER be inferred only from the user's selected workspace.

---

# 5. CEREMONY LIFECYCLE — USER-FACING MODEL

Use the following display states:

```text
Draft
Preparing
Ready
Live
Completed
```

Do NOT modify the persisted `CeremonyStatus` enum just to obtain these labels unless a later dedicated task proves this is required.

Prefer deriving display state from existing ceremony status + preparation readiness.

Example:

```text
existing draft + incomplete preparation → Preparing
existing active → Live
existing completed → Completed
```

---

# 6. CEREMONY-FIRST NAVIGATION

## 6.1 Administrator

Primary:

```text
Overview
Graduates
Preparation
Run Ceremony
Reports
```

Secondary:

```text
Settings
```

## 6.2 Data Encoder

Primary:

```text
Overview
Graduates
Preparation
```

Do not show technical/system destinations.

## 6.3 Scanner Operator

Primary:

```text
Scanner
```

Optional:

```text
Help
Sign out
```

No admin sidebar.

## 6.4 Stage Operator

Primary:

```text
Stage Control
Stage Display
```

No admin sidebar.

## 6.5 Technical Operator

Primary:

```text
System Status
Devices
Logs
Backups
```

Do not expose everyday graduate-preparation navigation unless existing permissions explicitly justify it.

---

# 7. CEREMONY CREATION FLOW

Ceremony creation must remain short.

Required initial fields:

- Ceremony name
- Academic year
- Event date
- Venue, optional

Do not put the entire preparation process inside ceremony creation.

After successful creation, show:

```text
Your ceremony is ready to prepare.

[Start guided setup]

Skip setup and open workspace
```

The creation form creates the ceremony.

The preparation system handles everything after creation.

---

# 8. GUIDED PREPARATION FLOW

Default guided preparation steps:

```text
1. Graduate information
2. Photos
3. Announcements
4. QR Cards
5. Stage Design
6. Final Readiness
```

This flow is an orchestration layer.

It MUST reuse existing features.

Do NOT duplicate:

- CSV import engine;
- graduate forms;
- QR generation logic;
- announcement generation;
- approval workflow;
- stage design logic;
- readiness logic.

---

# 9. SKIP BEHAVIOR

There are two skip types.

## 9.1 Skip setup

Visible after ceremony creation.

Label:

```text
Skip setup and open workspace
```

Meaning:

- do not use guided preparation now;
- do not mark any task complete;
- open the Ceremony Workspace.

## 9.2 Skip for now

Visible inside eligible guided steps.

Label:

```text
Skip for now
```

Meaning:

- postpone this guided step;
- save the user's guidance preference;
- do not alter authoritative readiness;
- continue to next available step.

## 9.3 Required rule

A skipped step is NOT a completed step.

Never mark domain readiness as ready because the user clicked Skip.

---

# 10. DOMAIN READINESS VS GUIDED-SETUP STATE

These are separate systems.

## 10.1 Domain readiness

Derived from authoritative system data.

Examples:

```text
Graduate information ready
→ derive from actual graduate records/validation.

Photos ready
→ derive from actual graduate photo state.

Announcements ready
→ derive from actual announcement generation/approval state.

QR ready
→ derive from actual QR availability.

Stage design ready
→ derive from ceremony/stage-design configuration.
```

Do NOT store fake booleans such as:

```text
photos_completed = true
announcements_completed = true
```

as the source of truth.

## 10.2 Guided setup state

Stores only user guidance progress:

- current step;
- started;
- skipped/dismissed;
- skipped step keys;
- last visited step.

This may be persisted separately.

---

# 11. PREPARATION STATUS CONTRACT

Create one normalized backend representation for the frontend.

Recommended service:

```text
app/Services/CeremonyPreparationService.php
```

The service should return a structure equivalent to:

```json
{
  "ceremony": {
    "id": 15,
    "name": "First Grading Recognition",
    "academic_year": "2026-2027",
    "event_date": "2026-08-28"
  },
  "display_state": "preparing",
  "summary": {
    "ready_steps": 3,
    "total_steps": 6,
    "issue_count": 11
  },
  "steps": [
    {
      "key": "graduates",
      "label": "Graduate information",
      "state": "ready",
      "requirement": "required",
      "completed": 152,
      "total": 152,
      "issue_count": 0,
      "href": "/..."
    },
    {
      "key": "photos",
      "label": "Photos",
      "state": "needs_attention",
      "requirement": "optional",
      "completed": 144,
      "total": 152,
      "issue_count": 8,
      "href": "/..."
    }
  ],
  "recommended_next": {
    "key": "announcements",
    "label": "Review announcements",
    "description": "3 announcements need review.",
    "href": "/..."
  }
}
```

Exact array syntax may follow project conventions.

React must consume this normalized structure.

Do not duplicate readiness rules inside multiple React pages.

---

# 12. PREPARATION STEP STATES

Allowed user-facing states:

```text
not_started
in_progress
ready
needs_attention
blocked
optional
```

Guidance state may additionally include:

```text
skipped_for_now
```

But `skipped_for_now` belongs to guided setup metadata, not domain readiness.

---

# 13. PREPARATION REQUIREMENT TYPES

Each preparation step must declare one of:

```text
required
recommended
optional
```

Do not invent requirement rules.

Use existing business/safety requirements.

If existing rules are unclear, STOP and report.

---

# 14. PREPARATION DEPENDENCIES

Use explicit prerequisites.

Minimum expected dependency model:

```text
Ceremony
   ↓
Graduate information
   ├───────────────┐
   ↓               ↓
Photos         Announcements
   │               │
   └──────┬────────┘
          ↓
       QR Cards
          ↓
    Final Readiness
```

Do not enforce a hard dependency unless the underlying feature genuinely requires it.

Example:

- QR codes require graduate records.
- Announcements require graduate identity/wording.

Photos should not automatically block announcement preparation unless existing rules require that.

---

# 15. CONTINUE PREPARATION ALGORITHM

Create a deterministic next-step resolver.

Recommended service:

```text
app/Services/CeremonySetupNavigator.php
```

Algorithm:

```text
1. Load normalized preparation steps.
2. Remove steps already ready.
3. Remove steps whose prerequisites are currently blocked.
4. Prefer unresolved steps the current user has NOT skipped.
5. Respect explicit priority order.
6. If none remain, revisit skipped unresolved steps.
7. If all required steps are ready, return readiness or null.
```

Recommended priority:

```text
graduates
photos
announcements
qr
stage_design
readiness
```

Adjust only if real dependencies require it.

---

# 16. WORKSPACE RESOLVER

Recommended service:

```text
app/Services/CeremonyWorkspaceResolver.php
```

Responsibilities:

- resolve last selected ceremony for preparation roles;
- ensure user may access the ceremony;
- determine whether to:
  - create;
  - continue;
  - choose;
- never change live operational ceremony.

Resolution behavior:

```text
0 relevant ceremonies
→ create flow

1 unfinished/relevant ceremony
→ open it

multiple relevant ceremonies
→ ceremony chooser

remembered accessible ceremony
→ prefer it unless completed/invalid according to product rules
```

---

# 17. WORKSPACE SELECTION PERSISTENCE

Prefer explicit URL ceremony context.

Recommended workspace route pattern:

```text
/admin/ceremonies/{ceremony}/workspace
```

or another route consistent with existing route structure.

Use a small user/session preference only to remember the last selected workspace.

Do not rely on hidden session state as the only identifier for destructive or important operations.

---

# 18. GUIDED SETUP PERSISTENCE

Do NOT reuse role onboarding progress for ceremony setup.

Recommended new model:

```text
CeremonySetupProgress
```

Recommended table:

```text
ceremony_setup_progress
```

Fields:

```text
id
user_id
ceremony_id
status
current_step
skipped_steps
created_at
updated_at
```

Recommended `status` values:

```text
not_started
in_progress
dismissed
completed
```

Recommended `skipped_steps`:

JSON array of step keys.

Example:

```json
["photos", "stage_design"]
```

Guided progress is user-specific.

Domain readiness remains ceremony-wide.

---

# 19. NEW/REFACTORED BACKEND COMPONENTS

Expected additions:

```text
app/Services/CeremonyPreparationService.php
app/Services/CeremonyWorkspaceResolver.php
app/Services/CeremonySetupNavigator.php
app/Models/CeremonySetupProgress.php
```

Expected controller additions or equivalents:

```text
app/Http/Controllers/Admin/CeremonyWorkspaceController.php
app/Http/Controllers/Admin/CeremonySetupController.php
app/Http/Controllers/Admin/CeremonyWorkspaceSelectionController.php
```

Exact controller grouping may follow current project conventions.

Do not create redundant controllers if existing ceremony controllers can cleanly own a responsibility.

---

# 20. NEW/REFACTORED FRONTEND COMPONENTS

Expected reusable components:

```text
resources/js/components/Ceremony/CeremonyChooser.jsx
resources/js/components/Ceremony/CeremonyWorkspaceHeader.jsx
resources/js/components/Ceremony/CeremonySwitcher.jsx

resources/js/components/Preparation/PreparationChecklist.jsx
resources/js/components/Preparation/PreparationStep.jsx
resources/js/components/Preparation/RecommendedNextAction.jsx
resources/js/components/Preparation/ReadinessSummary.jsx

resources/js/components/Setup/GuidedSetupLayout.jsx
resources/js/components/Setup/GuidedSetupProgress.jsx
resources/js/components/Setup/GuidedSetupFooter.jsx
```

Do not create components solely to wrap one line of JSX.

Follow existing naming/structure conventions if there is an established equivalent.

---

# 21. NEW/REFACTORED PAGES

Expected pages or equivalents:

```text
resources/js/pages/Admin/Ceremonies/Workspace.jsx
resources/js/pages/Admin/Ceremonies/Choose.jsx
resources/js/pages/Admin/Ceremonies/Setup.jsx
resources/js/pages/Admin/Preparation/Index.jsx
```

Reuse current pages where it reduces migration risk.

---

# 22. CEREMONY WORKSPACE UI

The workspace is the main preparation home.

Required sections:

```text
Ceremony header
Preparation summary
Recommended next action
Preparation checklist
Ceremony-day actions
```

Example:

```text
FIRST GRADING RECOGNITION
2026-2027 · August 28, 2026
Preparing

Preparation

Graduate information
152 of 152 ready                    READY

Photos
144 of 152 ready               8 MISSING

Announcements
149 approved               3 NEED REVIEW

QR Cards
152 generated                    READY

Stage Design
Graduation Blue selected         READY

Recommended next step

3 announcements need review.

[Continue preparation]
```

Do not build a decorative KPI-heavy dashboard.

Use structured, readable workflow rows.

---

# 23. PREPARATION PROGRESS DISPLAY

Primary progress text:

```text
4 of 6 preparation areas ready
```

Optional secondary percentage:

```text
67%
```

Issue count is more important than percentage.

Never imply that equal-weight steps represent equal risk.

---

# 24. PREPARATION HUB

Inside the selected ceremony, `Preparation` should contain:

```text
Overview
Photos
Announcements
QR Cards
Stage Design
Readiness
```

CSV import belongs under:

```text
Graduates → Import graduates
```

Pronunciation belongs under:

```text
Preparation → Announcements
```

Do not expose all preparation subfeatures in the global sidebar.

---

# 25. GRADUATES UX

Default graduate list should prioritize:

```text
Sequence
Graduate
Program
Preparation status
Action
```

Primary row action:

```text
Open
```

Optional:

```text
Edit
```

Move specialized actions into the graduate detail page.

Always-visible filters:

```text
Search
Preparation status
Ceremony only when context requires
```

Advanced filters go under:

```text
More filters
```

---

# 26. IMPORT GRADUATES UX

Keep the existing robust flow.

User-facing labels:

```text
1. Upload file
2. Match columns
3. Review
4. Finished
```

Step 1 target:

```text
Import graduates

Add graduates to:
First Grading Recognition        [Change]

Upload graduate list
[ Choose CSV ]

[ Download template ]

Photos & recorded announcements (optional)
[ Add ZIP package ]

[ Continue ]

File requirements ▾
```

Hide technical details under `File requirements`.

Do not change import validation or atomic behavior.

---

# 27. PHOTOS UX

Photos should be shown as a preparation task.

Example:

```text
Photos

144 of 152 graduates have photos
8 missing

[View missing photos]
```

Do not require users to understand media storage paths.

If bulk upload exists, expose it with human wording.

Internal filename rules belong in contextual help.

---

# 28. ANNOUNCEMENTS UX

User-facing title:

```text
Announcements
```

Normal preparation summary:

```text
147 ready
3 need pronunciation review
2 need approval

[Review next issue]
```

Primary workflow:

```text
Fix wording/pronunciation
Generate announcements
Review generated announcements
Approved / Ready
```

Advanced administrator settings contain:

- provider configuration;
- Azure/Kokoro details;
- voice comparison configuration;
- template/version internals;
- global pronunciation administration.

Do not remove these capabilities.

Reduce their prominence.

---

# 29. QR UX

QR Cards belong to Preparation.

Example:

```text
QR Cards

152 graduates
152 QR cards ready

[Print QR cards]
[Regenerate selected]
```

Do not expose QR generation as a global module.

If graduate records do not yet exist:

```text
QR Cards
Blocked — Add graduates first
```

---

# 30. STAGE DESIGN UX

Stage Design belongs to Preparation.

Example:

```text
Stage Design

Graduation Blue
Selected

[Preview]
[Change design]
```

Advanced design management may remain under Settings for administrators.

---

# 31. FINAL READINESS UX

Readiness page must group:

```text
Required
Needs attention
Recommended
```

Example:

```text
Ceremony readiness

Required
✓ Graduate information
✓ QR configuration

Needs attention
⚠ 3 announcements need review

Recommended
○ Venue device check

[Review issues]
```

Do not create new operational blockers unless existing business rules already require them.

---

# 32. RUN CEREMONY UX

When users choose `Run Ceremony`, show only operational launch actions.

Example:

```text
FIRST GRADING RECOGNITION

Ceremony day

[Open Scanner]
[Open Stage Control]
[Open Stage Display]
```

Only show actions allowed by current permissions.

Operational pages remain dedicated.

---

# 33. SCANNER UX RULES

Preserve existing Scanner behavior.

Required:

- USB scanner focus;
- camera option if supported;
- accepted/rejected feedback;
- duplicate-scan protection;
- manual fallback;
- server/realtime state;
- focus recovery.

UI priority:

```text
1. Ready / connection state
2. Scan input/capture
3. Latest result
4. Recent activity
5. Manual fallback
```

No admin sidebar.

At 1366x768:

- scan result above fold;
- next scan possible without scrolling.

---

# 34. STAGE CONTROL UX RULES

Preserve stage logic.

UI priority:

```text
1. Current graduate
2. Next graduate
3. Main advance action
4. Queue
5. Recovery controls
```

Separate:

```text
normal actions
```

from:

```text
recovery / holding / pause / exceptional actions
```

No unrelated admin navigation.

---

# 35. COMPLETED CEREMONY UX

Completed ceremony default view:

```text
First Grading Recognition
Completed August 28, 2026

152 graduates presented

[View ceremony summary]
[View graduates]
[View activity]
[Export report]
```

Preparation actions become secondary or read-only according to existing rules.

Do not make completed ceremonies look like active preparation workspaces.

---

# 36. SHELL MODES

Refactor application shell into context-aware modes.

Preferred API:

```jsx
<AppShell mode="preparation">
<AppShell mode="operational">
<AppShell mode="technical">
```

Equivalent architecture is acceptable.

## Preparation shell

Show:

- brand;
- ceremony context;
- simple role navigation;
- user menu;
- relevant alerts.

Hide healthy:

- network status;
- Reverb status;
- database/service diagnostics;
- global bottom status bar.

## Operational shell

Show:

- ceremony operational state;
- critical connection state;
- operation-specific controls.

## Technical shell

Show detailed:

- services;
- network;
- devices;
- backups;
- diagnostics.

---

# 37. STATUS VISIBILITY RULE

Normal preparation/admin pages:

Show technical state only when it affects the task.

Example:

Do NOT show:

```text
Network online
Live connected
Database healthy
```

on the Import Graduates page when everything is healthy.

If a failure blocks saving/importing, show a task-specific error.

---

# 38. PAGE HEADER RULE

Normal page:

```text
Title
Short description

[Primary Action] [Optional Secondary]
```

Maximum visible header actions:

- 1 primary;
- 1 secondary.

Everything else goes into:

- page content;
- overflow;
- advanced settings.

Operational pages are exempt.

---

# 39. TERMINOLOGY STANDARD

Use the right-hand terms in user-facing UI.

| Avoid | Use |
|---|---|
| CSV Import | Import graduates |
| Inspect import | Continue |
| Destination ceremony | Add graduates to |
| Import summary | Finished |
| Media package | Photos & audio |
| Audio readiness | Announcements |
| Voice generation | Generate announcements |
| Announcement candidate | Generated announcement |
| Operational logs | Activity & logs |
| Ceremony readiness | Preparation status |
| Stage event history | Stage activity |
| Device connections | Devices |
| Announcement batches | Generate announcements / Batch generation |

Do not rename internal classes/routes solely for wording.

---

# 40. ERROR MESSAGE STANDARD

Every error must answer:

1. What happened?
2. What was affected?
3. Was anything saved?
4. What should the user do next?

Bad:

```text
Something went wrong.
```

Good:

```text
The graduate list was checked, but 3 rows are missing student numbers.
No graduates were imported.
Fix the highlighted rows and upload the file again.
```

---

# 41. PROGRESSIVE DISCLOSURE STANDARD

## Level 1 — Always visible

Only what is required now.

Examples:

- ceremony;
- next action;
- issue count;
- primary button.

## Level 2 — Expandable

Examples:

- file requirements;
- advanced filters;
- detailed explanations.

## Level 3 — Advanced settings

Examples:

- provider configuration;
- device certificates;
- backup internals;
- audit logs;
- template versions.

---

# 42. ONBOARDING VS CEREMONY SETUP

Keep role onboarding separate.

Role onboarding teaches the app.

Ceremony guided setup prepares one ceremony.

Do not merge their persistence models.

After refactor, simplify role onboarding.

It should not be required for normal use.

---

# 43. CSS STRATEGY

Do not rewrite the large stylesheet in one task.

Target structure:

```text
resources/css/
  app.css

  foundation/
    tokens.css
    reset.css
    typography.css

  shell/
    app-shell.css
    navigation.css
    toolbar.css

  components/
    buttons.css
    forms.css
    tables.css
    status.css
    dialogs.css
    empty-state.css

  workflows/
    ceremony.css
    graduates.css
    import.css
    preparation.css
    announcements.css

  operations/
    scanner.css
    stage-control.css
    stage-display.css

  utilities/
    accessibility.css
    responsive.css
```

Migrate only after workflow layouts stabilize.

---

# 44. PHASE ORDER — DO NOT REORDER WITHOUT A REASON

Execute in this order:

```text
Phase 0  Baseline and regression safety
Phase 1  Preparation domain contract
Phase 2  Workspace ceremony context
Phase 3  Ceremony creation flow
Phase 4  Ceremony Workspace
Phase 5  Guided setup + skip/resume
Phase 6  Graduate workflow integration
Phase 7  Preparation hub integration
Phase 8  Announcements UX
Phase 9  Global navigation/shell
Phase 10 Run Ceremony UX
Phase 11 Completed ceremony UX
Phase 12 Onboarding simplification
Phase 13 CSS modularization
Phase 14 Browser UX regression suite
Phase 15 Human usability validation
```

---

# 45. PHASE 0 — BASELINE

## P0-T1 — Prepare branch

```bash
git fetch origin
git switch feature/windows-client-distribution
git pull
git switch -c feature/gss-usability-workflow-refactor
```

If branch exists:

```bash
git switch feature/gss-usability-workflow-refactor
git pull
```

## P0-T2 — Baseline screenshots

Capture:

- `/admin`
- ceremony list;
- ceremony detail;
- graduate list;
- import steps;
- voice generation;
- pronunciation;
- scanner;
- stage control;
- stage display;
- backups;
- device connections.

Viewports:

```text
1366x768
1536x864
```

## P0-T3 — Baseline verification

```bash
docker compose up -d --build
docker compose exec -T app php artisan test
docker compose exec -T app vendor/bin/pint --test
npm run lint
npm run build
```

Browser:

```bash
npm run test:browser
```

if configured.

## Done

No product behavior changed.

---

# 46. PHASE 1 — PREPARATION DOMAIN CONTRACT

## Goal

Create one backend source for preparation readiness and next-action logic.

## Files to inspect

```text
app/Http/Controllers/Admin/AdminDashboardController.php
app/Models/Ceremony.php
app/Models/Student.php
app/Enums/AudioReadinessStatus.php
app/Enums/CeremonyStatus.php
app/Services/
resources/js/pages/Admin/Index.jsx
```

## Create

```text
app/Services/CeremonyPreparationService.php
app/Services/CeremonySetupNavigator.php
```

## Requirements

`CeremonyPreparationService` must:

- accept a Ceremony;
- calculate step status from real data;
- return normalized step structure;
- not mutate data.

`CeremonySetupNavigator` must:

- accept normalized preparation + user setup metadata;
- return recommended next step;
- not mutate domain readiness.

## Tests

Create unit/feature tests for:

- no graduates;
- graduates ready;
- missing photos;
- announcement issues;
- QR blocked before graduates;
- all required steps ready;
- skipped step priority behavior.

## Verify

```bash
docker compose exec -T app php artisan test
docker compose exec -T app vendor/bin/pint --test
```

---

# 47. PHASE 2 — WORKSPACE CEREMONY CONTEXT

## Goal

Separate preparation selection from live operational context.

## Inspect

```text
app/Http/Middleware/HandleInertiaRequests.php
app/Enums/UserRole.php
routes/web.php
resources/js/components/AppShell.jsx
resources/js/pages/Dashboard.jsx
```

## Create/reuse

```text
app/Services/CeremonyWorkspaceResolver.php
```

Potential controller:

```text
CeremonyWorkspaceSelectionController
```

## Requirements

- selected workspace ceremony is independent of live ceremony;
- switch ceremony action exists;
- operational role home routing unchanged;
- preparation roles can continue last selected ceremony;
- invalid/inaccessible remembered ceremony is ignored safely.

## Tests

- selecting workspace ceremony does not activate it;
- changing workspace ceremony does not affect Scanner live ceremony;
- unauthorized ceremony cannot be selected;
- multiple ceremonies trigger chooser logic;
- one usable ceremony can open directly.

---

# 48. PHASE 3 — CEREMONY CREATION FLOW

## Goal

Make new ceremony setup short.

## Inspect

```text
resources/js/pages/Admin/Ceremonies/Create.jsx
resources/js/pages/Admin/Ceremonies/Edit.jsx
app/Http/Controllers/Admin/CeremonyController.php
```

## UI

Required initial fields:

```text
Ceremony name
Academic year
Event date
Venue
```

Keep any truly required existing field.

Move nonessential configuration to later settings.

After create:

```text
[Start guided setup]
Skip setup and open workspace
```

## Do not

- change ceremony safety rules;
- remove required backend validation;
- activate ceremony automatically unless existing behavior already does this.

---

# 49. PHASE 4 — CEREMONY WORKSPACE

## Goal

Make the selected ceremony the main home.

## Create/reuse

```text
resources/js/pages/Admin/Ceremonies/Workspace.jsx
resources/js/components/Ceremony/CeremonyWorkspaceHeader.jsx
resources/js/components/Preparation/PreparationChecklist.jsx
resources/js/components/Preparation/PreparationStep.jsx
resources/js/components/Preparation/RecommendedNextAction.jsx
```

Controller:

```text
CeremonyWorkspaceController
```

## Required data

- ceremony;
- display state;
- preparation summary;
- preparation steps;
- recommended next action;
- authorized ceremony-day actions.

## UI

Do not use a crowded admin dashboard.

Use:

```text
Header
Preparation rows
Recommended next action
Ceremony day actions
```

## Acceptance

A first-time user can answer:

```text
What ceremony?
What remains?
What should I do next?
```

without leaving the page.

---

# 50. PHASE 5 — GUIDED SETUP + SKIP/RESUME

## Goal

Add optional, resumable guidance.

## Create

Migration/model:

```text
ceremony_setup_progress
CeremonySetupProgress
```

Frontend:

```text
GuidedSetupLayout
GuidedSetupProgress
GuidedSetupFooter
```

Controller:

```text
CeremonySetupController
```

## Required actions

```text
Start guided setup
Continue
Back
Skip for now
Exit to workspace
Resume setup
```

## Rules

- save current step;
- save skipped step keys;
- do not mark domain readiness complete;
- if real work completes a skipped step, readiness shows ready;
- user may exit at any time;
- resume begins at next unresolved appropriate step.

## Tests

- skip setup;
- skip one step;
- skip all eligible steps;
- resume;
- another user sees true ceremony readiness;
- readiness becoming invalid reappears automatically.

---

# 51. PHASE 6 — GRADUATE WORKFLOW

## Goal

Make Graduates ceremony-aware and low-density.

## Inspect

```text
resources/js/pages/Admin/Students/Index.jsx
resources/js/pages/Admin/Students/Import/*
resources/js/pages/Admin/Students/*
```

## Changes

- selected ceremony should be obvious;
- remove redundant ceremony dropdown when context is already fixed;
- allow explicit Change/Switch where appropriate;
- simplify default table;
- add combined preparation status;
- make Import Graduates easy to discover.

## Import labels

```text
Upload file
Match columns
Review
Finished
```

CTA:

```text
Continue
```

Technical requirements under disclosure.

## Do not

Change import engine or validation.

---

# 52. PHASE 7 — PREPARATION HUB

## Goal

Group everyday preparation tasks.

## Create/reuse

```text
resources/js/pages/Admin/Preparation/Index.jsx
```

Rows:

```text
Photos
Announcements
QR Cards
Stage Design
Readiness
```

Each row:

- status;
- issue count;
- short description;
- action.

No decorative dashboard-card explosion.

---

# 53. PHASE 8 — ANNOUNCEMENTS UX

## Goal

Hide internal TTS complexity from everyday users.

## Inspect

```text
resources/js/pages/Admin/VoiceGeneration/Index.jsx
resources/js/pages/Admin/PronunciationRules/*
resources/js/pages/Admin/Ceremonies/AnnouncementSettings.jsx
resources/js/pages/Admin/Ceremonies/AnnouncementBatches.jsx
```

## User-facing workflow

```text
Announcements
→ Pronunciation issues
→ Generate
→ Review
→ Ready
```

## Administrator advanced area

Keep:

- provider configuration;
- voice comparison;
- provider health;
- template versions;
- common pronunciation dictionary.

## Acceptance

Data Encoder does not need to understand:

```text
provider registry
candidate lifecycle
immutable template versions
```

to complete normal preparation.

---

# 54. PHASE 9 — NAVIGATION AND SHELL

## Goal

Replace the old module-first structure only after destination workflows exist.

## Inspect

```text
resources/js/components/AppShell.jsx
resources/js/components/appNavigation.js
resources/js/components/SystemStatusBar.jsx
resources/js/components/Admin/AdminLayout.jsx
resources/js/components/CommandPalette.jsx
```

## Implement

Primary ceremony nav:

```text
Overview
Graduates
Preparation
Run Ceremony
Reports
```

Secondary:

```text
Settings
```

## Remove from primary nav

Examples:

```text
CSV Import
QR Codes
Voice Generation
Pronunciation Dictionary
Announcement Batches
Scan Logs
Stage Events
Audit Logs
Backups
Device Connections
```

These remain reachable through grouped areas based on role/permission.

## System status

Normal preparation pages:

- no bottom status bar;
- no duplicate healthy connection badges.

Operational/technical modes retain necessary status.

---

# 55. PHASE 10 — RUN CEREMONY UX

## Goal

Create a clear transition from preparation to operation.

## Run Ceremony page

Show:

```text
Readiness summary

[Open Scanner]
[Open Stage Control]
[Open Stage Display]
```

Only authorized actions.

## Scanner/Stage

Refine layout only.

Do not change operational state machines.

---

# 56. PHASE 11 — COMPLETED CEREMONY UX

## Goal

Make historical ceremonies review-oriented.

Default completed page:

```text
Completed date
Graduate count
Summary
Activity
Reports
Export
```

Move preparation actions out of primary focus.

Respect existing edit/delete protection.

---

# 57. PHASE 12 — ONBOARDING SIMPLIFICATION

## Inspect

```text
resources/js/components/OnboardingGuide.jsx
resources/js/components/onboardingTours.js
app/Services/OnboardingWorkspace.php
```

## New role guidance

Administrator:

```text
Create/select ceremony
Review preparation
Manage operators/devices
Run readiness check
```

Data Encoder:

```text
Open ceremony
Import/edit graduates
Resolve preparation issues
Prepare announcements
```

Scanner:

```text
Verify connection
Test scan
Use fallback
```

Stage:

```text
Verify live state
Advance queue
Use recovery controls
```

Technical:

```text
Verify devices
Review logs
Verify backup
```

Do not make onboarding necessary to discover ordinary workflow.

---

# 58. PHASE 13 — CSS MODULARIZATION

Only start after major layouts are stable.

Migration order:

```text
1. tokens/reset
2. shell/navigation
3. common components
4. ceremony/workspace
5. graduates/import
6. preparation
7. announcements
8. scanner
9. stage
10. remaining legacy CSS
```

For duplicated selectors:

1. find all definitions;
2. determine effective cascade;
3. move intended final rule;
4. verify visually;
5. remove duplicate.

Do not bulk-delete.

---

# 59. PHASE 14 — BROWSER UX REGRESSION SUITE

Use existing Playwright + Axe.

Add scenarios below.

## 59.1 Ceremony creation

```text
Admin login
Create ceremony
Choose Start guided setup
Verify first preparation step
```

## 59.2 Skip setup

```text
Create ceremony
Skip setup
Workspace opens
No steps falsely marked ready
```

## 59.3 Skip step

```text
Start guided setup
Skip Photos
Continue to next eligible step
Workspace shows Photos unresolved
```

## 59.4 Resume

```text
Exit setup
Reopen ceremony
Resume
Correct unresolved step opens
```

## 59.5 Data Encoder import

```text
Open ceremony
Graduates
Import graduates
Upload
Map
Review
Finish
Return to graduates
```

## 59.6 Workspace vs live ceremony

```text
Select Ceremony A for preparation
Live Ceremony B remains operational
Scanner/Stage still use B
```

## 59.7 Operational roles

Scanner login:

- scanner opens directly;
- no ceremony setup UI.

Stage login:

- stage control opens directly;
- no preparation UI.

## 59.8 Accessibility

Run Axe on:

- ceremony chooser;
- create ceremony;
- workspace;
- guided setup;
- graduates;
- import;
- preparation;
- announcements;
- run ceremony.

---

# 60. VIEWPORT ACCEPTANCE

Required:

```text
1366x768
1536x864
```

Normal pages:

- no horizontal scroll;
- navigation fits;
- primary action visible.

Scanner:

- scan area/results visible above fold.

Stage Control:

- current;
- next;
- main action;
- queue access

visible without unnecessary scrolling.

---

# 61. HUMAN USABILITY TEST

Use a nontechnical tester.

Give only:

- ceremony information;
- graduate CSV;
- photos;
- normal ceremony preparation goal.

Instruction:

```text
Prepare this graduation using GSS.
```

Do not guide.

Record:

- first wrong click;
- hesitation;
- misunderstood labels;
- backtracking;
- help requests;
- completion time;
- failures;
- recovery behavior.

Target:

A new Data Encoder should be able to:

```text
create/select ceremony
import graduates
understand remaining preparation
continue work
```

without developer instruction.

---

# 62. ATOMIC AGENT TASK FORMAT

Every small-agent prompt should use this exact structure:

```text
TASK ID:
<phase-task id>

OBJECTIVE:
<one narrow result>

BASELINE:
feature/gss-usability-workflow-refactor

READ:
AGENTS.md
GSS_CEREMONY_FIRST_UX_WORKFLOW_REFACTOR_V2.md sections:
0, 1, 2, <task section>

FILES TO INSPECT:
<exact paths>

FILES ALLOWED TO CHANGE:
<exact paths>

IMPLEMENT:
1.
2.
3.

DO NOT:
1.
2.
3.

ACCEPTANCE:
- ...
- ...
- ...

VERIFY:
<exact commands>

OUTPUT:
1. Changed files
2. Verification result
3. Blocker only if blocked
```

---

# 63. STOP CONDITIONS

An agent MUST stop instead of guessing when:

1. required data is absent;
2. a change would alter authorization semantics;
3. a workspace change appears to affect live ceremony selection;
4. existing safety rules conflict with this plan;
5. a required/optional preparation rule is unclear;
6. duplicated CSS behavior cannot be safely determined;
7. route behavior differs from assumptions;
8. Scanner/Stage logic must change beyond UI presentation;
9. tests expose an undocumented invariant;
10. implementation requires a database/business change not explicitly covered.

Use this blocker format:

```text
BLOCKED:
<exact issue>

WHY:
<why this cannot be safely guessed>

NEEDED:
<minimum decision/data required>

FILES:
<paths>

CHANGES MADE:
yes/no
```

---

# 64. EXAMPLE AGENT TASK — PREPARATION SERVICE

```text
TASK ID:
P1-T1

OBJECTIVE:
Create CeremonyPreparationService that returns normalized read-only preparation status.

FILES TO INSPECT:
app/Models/Ceremony.php
app/Models/Student.php
app/Enums/AudioReadinessStatus.php
existing readiness/query services
tests related to readiness

FILES ALLOWED TO CHANGE:
app/Services/CeremonyPreparationService.php
tests/Unit/CeremonyPreparationServiceTest.php
tests/Feature/... only if required

IMPLEMENT:
1. Accept Ceremony.
2. Return ceremony summary.
3. Return steps for graduates, photos, announcements, QR, stage design, readiness.
4. Derive states from actual database/domain state.
5. Include issue_count and href metadata where safe.
6. Do not mutate database state.

DO NOT:
1. Create wizard completion booleans.
2. Change existing models.
3. Change authorization.
4. Change stage/scanner logic.

ACCEPTANCE:
- no graduates → graduates not ready;
- actual graduate data changes status automatically;
- no mutation occurs;
- output shape is deterministic.

VERIFY:
docker compose exec -T app php artisan test
docker compose exec -T app vendor/bin/pint --test
```

---

# 65. EXAMPLE AGENT TASK — SETUP SKIP

```text
TASK ID:
P5-T3

OBJECTIVE:
Implement Skip for now for guided setup without altering domain readiness.

FILES TO INSPECT:
app/Models/CeremonySetupProgress.php
app/Http/Controllers/Admin/CeremonySetupController.php
resources/js/pages/Admin/Ceremonies/Setup.jsx
resources/js/components/Setup/GuidedSetupFooter.jsx

FILES ALLOWED TO CHANGE:
same files
relevant focused tests

IMPLEMENT:
1. Add Skip for now action to eligible steps.
2. Persist step key in skipped_steps.
3. Advance using CeremonySetupNavigator.
4. Do not mark preparation step ready.
5. Workspace must still show real unresolved issue.
6. Resume must revisit skipped unresolved work after non-skipped work is exhausted.

DO NOT:
1. modify Ceremony readiness directly;
2. update QR/audio/photo state;
3. bypass required operational validation.

ACCEPTANCE:
- clicking Skip changes only guidance progress;
- preparation service output remains based on real data;
- resume works;
- another user sees unchanged real readiness.

VERIFY:
docker compose exec -T app php artisan test
npm run lint
npm run build
```

---

# 66. EXAMPLE AGENT TASK — WORKSPACE CEREMONY SAFETY

```text
TASK ID:
P2-T2

OBJECTIVE:
Allow preparation users to select a workspace ceremony without changing the live ceremony.

FILES TO INSPECT:
app/Http/Middleware/HandleInertiaRequests.php
app/Enums/UserRole.php
app/Models/Ceremony.php
routes/web.php
existing active ceremony tests

FILES ALLOWED TO CHANGE:
workspace resolver/controller
focused route/middleware changes
focused tests

IMPLEMENT:
1. Store/resolve workspace ceremony separately.
2. Expose workspace ceremony props separately from existing ceremonyContext.
3. Keep ceremonyContext operational behavior unchanged.
4. Add tests proving workspace selection does not activate ceremony.

DO NOT:
1. reuse active ceremony status as the workspace-selection mechanism;
2. change live ceremony;
3. alter scanner/stage route behavior.

ACCEPTANCE:
- workspace A can be selected while live B remains live;
- Scanner uses B;
- Stage uses B;
- preparation pages use A.

VERIFY:
docker compose exec -T app php artisan test
```

---

# 67. EXAMPLE AGENT TASK — CEREMONY WORKSPACE UI

```text
TASK ID:
P4-T1

OBJECTIVE:
Build the ceremony workspace using normalized preparation data.

FILES TO INSPECT:
resources/js/pages/Admin/Index.jsx
resources/js/pages/Admin/Ceremonies/Show.jsx
resources/js/components/Admin/PageHeader.jsx
new preparation service props

FILES ALLOWED TO CHANGE:
resources/js/pages/Admin/Ceremonies/Workspace.jsx
resources/js/components/Ceremony/*
resources/js/components/Preparation/*
relevant CSS only

IMPLEMENT:
1. Show ceremony name/date/state.
2. Render preparation rows.
3. Show issue counts.
4. Render one Recommended next action.
5. Show authorized ceremony-day actions.
6. Keep visual density low.

DO NOT:
1. recreate readiness rules in React;
2. show full technical status;
3. add decorative metrics unrelated to action.

ACCEPTANCE:
- user can identify remaining work immediately;
- one primary next action;
- no horizontal scroll at 1366x768.

VERIFY:
npm run lint
npm run build
relevant Playwright test
```

---

# 68. EXAMPLE AGENT TASK — NAVIGATION

```text
TASK ID:
P9-T1

OBJECTIVE:
Replace module-first admin navigation with ceremony-first high-level navigation.

FILES TO INSPECT:
resources/js/components/appNavigation.js
resources/js/components/AppShell.jsx
resources/js/components/CommandPalette.jsx
role ability props

FILES ALLOWED TO CHANGE:
same files
focused tests

IMPLEMENT:
Administrator:
Overview
Graduates
Preparation
Run Ceremony
Reports
Settings

Data Encoder:
Overview
Graduates
Preparation

Scanner:
dedicated operational UI

Stage:
dedicated operational UI

Technical:
System Status
Devices
Logs
Backups

DO NOT:
1. remove backend routes;
2. weaken authorization;
3. delete deep pages;
4. expose every Preparation subpage globally.

ACCEPTANCE:
- primary nav matches role;
- deep capabilities remain reachable contextually;
- sidebar does not overflow at 1366x768.

VERIFY:
npm run lint
npm run build
npx playwright test <focused spec>
```

---

# 69. COMMIT STRATEGY

Use one logical task per commit.

Examples:

```text
refactor: add ceremony preparation status service
feat: add workspace ceremony selection
feat: add ceremony workspace
feat: add resumable guided ceremony setup
refactor: simplify graduate preparation flow
refactor: group preparation navigation
refactor: simplify announcement workflow
refactor: add preparation shell mode
test: cover workspace and live ceremony separation
test: cover guided setup skip and resume
```

Do not combine several phases in one commit.

---

# 70. PULL REQUEST REQUIREMENTS

PR description must include:

```text
What changed
Why
Architecture impact
UX impact
Database/migration changes
Authorization impact
Verification commands
Screenshots
Remaining hardware/manual checks
```

If a migration exists, explicitly document it.

---

# 71. FINAL UX ACCEPTANCE CRITERIA

The full refactor is complete only when all are true.

## Startup

- preparation roles get ceremony-first startup;
- operational roles go directly to operational screens;
- users are not repeatedly asked to select the same ceremony.

## Workspace

- selected ceremony is clear;
- preparation status is clear;
- next action is clear;
- users can switch ceremony intentionally.

## Guided setup

- optional;
- resumable;
- Skip setup available;
- Skip for now available where safe;
- skipping does not mark work ready.

## Preparation

- graduates;
- photos;
- announcements;
- QR;
- stage design;
- readiness

are understandable without module knowledge.

## Navigation

Administrator:

```text
≤ 6 high-level destinations
```

Data Encoder:

```text
normally 3 high-level destinations
```

Operational roles:

```text
no full admin navigation
```

## Import

- reachable within 2 intentional actions;
- technical requirements hidden until needed;
- validation remains precise.

## Announcements

Normal preparation does not require understanding provider internals.

## Scanner

- scan area above fold;
- accepted/rejected state obvious;
- focus behavior preserved.

## Stage Control

- current/next/main action visible;
- recovery controls distinct.

## Technical status

Healthy system details are hidden from normal preparation screens.

## Accessibility

- Axe covered pages have zero violations;
- keyboard operation works;
- focus visible;
- status not color-only.

## Regression

Existing critical domain behavior remains intact.

---

# 72. FINAL SYSTEM EXPERIENCE

Target user story:

```text
I open GSS.

If I am preparing a ceremony, GSS helps me select or create it.

I immediately see what still needs work.

If I am new, I can use guided setup.

If I am experienced, I can skip setup and work directly.

If I do not have a file/photo/announcement yet, I can skip it for now.

GSS remembers where I stopped.

The system never pretends skipped work is complete.

When I return, Continue preparation takes me to the next useful task.

When preparation is ready, I can open ceremony operations.

Scanner and Stage Control stay focused and safe.

After the event, the ceremony becomes a review/reporting workspace.
```

---

# 73. MASTER PRINCIPLE

Every UI decision must satisfy:

> Show users what they need for the task they are doing now, keep the selected ceremony as the working context, guide beginners without blocking experienced users, and never hide or weaken operational safety.

This document supersedes the earlier GSS usability plan wherever the two conflict.

The new authoritative architecture is:

```text
Ceremony Resolver
→ Ceremony Workspace
→ Optional Guided Setup
→ Preparation Tasks
→ Final Readiness
→ Run Ceremony
→ Completed Ceremony Review
```
