# GSS Usability & Workflow Refactor
## AI-Agent Execution Playbook

**Repository:** `Aracena18/connict_gss`
**Baseline branch:** `feature/windows-client-distribution`
**Recommended working branch:** `feature/gss-usability-workflow-refactor`
**Primary goal:** Make the Graduation Stage System easy to use for non-technical daily users such as teachers, registrars, data encoders, ceremony operators, and school staff without weakening existing safety, authorization, realtime behavior, or recovery guarantees.

---

# 0. EXECUTION CONTRACT FOR AI AGENTS

This document is written so that small implementation agents can execute tasks directly with minimal reasoning.

## 0.1 Mandatory rules

Every agent MUST follow these rules:

1. Read `AGENTS.md` before changing code.
2. Use `feature/windows-client-distribution` as the source baseline unless a newer approved refactor branch already exists.
3. Do not rewrite the backend architecture.
4. Do not change database rules, authorization rules, transactional behavior, realtime behavior, recovery behavior, or ceremony safety unless this document explicitly requires it.
5. PostgreSQL remains authoritative.
6. HTTP remains the command and recovery path.
7. Reverb/Echo remains the committed realtime update path.
8. Do not add polling.
9. Do not add cloud dependencies.
10. Do not add external CDNs.
11. Do not add browser speech synthesis.
12. Do not add SQLite fallback.
13. Do not replace React/Inertia.
14. Do not introduce Tailwind only for this refactor.
15. Do not redesign Scanner or Stage Control into ordinary admin pages.
16. Do not change Stage Display into an admin-style screen.
17. Do not remove capabilities because they are visually complex. Hide, regroup, or move them into secondary/advanced areas instead.
18. Do not perform large CSS rewrites in one task.
19. Do not modify unrelated files.
20. Every behavior change must have automated test coverage when practical.
21. Run required verification commands before marking a task complete.
22. Preserve accessibility: keyboard navigation, focus state, labels, contrast, and reduced-motion behavior.
23. Preserve existing URLs whenever possible. Prefer reorganizing navigation and page composition before changing routes.
24. Prefer incremental migration over full rewrite.
25. If an existing component can be simplified safely, refactor it instead of creating duplicate UI systems.

---

# 1. CURRENT SYSTEM PROBLEMS TO FIX

The current GSS has strong backend and operational capabilities but the interface exposes too much complexity at once.

## 1.1 Navigation overload

The current system exposes many destinations such as:

- Overview
- Graduates
- Ceremonies
- CSV Import
- QR Codes
- Stage Designs
- Voice Generation
- Pronunciation Dictionary
- Audio Readiness
- Scanner
- Stage Control
- Stage Display
- Scan Logs
- Stage Events
- Ceremony Summary
- Audit Logs
- Backups
- Operator Accounts
- Connect Devices

Problem:

- these are organized by system/module structure;
- normal users think in tasks, not modules;
- too many options are visible at the same time;
- advanced functionality competes visually with everyday work.

## 1.2 Global shell overload

The current application shell contains too many persistent elements:

- sidebar
- page name
- current ceremony
- ceremony status
- network status
- realtime connection status
- command center
- user menu
- help/onboarding
- bottom system status bar

Problem:

- status information is duplicated;
- technical information appears even when irrelevant;
- administrative screens look like live operations consoles;
- teachers/data encoders see unnecessary system details.

## 1.3 Page action overload

Some pages expose many actions in the page header.

Example ceremony page actions may include:

- View graduates
- Edit ceremony
- Generate voice
- Announcement settings
- Announcement batches

Problem:

- all actions receive similar visual importance;
- users must understand system architecture;
- primary intent is unclear.

## 1.4 Import workflow exposes technical details too early

The import flow is functionally good:

1. Upload CSV
2. Map columns
3. Review rows
4. Import summary

But Step 1 exposes too much implementation detail:

- UTF-8 requirements
- delimiter options
- ZIP limits
- `photos/`
- `audio/`
- `photo_filename`
- `audio_filename`
- row limits
- media mapping behavior
- overwrite rules

Problem:

- normal users need only enough information to proceed;
- technical details should be progressive disclosure.

## 1.5 Announcement workflow is fragmented

Announcement preparation is spread across several concepts:

- pronunciation dictionary
- voice generation
- provider settings
- voice comparison
- batch generation
- review queue
- audio readiness
- graduate announcement preview

Problem:

- users experience the internal pipeline instead of a guided preparation workflow.

## 1.6 Onboarding is compensating for interface complexity

The role onboarding system is technically useful, but current tours explain many separate destinations.

Problem:

- if normal work requires a long guided tour, the information architecture is still too complex.

## 1.7 CSS architecture is too monolithic

Current stylesheet:

`resources/css/app.css`

is very large and contains:

- global tokens
- shell
- admin
- onboarding
- pronunciation
- forms
- tables
- scanner
- stage
- responsive rules
- legacy styles
- repeated selectors

Problem:

- behavior is hard to reason about;
- duplicate selectors create maintenance risk;
- page-level refactoring is harder than necessary.

---

# 2. TARGET PRODUCT MODEL

The GSS must stop feeling like a collection of system modules.

It must feel like a guided graduation ceremony workspace.

The product should answer these user questions quickly:

1. What ceremony am I working on?
2. What still needs preparation?
3. What should I do next?
4. Are the graduates ready?
5. Are announcements ready?
6. Are QR codes/photos ready?
7. Is the ceremony ready to run?
8. Where do I scan graduates?
9. Where do I control the stage?
10. What do I do when something fails?

---

# 3. TARGET USER EXPERIENCES

## 3.1 Administrator

Primary jobs:

- create/manage ceremonies;
- activate the correct ceremony;
- review readiness;
- manage operators;
- configure advanced settings;
- resolve blockers;
- manage backups/recovery.

The administrator may access advanced features, but advanced features must not visually dominate everyday preparation.

## 3.2 Data Encoder / Teacher

Primary jobs:

- import graduates;
- add/edit graduate records;
- verify names/programs/eligibility;
- prepare photos;
- prepare announcements;
- review preparation status.

The Data Encoder should not need to understand system internals.

## 3.3 Scanner Operator

Primary jobs:

- scan graduate QR;
- immediately confirm accepted/rejected state;
- recover from unreadable QR;
- use controlled manual lookup when needed.

Scanner must remain a dedicated operational interface.

## 3.4 Stage Operator

Primary jobs:

- see current graduate;
- see next graduate;
- control queue progression;
- replay announcement;
- skip/return;
- pause/hold/recover.

Stage Control must remain a dedicated operational interface.

## 3.5 Technical Operator

Primary jobs:

- check device connectivity;
- inspect system/service health;
- investigate logs;
- validate backup/recovery readiness.

Technical diagnostics should live here instead of appearing on ordinary preparation screens.

---

# 4. TARGET PRIMARY INFORMATION ARCHITECTURE

Use these user-facing sections:

1. **Home**
2. **Graduates**
3. **Preparation**
4. **Run Ceremony**
5. **Reports**
6. **Settings**

Do NOT show all six to every role.

---

# 5. ROLE-SPECIFIC NAVIGATION

## 5.1 Administrator navigation

Primary visible destinations:

- Home
- Graduates
- Preparation
- Run Ceremony
- Reports
- Settings

Advanced pages may exist under Settings or Preparation.

## 5.2 Data Encoder navigation

Primary visible destinations:

- Home
- Graduates
- Preparation

Do not show:

- Backups
- Audit Logs
- Device Connections
- Stage Events
- technical service status

unless authorization and task context explicitly require it.

## 5.3 Scanner Operator navigation

Primary visible destination:

- Scanner

Secondary options:

- Help
- Return / Sign out

Do not show the full admin sidebar.

## 5.4 Stage Operator navigation

Primary visible destinations:

- Stage Control
- Stage Display

Secondary:

- Help
- Return / Sign out

Do not show preparation/admin modules.

## 5.5 Technical Operator navigation

Primary visible destinations:

- System Status
- Devices
- Logs
- Backups

Do not expose graduate-preparation tools unless specifically authorized.

---

# 6. TARGET CEREMONY WORKSPACE

Create or refactor a central ceremony workspace.

The ceremony workspace should be the main preparation hub.

## 6.1 Required content

Header:

- ceremony name;
- academic year;
- event date;
- status;
- optional venue;
- one primary next action.

Preparation checklist:

- Graduate information
- Photos
- QR codes
- Announcements
- Stage design
- Operator readiness
- Device readiness when relevant

Each preparation item must show one of:

- Ready
- Needs attention
- Not started
- Optional
- Blocked

Each issue count must be clickable.

Example:

```text
First Grading Recognition
2026-2027 · August 28, 2026

Preparation

✓ Graduate information        152 ready
⚠ Photos                      8 missing
⚠ Announcements               3 need review
✓ QR codes                    Ready
✓ Stage design                Selected

[Continue preparation]
```

Ceremony-day actions:

```text
[Open Scanner]
[Open Stage Control]
[Open Stage Display]
```

Only show actions the current role is authorized to use.

---

# 7. PHASED IMPLEMENTATION PLAN

---

# PHASE 0 — BASELINE AND SAFETY

## Goal

Create a stable regression baseline before UI/UX behavior changes.

## Tasks

### P0-T1 — Create refactor branch

From latest approved `feature/windows-client-distribution`:

```bash
git fetch origin
git switch feature/windows-client-distribution
git pull
git switch -c feature/gss-usability-workflow-refactor
```

If branch already exists:

```bash
git switch feature/gss-usability-workflow-refactor
git pull
```

### P0-T2 — Record baseline screenshots

Capture at minimum:

- Dashboard/Home
- Admin readiness
- Ceremony list
- Ceremony detail
- Graduate list
- Import step 1
- Import mapping
- Import review
- Voice generation
- Pronunciation rules
- Scanner
- Stage Control
- Stage Display
- Backups
- Device connections

Viewport sizes:

- 1366x768
- 1536x864

Store baseline screenshots under a dedicated QA location.

Do not overwrite current Playwright approved screenshots unless explicitly updating a visual assertion.

### P0-T3 — Run baseline verification

Run:

```bash
docker compose up -d --build
docker compose exec -T app php artisan test
docker compose exec -T app vendor/bin/pint --test
npm run lint
npm run build
```

If E2E test environment is configured:

```bash
npm run test:browser
```

or:

```bash
npx playwright test
```

Record failures before refactoring.

### P0-T4 — Freeze business behavior

Do not intentionally modify:

- student import validation;
- queue behavior;
- stage state transitions;
- scan acceptance rules;
- duplicate scan protection;
- authorization;
- backup behavior;
- announcement approval rules;
- realtime recovery.

## Definition of Done

- refactor branch exists;
- baseline screenshots exist;
- baseline tests are recorded;
- no functionality changed.

---

# PHASE 1 — WORKFLOW MAP AND NAVIGATION MODEL

## Goal

Replace module-first navigation with job-first navigation.

## Primary files

- `resources/js/components/appNavigation.js`
- `resources/js/components/AppShell.jsx`
- `app/Enums/UserRole.php`
- `app/Http/Middleware/HandleInertiaRequests.php`
- role/ability policies only if necessary for display logic

## P1-T1 — Build a route-to-workflow mapping

Create a frontend configuration that groups existing routes into:

```text
Home
Graduates
Preparation
Run Ceremony
Reports
Settings
```

Do NOT change routes yet.

Map existing destinations as follows.

### Home

- `/dashboard`
- `/admin` readiness/workspace

### Graduates

- `/admin/students`
- `/admin/students/create`
- `/admin/students/{id}`
- `/admin/students/{id}/edit`
- `/admin/students/import/*`

### Preparation

- QR code pages
- stage design
- voice generation
- pronunciation rules
- announcement settings
- announcement batches
- audio readiness-related graduate filters

### Run Ceremony

- `/scanner`
- `/stage-control`
- `/stage`

### Reports

- ceremony summary
- scan logs when shown to admins
- stage events when shown to admins

### Settings

- ceremonies administration where appropriate
- operator accounts
- device connections
- backups
- audit logs
- advanced announcement/provider configuration

## P1-T2 — Build role-specific navigation arrays

Do not simply hide unauthorized items.

Also reduce authorized-but-non-primary items.

Use a two-level model:

```js
primaryNavigation
secondaryNavigation
```

Rules:

- everyday tasks go into primary navigation;
- advanced/system tasks go into Settings or secondary navigation;
- operational screens get dedicated navigation.

## P1-T3 — Update command palette sources

The command palette may still expose authorized destinations.

But primary sidebar simplicity must not depend on removing command access.

Keep useful direct commands such as:

- Open Scanner
- Open Stage Control
- Open Graduates
- Import Graduates
- Open Preparation
- Open Settings

Do not list every deep subpage as a visually equal command unless needed.

## P1-T4 — Update navigation tests

Replace tests that only verify the long sidebar fits.

Add tests for:

- Data Encoder sees Home, Graduates, Preparation;
- Scanner Operator does not see admin navigation;
- Stage Operator sees only stage-relevant destinations;
- Technical Operator sees system-focused navigation;
- Administrator sees the complete high-level structure.

## Definition of Done

- no role sees unnecessary primary destinations;
- no behavior/routes changed;
- command palette still works;
- authorization is still enforced server-side;
- tests pass.

---

# PHASE 2 — APPLICATION SHELL REFACTOR

## Goal

Stop using one overloaded global shell for every context.

## Primary files

- `resources/js/components/AppShell.jsx`
- `resources/js/components/Admin/AdminLayout.jsx`
- `resources/js/components/SystemStatusBar.jsx`
- `resources/js/components/PageHeader.jsx` or equivalent
- new shell/layout components as needed

## Required structure

Implement composable shell modes.

Preferred conceptual structure:

```text
AppShell
├── PreparationShell
├── OperationalShell
└── TechnicalShell
```

Actual implementation may use props instead of three fully separate roots.

## P2-T1 — Add shell mode support

Suggested prop/API:

```jsx
<AppShell mode="preparation">
<AppShell mode="operational">
<AppShell mode="technical">
```

or equivalent.

## P2-T2 — Preparation shell

Must contain only:

- brand/app identity;
- role-appropriate primary navigation;
- current ceremony context;
- user menu;
- page content;
- contextual help when useful.

Must NOT show persistent:

- network status;
- realtime connection status;
- bottom service bar;
- technical diagnostics.

Healthy technical state should remain invisible.

Only show a global warning if the condition affects the user's current task.

## P2-T3 — Operational shell

Use only for Scanner/Stage Control or equivalent operational contexts.

Show:

- ceremony state;
- critical connection state;
- operation-specific system state;
- large critical actions;
- minimal unrelated navigation.

## P2-T4 — Technical shell

Show:

- service status;
- device status;
- backup health;
- system information;
- detailed diagnostics.

Technical status belongs here.

## P2-T5 — Remove global bottom status bar from normal admin screens

`SystemStatusBar` must NOT be mounted by default for all pages.

Only use it where persistent status is operationally useful.

## P2-T6 — Remove duplicate status messaging

Do not show the same status both:

- in top toolbar;
- and bottom bar;
- and page card.

Choose one contextually correct location.

## Definition of Done

- normal admin/preparation screens are visually quieter;
- technical healthy-state indicators are absent from normal screens;
- Scanner/Stage still show operational state;
- no connection/recovery behavior changed.

---

# PHASE 3 — HOME AND CEREMONY WORKSPACE

## Goal

Make Home answer: "What should I do next?"

## Primary files

- `resources/js/pages/Dashboard.jsx`
- `resources/js/pages/Admin/Index.jsx`
- `resources/js/pages/Admin/Ceremonies/Show.jsx`
- `app/Http/Controllers/Admin/AdminDashboardController.php`
- related readiness/query services

## P3-T1 — Merge overlapping overview concepts

Avoid having multiple confusing "Overview", "Administration", and "Ceremony readiness" concepts.

Target:

- Home = role-aware next-action page.
- Ceremony Workspace = ceremony-specific preparation page.

## P3-T2 — Build preparation checklist data

Backend should provide a normalized structure such as:

```json
{
  "graduate_data": {
    "status": "ready",
    "count": 152,
    "issue_count": 0,
    "href": "/admin/students?...",
    "label": "Graduate information"
  },
  "photos": {
    "status": "warning",
    "issue_count": 8,
    "href": "/admin/students?media=missing_photo",
    "label": "Photos"
  },
  "announcements": {
    "status": "warning",
    "issue_count": 3,
    "href": "/admin/voice-generation",
    "label": "Announcements"
  }
}
```

Do not duplicate business rules in React if backend already calculates readiness.

## P3-T3 — Add one primary next action

Determine the next action based on blockers.

Example priority:

1. no active ceremony;
2. no graduates;
3. eligibility issues;
4. missing photos if required;
5. announcement review;
6. QR preparation;
7. device/operator readiness;
8. ready for rehearsal/ceremony.

Only one action receives primary visual emphasis.

## P3-T4 — Move advanced ceremony details out of the header

Do not show five equally strong header actions.

Use:

Primary button:
- Continue preparation
or
- Resolve next issue

Secondary:
- Edit ceremony

Overflow/secondary area:
- announcement settings
- announcement batches
- delete
- technical history

## Definition of Done

- first-time user can identify the next task without opening another page;
- ceremony preparation issues are directly actionable;
- advanced controls remain accessible;
- no readiness logic is lost.

---

# PHASE 4 — GRADUATE MANAGEMENT REFACTOR

## Goal

Make graduate management readable for teachers/data encoders.

## Primary files

- `resources/js/pages/Admin/Students/Index.jsx`
- student create/edit/show pages
- shared student components
- CSS for tables and filters

## P4-T1 — Simplify default table columns

Default columns should prioritize:

- sequence;
- graduate;
- program;
- preparation status;
- action.

Do not show all of these by default:

- ceremony
- eligibility
- photo
- audio
- stage
- QR
- many row actions

unless the context requires them.

## P4-T2 — Add combined preparation status

Create a human-readable status such as:

- Ready
- Needs eligibility review
- Photo missing
- Announcement missing
- Announcement needs review
- Multiple issues

Use status text + icon; not color alone.

## P4-T3 — Reduce row actions

Default row action:

- Open

Optional quick action:

- Edit

Place QR and specialized actions inside the graduate detail screen.

## P4-T4 — Simplify filters

Always visible:

- search;
- ceremony when relevant;
- preparation status.

Move advanced filters into "More filters":

- college;
- program;
- stage status;
- media internals;
- sort.

## P4-T5 — Preserve administrative deep visibility

Do not delete any existing data.

Move detailed fields into the student detail page.

## Definition of Done

- graduate list is understandable without training;
- default table is narrower;
- advanced filtering still exists;
- existing permissions remain intact.

---

# PHASE 5 — IMPORT GRADUATES REFACTOR

## Goal

Keep the robust four-step import pipeline but simplify the language and visual hierarchy.

## Primary files

- `resources/js/pages/Admin/Students/Import/Index.jsx`
- `Mapping.jsx`
- `Preview.jsx`
- `Summary.jsx`
- `resources/js/components/Admin/ImportProgress.jsx`
- import CSS
- existing import controller only if props need restructuring

## Keep this workflow

1. Upload file
2. Match columns
3. Review
4. Finished

## P5-T1 — Rename user-facing steps

Current:

- Upload CSV
- Map columns
- Review rows
- Import summary

New:

- Upload file
- Match columns
- Review
- Finished

Internal controller/route names may stay unchanged.

## P5-T2 — Simplify Step 1

Target layout:

```text
Import graduates

Add graduates to:
First Grading Recognition        [Change]

Upload graduate list
[ Drop CSV here / Choose CSV ]

[ Download template ]

Photos & recorded announcements (optional)
[ Add ZIP package ]

[ Continue ]

File requirements ▾
```

## P5-T3 — Move technical instructions under disclosure

Hide by default:

- delimiter support;
- UTF-8 wording;
- folder paths;
- file-size limits unless exceeded;
- `photo_filename`;
- `audio_filename`.

Place under:

`File requirements`

or contextual help.

## P5-T4 — Make current ceremony compact

If exactly one valid ceremony is available:

Show ceremony name as context.

Do not render a large select unless the user needs to choose.

Provide a small "Change" affordance where permitted.

## P5-T5 — Simplify CTA language

Use:

- Continue
- Review
- Import graduates
- Done

Avoid:

- Inspect import
- Confirm atomic import
- Import summary

unless technical context requires it.

## P5-T6 — Preserve validation detail

Errors must still explain:

- what is wrong;
- which row/column;
- what the user should fix;
- whether anything was saved.

Do not hide validation detail when an error exists.

## Definition of Done

- user can upload CSV without reading technical instructions;
- advanced requirements remain available;
- mapping and validation still behave identically;
- import remains atomic;
- current import tests still pass.

---

# PHASE 6 — PREPARATION HUB

## Goal

Merge fragmented preparation functions into a guided area.

## User-facing Preparation sections

1. Graduate information
2. Photos
3. QR codes
4. Announcements
5. Stage design
6. Readiness

## P6-T1 — Create Preparation page

Recommended route:

Reuse an existing suitable route if possible.

Avoid route proliferation unless needed.

Page must show summary cards/list rows with status and issue count.

Example:

```text
Preparation

Graduate information     Ready
Photos                    8 missing
QR codes                  Ready
Announcements             3 need review
Stage design              Ready

[Continue preparation]
```

## P6-T2 — Do not use a decorative dashboard card grid

Use compact structured rows or sections.

Every row should have:

- status;
- short description;
- count if relevant;
- one action.

## P6-T3 — Use direct issue links

Example:

"8 missing photos" -> filtered graduate list.

"3 announcements need review" -> review queue.

## Definition of Done

- everyday preparation tasks can be reached from one place;
- sidebar no longer needs separate primary links for every preparation subsystem.

---

# PHASE 7 — ANNOUNCEMENTS WORKFLOW REFACTOR

## Goal

Present announcement preparation as one workflow instead of exposing the full internal TTS pipeline.

## Existing capabilities to preserve

- provider settings;
- voice comparison;
- pronunciation dictionary;
- exact overrides;
- candidate generation;
- batch generation;
- human review;
- approval/rejection;
- readiness states.

## User-facing model

Page title:

**Announcements**

Top summary:

```text
Announcements

147 ready
3 need pronunciation review
2 need approval

[Review next issue]
```

## P7-T1 — Group normal tasks

Primary sections:

- Pronunciation issues
- Generation status
- Review queue

## P7-T2 — Move provider configuration to advanced settings

Provider details such as:

- Kokoro configuration;
- Azure credentials/configuration;
- immutable template internals;
- voice comparison setup

must not dominate normal Data Encoder workflow.

Place in:

Settings -> Announcement settings

or an Administrator-only advanced area.

## P7-T3 — Keep Data Encoder flow simple

Data Encoder should mainly see:

1. Fix names/wording
2. Generate announcements
3. Review required items
4. Ready

## P7-T4 — Simplify terminology

Prefer:

- Announcements
- Generate
- Review
- Approved
- Needs review
- Pronunciation

Avoid exposing:

- candidate lifecycle
- immutable template version
- provider registry

unless in advanced settings.

## Definition of Done

- a teacher/data encoder can understand announcement status without knowing TTS internals;
- administrator can still access full configuration;
- approval safety remains unchanged.

---

# PHASE 8 — OPERATIONAL SCREENS REFINEMENT

## Goal

Improve Scanner and Stage Control without turning them into normal admin pages.

---

## 8A. Scanner

Primary files:

- `resources/js/pages/Scanner.jsx`
- scanner components
- scanner CSS
- browser tests

### Required behavior

Keep:

- scanner input focus;
- USB scanner support;
- camera support if present;
- controlled manual lookup;
- accepted/rejected feedback;
- server state;
- realtime state;
- duplicate-scan protection.

### UI changes

- reduce header navigation;
- keep only critical status;
- make scan result dominant;
- recent scans secondary;
- fallback lookup collapsed by default;
- no admin sidebar;
- no unnecessary technical labels.

### Scanner Definition of Done

At 1366x768:

- scan input/result is above the fold;
- operator can process next graduate without scrolling;
- accepted/rejected state is unmistakable;
- input focus recovery still works.

---

## 8B. Stage Control

Primary files:

- Stage Control React page/components
- stage-control CSS
- browser tests

### Required behavior

Keep:

- current graduate;
- next graduate;
- queue;
- Present;
- Complete/Next;
- Skip;
- Return;
- Replay;
- Holding;
- Pause/Resume;
- realtime recovery.

### UI hierarchy

Top priority:

1. Current graduate
2. Next graduate
3. Main advance action
4. Queue
5. Recovery controls

Emergency/recovery controls must be visually separated from normal operation.

### Stage Control Definition of Done

At 1366x768:

- current and next graduate visible;
- main stage action visible;
- queue visible enough to operate;
- no unrelated admin controls.

---

# PHASE 9 — REPORTS AND TECHNICAL SETTINGS

## Goal

Move low-frequency technical features away from everyday workflows.

## Reports

Group:

- Ceremony Summary
- Scan Logs
- Stage Events

Admins may access all.

Technical users may access operational logs as authorized.

## Settings

Group:

- Ceremonies management
- Operator Accounts
- Device Connections
- Backups
- Audit Logs
- Advanced Announcement Settings
- Pronunciation global settings when appropriate

## Rule

Do not remove these features.

Only reduce their prominence.

## Definition of Done

- teachers/data encoders are not exposed to unnecessary technical tools;
- advanced administrators still retain full access.

---

# PHASE 10 — ONBOARDING SIMPLIFICATION

## Goal

Use onboarding as help, not as compensation for complicated navigation.

## Primary files

- `resources/js/components/OnboardingGuide.jsx`
- `resources/js/components/onboardingTours.js`
- `app/Services/OnboardingWorkspace.php`
- onboarding configuration

## P10-T1 — Replace long tours with short role checklists

Administrator:

- Create ceremony
- Add graduates
- Review preparation
- Check operators/devices
- Ready

Data Encoder:

- Add/import graduates
- Fix preparation issues
- Review announcements

Scanner Operator:

- Verify scanner ready
- Test scan
- Learn fallback lookup

Stage Operator:

- Verify stage connection
- Test queue controls
- Review recovery actions

Technical Operator:

- Verify devices
- Verify logs
- Verify backup

## P10-T2 — Keep contextual help

Keep:

- Help menu;
- Keyboard shortcuts;
- Optional guided hints.

Remove unnecessary forced cross-page tours.

## Definition of Done

- user can work without completing onboarding;
- help remains available;
- onboarding still respects live ceremony suppression.

---

# PHASE 11 — CSS MODULARIZATION

## Goal

Reduce CSS risk and make the new UX maintainable.

## Current issue

`resources/css/app.css` is too large and contains repeated/legacy selectors.

## Target structure

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
    announcements.css
    preparation.css

  operations/
    scanner.css
    stage-control.css
    stage-display.css

  utilities/
    accessibility.css
    responsive.css
```

## P11-T1 — Make `app.css` an import entrypoint

Example:

```css
@import './foundation/tokens.css';
@import './foundation/reset.css';
@import './foundation/typography.css';
@import './shell/app-shell.css';
@import './shell/navigation.css';
@import './components/buttons.css';
...
```

Verify Vite supports the selected import form in the current build.

## P11-T2 — Migrate by stable domain

Migration order:

1. tokens/reset
2. shell
3. common components
4. graduates/import
5. announcements
6. scanner
7. stage control
8. reports/settings
9. remaining legacy CSS

## P11-T3 — Remove duplicate selectors only after visual verification

For each migrated selector:

1. identify all definitions;
2. determine currently effective cascade;
3. move intended final rule;
4. remove duplicate legacy rule;
5. visually verify;
6. run tests.

Do not bulk-delete old selectors.

## Definition of Done

- CSS is modular;
- no duplicate critical selectors remain;
- visual behavior is stable;
- build passes.

---

# PHASE 12 — UX TESTING AND ACCEPTANCE

## Goal

Measure whether the refactor actually made GSS easier.

## Automated tests

Keep existing:

- PHPUnit;
- authorization tests;
- queue atomicity;
- scanner behavior;
- realtime recovery;
- backup verification;
- Axe accessibility;
- keyboard focus tests.

Add UX-focused Playwright scenarios.

---

## P12-T1 — Data Encoder task test

Scenario:

1. Sign in as Data Encoder.
2. Reach Graduates.
3. Start Import.
4. Upload CSV.
5. Map columns.
6. Review validation.
7. Complete import.
8. Return to graduate list.

Assertions:

- no technical admin navigation visible;
- import is reachable within two intentional actions;
- only expected primary destinations visible;
- no horizontal overflow;
- Axe has zero violations.

---

## P12-T2 — Administrator readiness test

Scenario:

1. Sign in as Administrator.
2. Home identifies preparation blocker.
3. Click blocker.
4. Resolve issue.
5. Return Home.
6. Confirm readiness updates.

Assertions:

- next action is obvious;
- blocker link is direct;
- no duplicated healthy technical status.

---

## P12-T3 — Scanner test

Keep existing critical scan behavior.

Add assertions:

- no admin sidebar;
- scan input remains focused;
- result visible without scroll;
- fallback lookup is secondary.

---

## P12-T4 — Stage Control test

Add assertions:

- current graduate visible;
- next graduate visible;
- primary action visible;
- queue available;
- recovery controls separated;
- no unrelated admin navigation.

---

## P12-T5 — Viewport tests

Required:

- 1366x768
- 1536x864

Assertions:

- no horizontal page scroll;
- no critical action hidden below fold on Scanner/Stage;
- sidebar/nav does not need scrolling for ordinary roles.

---

# 13. HUMAN USABILITY TEST

After automated validation, test with a person who has not used GSS.

Do not give click-by-click instructions.

Give them:

- a sample ceremony;
- a CSV;
- graduate photos;
- normal preparation tasks.

Ask:

> Prepare this ceremony for graduation.

Record:

- first wrong click;
- hesitation;
- misunderstood labels;
- places where help is requested;
- number of backtracks;
- completion time;
- errors;
- whether they can recover without developer help.

Repeat after fixes.

---

# 14. REQUIRED UX ACCEPTANCE TARGETS

The refactor is NOT complete unless these targets are met.

## Navigation

- Administrator: maximum 6 high-level primary destinations.
- Data Encoder: normally 3 primary destinations.
- Scanner Operator: no full admin navigation.
- Stage Operator: no full admin navigation.
- Technical Operator: system-focused navigation only.

## Workflow

- Import reachable within 2 intentional actions from Data Encoder Home/Graduates.
- Home clearly shows next preparation action.
- Ceremony preparation issues are directly clickable.
- Normal users do not need to understand internal TTS/provider architecture.

## Visual hierarchy

- one dominant primary action per normal admin page;
- healthy technical status is not repeated;
- technical status is hidden when irrelevant;
- advanced controls use secondary/overflow treatment.

## Operational screens

At 1366x768:

- Scanner primary scan area visible without scrolling;
- Stage current/next/action visible without scrolling;
- critical status visible;
- no unrelated controls.

## Accessibility

- Axe: zero violations for covered pages;
- all interactive elements keyboard reachable;
- visible focus indicators;
- color never used alone for status;
- reduced motion respected.

## Regression safety

Must still pass:

```bash
docker compose exec -T app php artisan test
docker compose exec -T app vendor/bin/pint --test
npm run lint
npm run build
```

and browser tests where configured.

---

# 15. COMPONENT REFACTOR TARGETS

Use these targets where appropriate.

## 15.1 Shell components

Potential components:

```text
AppShell
PrimaryNavigation
CeremonyContext
UserMenu
GlobalWarning
PreparationShell
OperationalShell
TechnicalShell
```

Do not create all components unless they reduce complexity.

## 15.2 Workflow components

Potential components:

```text
PreparationChecklist
PreparationRow
NextActionPanel
IssueCount
StatusText
AdvancedDisclosure
CompactPageHeader
```

## 15.3 Form components

Reuse shared components for:

- label;
- hint;
- error;
- required state;
- file upload;
- disclosure;
- primary action area.

Do not create inconsistent page-specific forms.

---

# 16. PAGE HEADER RULE

For normal admin/preparation screens:

Use:

```text
Title
Short description

[Primary Action] [Optional Secondary]
```

Maximum:

- 1 primary action;
- 1 directly visible secondary action.

Move all other actions to:

- page sections;
- overflow menu;
- Settings;
- advanced disclosure.

Exceptions:

- operational screens;
- destructive confirmation workflows.

---

# 17. TERMINOLOGY STANDARD

Use these user-facing terms.

| Avoid | Use |
|---|---|
| CSV Import | Import graduates |
| Inspect import | Continue |
| Import summary | Finished |
| Destination ceremony | Add graduates to |
| Media package | Photos & audio |
| Audio readiness | Announcements |
| Voice generation | Generate announcements |
| Announcement candidate | Generated announcement |
| Operational logs | Activity & logs |
| Ceremony readiness | Preparation status |
| Student record | Graduate record |
| Stage event history | Stage activity |
| Device connections | Devices |
| Operator accounts | Users / Operators |
| Announcement batches | Batch generation / Generate announcements |

Do not rename internal classes/routes solely to match UI wording unless there is a strong technical reason.

---

# 18. ERROR MESSAGE STANDARD

Every user-facing error must state:

1. what happened;
2. what was affected;
3. whether data was saved;
4. what the user should do next.

Avoid:

> Something went wrong.

Prefer:

> The graduate list was checked, but 3 rows have missing student numbers. No graduates were imported. Fix the highlighted rows and upload the file again.

Operational example:

> The scan was not confirmed by the local server. No graduate was added to the queue. Check the connection, then scan the same QR code again.

---

# 19. PROGRESSIVE DISCLOSURE STANDARD

Information belongs in one of three levels.

## Level 1 — Always visible

Only information required for the current task.

Examples:

- ceremony;
- graduate name;
- next action;
- issue count;
- primary button.

## Level 2 — Expandable/contextual

Useful but not always required.

Examples:

- import file requirements;
- advanced filters;
- detailed status explanation;
- optional media instructions.

## Level 3 — Advanced settings

Technical/administrative configuration.

Examples:

- provider configuration;
- backup internals;
- audit logs;
- device certificates;
- announcement template versions;
- global pronunciation management.

Agents must classify new UI information into one of these levels before placing it on screen.

---

# 20. STATUS VISIBILITY STANDARD

## Preparation/Admin screens

Show:

- task status;
- blockers;
- save success/failure;
- ceremony status if relevant.

Do not show healthy:

- network;
- Reverb;
- server;
- database

unless failure affects the current task.

## Scanner

Show:

- scanner capture readiness;
- server confirmation state;
- realtime state;
- ceremony accepting scans.

## Stage Control

Show:

- ceremony active/paused;
- stage connection;
- realtime state;
- audio readiness where required;
- current/next presentation state.

## Technical

Show full system status.

---

# 21. SAFE REFACTOR BOUNDARIES

These parts should remain behaviorally stable unless a dedicated task explicitly says otherwise:

- Laravel models;
- database migrations;
- transactional Actions;
- scan submission semantics;
- queue state machine;
- stage state machine;
- Reverb broadcasting semantics;
- recovery reads;
- backup verification;
- TTS approval safety;
- authorization.

UI components may consume the same data differently.

---

# 22. COMMIT STRATEGY

Use small focused commits.

Examples:

```text
refactor: add role-based workflow navigation
refactor: split preparation and operational shell status
feat: add ceremony preparation workspace
refactor: simplify graduate list hierarchy
refactor: simplify graduate import step one
refactor: consolidate announcement preparation UI
refactor: modularize shell CSS
test: add data encoder import usability flow
test: add role-specific navigation coverage
```

Do not combine multiple large phases into one commit.

---

# 23. AGENT TASK TEMPLATE

Every small AI agent should receive tasks in this structure.

```text
TASK:
<one narrowly scoped implementation task>

BASELINE:
feature/gss-usability-workflow-refactor

READ FIRST:
AGENTS.md
<this playbook section>

FILES TO INSPECT:
<exact paths>

FILES ALLOWED TO CHANGE:
<exact paths>

DO:
1.
2.
3.
4.

DO NOT:
1.
2.
3.

ACCEPTANCE CRITERIA:
- ...
- ...
- ...

VERIFY:
<exact commands>

OUTPUT:
- summary of changed files
- verification results
- remaining issue only if blocked
```

Agents should not redesign beyond the assigned task.

---

# 24. EXAMPLE SMALL-AGENT TASKS

## Agent Task A — Hide global bottom status on preparation screens

```text
TASK:
Make SystemStatusBar unavailable by default on preparation/admin pages.

FILES TO INSPECT:
resources/js/components/AppShell.jsx
resources/js/components/SystemStatusBar.jsx
resources/js/components/Admin/AdminLayout.jsx

DO:
1. Add shell-mode or status-bar configuration.
2. Ensure AdminLayout uses preparation mode.
3. Do not render SystemStatusBar in preparation mode.
4. Preserve the component for operational/technical use.

DO NOT:
1. Delete SystemStatusBar.
2. Change realtime connection logic.
3. Change network detection behavior.

ACCEPTANCE:
- /admin/students has no bottom status bar.
- /admin/students/import has no bottom status bar.
- operational screens retain required state.
- no console errors.

VERIFY:
npm run lint
npm run build
```

## Agent Task B — Simplify Import Step 1 text

```text
TASK:
Simplify the first Import Graduates page without changing import backend behavior.

FILES:
resources/js/pages/Admin/Students/Import/Index.jsx
resources/js/components/Admin/ImportProgress.jsx
relevant import CSS only

DO:
1. Rename visible title to Import graduates.
2. Rename step labels to Upload file / Match columns / Review / Finished.
3. Use primary CTA Continue.
4. Move technical file requirements into a details disclosure.
5. Keep template download visible.
6. Keep optional ZIP upload.
7. Keep all validation and file limits unchanged.

DO NOT:
1. Change controller logic.
2. Change validation limits.
3. Change routes.
4. Change import session behavior.

VERIFY:
npm run lint
npm run build
relevant tests
```

## Agent Task C — Simplify Graduate table

```text
TASK:
Reduce default Graduate table density.

FILES:
resources/js/pages/Admin/Students/Index.jsx
relevant student CSS

DO:
1. Keep default columns: Sequence, Graduate, Program, Preparation status, Action.
2. Build Preparation status from existing props only if sufficient.
3. If backend props are insufficient, stop and report exact missing data instead of inventing logic.
4. Keep search.
5. Keep advanced filters under disclosure.
6. Keep Open as main row action.

DO NOT:
1. Remove existing student data.
2. Remove permissions.
3. Remove detail page actions.
4. Change backend rules.

VERIFY:
npm run lint
npm run build
browser test at 1366x768
```

---

# 25. STOP CONDITIONS FOR AGENTS

An agent must STOP and report instead of guessing when:

1. required data is not present in page props;
2. a requested UX change would require changing authorization semantics;
3. a route change would break external/open-stage behavior;
4. behavior differs between the baseline branch and current working branch;
5. existing tests reveal a safety invariant not covered by this playbook;
6. a CSS selector is duplicated and the effective intended rule cannot be determined safely;
7. Scanner or Stage behavior would need modification beyond presentation;
8. backend business rules must change to achieve the requested UI.

When stopped, report:

```text
BLOCKED:
<exact reason>

NEEDED:
<minimal decision or missing data>

FILES INVOLVED:
<paths>

NO CHANGES MADE:
yes/no
```

---

# 26. FINAL DEFINITION OF DONE

The entire GSS Usability & Workflow Refactor is complete only when all of the following are true.

## Product

- GSS feels like a ceremony workflow, not a module collection.
- Home clearly tells users what to do next.
- Ceremony preparation is centralized.
- common users see only relevant navigation.
- advanced tools remain available but secondary.

## Data Encoder

- can import graduates without technical instruction;
- can identify preparation problems;
- can prepare announcements without understanding provider internals.

## Administrator

- can see blockers;
- can resolve readiness issues;
- can reach advanced settings when needed.

## Scanner Operator

- can scan continuously with minimal distraction;
- can recover from unreadable QR;
- can understand accepted/rejected states immediately.

## Stage Operator

- can see current/next graduate;
- can control stage flow without scrolling;
- can reach recovery controls safely.

## Technical Operator

- can access device, log, backup, and system diagnostics without exposing them to normal users.

## Code quality

- CSS is modularized;
- duplicate critical selectors removed;
- components have clear responsibilities;
- no unnecessary framework migration.

## Safety

- authorization unchanged;
- scan/queue/stage transaction safety unchanged;
- realtime behavior unchanged;
- recovery behavior unchanged;
- backup safety unchanged.

## Verification

All required automated tests, lint, formatting, build, accessibility checks, and usability scenarios pass.

---

# 27. IMPLEMENTATION ORDER — DO NOT CHANGE

Execute phases in this order:

```text
0. Baseline and safety
1. Workflow map and navigation
2. Shell refactor
3. Home and ceremony workspace
4. Graduate management
5. Import graduates
6. Preparation hub
7. Announcements
8. Scanner and Stage refinement
9. Reports and technical settings
10. Onboarding simplification
11. CSS modularization
12. UX tests and acceptance
13. Human usability validation
```

Do not begin CSS visual polish before navigation, shell, and core workflow structure are stable.

---

# 28. CORE PRINCIPLE

Every implementation decision should satisfy this rule:

> Show the user only the information and actions required for the task they are currently performing, while keeping advanced system capability available when needed.

The GSS backend is already powerful.

The purpose of this refactor is to reduce cognitive load, simplify navigation, guide users through ceremony preparation, and preserve the reliability required during live graduation operations.
