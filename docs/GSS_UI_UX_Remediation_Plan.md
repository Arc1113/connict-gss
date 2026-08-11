# Graduation Stage System — UI/UX Remediation and Workflow Redesign Plan

**Repository:** `Aracena18/connict_gss`
**Target branch:** `main`
**Project:** Graduation Stage System (GSS)
**Primary stack:** Laravel + React + Inertia.js + Vite + PostgreSQL + Laravel Reverb/Echo
**Deployment model:** Local-network, multi-device, installable PWA
**Purpose:** Fix the UI/UX architecture identified during the full repository audit without damaging existing operational reliability.

---

## 1. Purpose of This Plan

This document is the implementation plan for fixing the current Graduation Stage System UI/UX.

The redesign must **not** be treated as a visual reskin.

The main problem identified during the audit is that the system already contains strong individual features, but the administration and preparation workflow is fragmented across multiple separate modules.

The redesign must make the application feel like one connected **graduation ceremony operations system** rather than a collection of independent pages.

The primary design shift is:

> Users should feel that they are working on one ceremony and progressing through its preparation workflow.

Instead of:

> Users repeatedly navigating between Ceremonies, Graduates, CSV Import, QR Codes, Voice Generation, Stage Designs, Reports, and other disconnected modules.

The existing Scanner, Stage Control, and Stage Display implementations already have strong operational behavior. They must be preserved and integrated more clearly into the overall system rather than rewritten unnecessarily.

---

## 2. Highest-Level Goal

Transform the GSS into a **ceremony-centered desktop operations application** with three clearly separated contexts:

1. **Preparation**
2. **Live Ceremony Operations**
3. **Review / System Administration**

The system must make the following immediately understandable:

- Which ceremony the operator is currently preparing.
- Which ceremony is currently live.
- What parts of the ceremony preparation are complete.
- What still requires attention.
- What the operator should do next.
- Where Scanner and Stage Control belong in the workflow.
- How to move between graduate preparation, QR, announcements, stage design, and readiness without repeatedly selecting or searching for the same ceremony.

---

## 3. Critical Rule: Preserve Existing Operational Reliability

The redesign must **not change business rules merely to simplify UI implementation**.

Existing backend protections, authorization rules, transaction behavior, queue rules, stage recovery behavior, QR security, audio approval rules, Reverb synchronization, backup safety, audit logs, and ceremony lifecycle protections remain authoritative.

If UI convenience conflicts with operational correctness:

> Operational correctness wins.

Do not weaken server-side validation because the new UI appears to make an invalid state impossible.

The server must continue rejecting invalid requests.

---

## 4. What Must Be Preserved

### 4.1 Scanner

Preserve:

- USB scanner support.
- Keyboard-wedge behavior.
- Input autofocus.
- Enter-to-process behavior.
- Camera scanning.
- Camera retry logic.
- Duplicate protection.
- Manual graduate lookup.
- Server availability state.
- Reverb connection state.
- Accepted/rejected scan feedback.
- Recent scan history.
- Ceremony inactive handling.
- Recovery instructions.
- Input refocusing.
- Accessibility behavior.

Do not convert Scanner into a normal admin page.

Do not surround Scanner with the full administration sidebar.

Scanner must remain a specialized operator workspace.

### 4.2 Stage Control

Preserve:

- Current graduate.
- Next graduate.
- Waiting queue.
- Skipped list.
- Recent actions.
- Present next.
- Complete and present next.
- Complete and clear.
- Replay announcement.
- Holding screen.
- Skip.
- Return to queue.
- Present selected.
- Wrong-graduate correction.
- Assisted automatic controls.
- Authoritative server-state reconciliation.
- Version protection.
- Command locking.
- Reverb reconnection.
- Focus-managed confirmation dialogs.
- Error/recovery messages.

Do not convert Stage Control into a generic CRUD/admin screen.

### 4.3 Stage Display

Preserve:

- Full-screen audience presentation.
- No sidebar.
- No normal admin controls.
- Ceremony-specific theme.
- Graduate information presentation.
- Stage preflight.
- Audio preflight.
- Holding state.
- Idle state.
- Reverb recovery.
- Audio playback logic.
- Stage state reconciliation.

Only operator-facing leakage must be fixed.

---

## 5. Problems Identified During the Audit

The implementation phases below must directly solve these problems.

### 5.1 Critical Audience Display Problem

`PwaStatus` is globally mounted by `resources/js/app.jsx`.

This means PWA messages such as:

- application update ready;
- installation prompt;
- offline/server message;

can potentially appear on the audience-facing Stage Display.

The Stage Display must never display technical workstation controls or installation prompts.

### 5.2 No Separate "Working Ceremony"

The system currently has a strong concept of an **active ceremony**.

That active ceremony is correctly used by:

- Scanner;
- Stage Control;
- Stage Display.

However, administration/preparation also frequently depends on that active ceremony.

This creates a UX problem.

Operators need to prepare future ceremonies without activating them.

The system therefore requires two separate concepts:

#### Live Ceremony

The ceremony currently used for live operations.

Only one may be active.

Used by:

- Scanner;
- Stage Control;
- Stage Display.

#### Working Ceremony

The ceremony the administrator/data encoder is currently preparing.

Used by:

- Graduate records;
- Import;
- QR preparation;
- Announcement preparation;
- Stage design;
- Readiness;
- Reports.

Selecting a Working Ceremony must NEVER activate it.

### 5.3 Duplicate Home / Overview Experiences

The system currently has both:

- `/dashboard`
- `/admin`

These function as different overview pages.

They should be consolidated into one coherent application home experience.

### 5.4 Ceremony Detail Is Acting Like a Link Launcher

Ceremony detail currently links to multiple related modules rather than functioning as the actual workspace for that ceremony.

Examples:

- View graduates.
- Generate voice.
- Announcement settings.
- Announcement batches.
- Edit ceremony.

These should become contextual parts of one Ceremony Workspace.

### 5.5 Graduate Detail Is Too Long and Multi-Purpose

Graduate Detail currently mixes:

- identity;
- academic details;
- eligibility;
- QR;
- photo;
- audio;
- announcement review;
- voice generation;
- operational history;
- deletion.

The screen needs stronger local information architecture.

### 5.6 Browser-History "Back" Is Not Semantic Navigation

The shared `PageHeader` uses browser history.

The UI should instead provide deterministic parent navigation.

The user should know exactly what parent screen they will return to.

### 5.7 Navigation Reflects Technical Modules More Than User Workflow

Current groups such as:

- Data
- Operations
- System

are technically understandable but do not sufficiently communicate the ceremony preparation workflow.

### 5.8 CSS Architecture Contains Migration Debt

`resources/css/app.css` has grown very large and contains overlapping modern and legacy token aliases.

Do not rewrite all CSS immediately.

The CSS should be cleaned gradually after information architecture changes are stable.

---

## 6. Target Product Mental Model

The redesigned application must communicate this model:

```text
APPLICATION
│
├── Overview
│
├── Working Ceremony
│   ├── Overview
│   ├── Setup
│   ├── Graduates
│   ├── Import
│   ├── QR & Media
│   ├── Announcements
│   ├── Stage Design
│   └── Readiness
│
├── Live Operations
│   ├── Scanner
│   ├── Stage Control
│   └── Stage Display
│
├── Review
│   ├── Ceremony Summary
│   ├── Scan Logs
│   └── Stage Events
│
└── System
    ├── Connect Devices
    ├── Backups
    ├── Operator Accounts
    └── Audit Logs
```

This architecture is the target unless a backend constraint makes a specific item impossible.

---

## 7. Required Development Strategy

The redesign must be implemented **incrementally**.

Do not attempt a single giant rewrite.

Each phase must:

1. Have one clear purpose.
2. Keep the application runnable.
3. Preserve existing routes where practical.
4. Keep existing tests passing.
5. Add tests for new behavior.
6. Avoid mixing unrelated refactors.

Do not proceed to later visual cleanup while important context/navigation behavior remains unresolved.

---

# PHASE 0 — Repository and Safety Preparation

## Goal

Establish a safe baseline before modifying UX architecture.

### 0.1 Verify the Correct Branch

All work must target:

```text
main
```

The fork currently may still report an old development branch as the default GitHub branch.

Change the GitHub repository default branch to:

```text
main
```

This repository configuration change is outside normal application code but must be completed before long-term work continues.

### 0.2 Create a UI/UX Redesign Branch

Recommended branch:

```text
feature/ui-ux-ceremony-workspace
```

Do not work directly on `main`.

### 0.3 Baseline Validation

Before modifying UI behavior, run:

```bash
npm install
npm run lint
npm run build
```

Run Laravel test suite according to the project setup.

Run browser tests when the QA environment and required environment variables are available:

```bash
npm run test:browser
```

Record failures that already exist before redesign work.

Do not treat pre-existing failures as redesign regressions.

### 0.4 Read Before Editing

Codex must read at minimum:

```text
design.md

routes/web.php

resources/js/app.jsx

resources/js/components/AppShell.jsx
resources/js/components/appNavigation.js
resources/js/components/CommandPalette.jsx
resources/js/components/OperationCommandMenu.jsx
resources/js/components/PwaStatus.jsx

resources/js/components/Admin/AdminLayout.jsx
resources/js/components/Admin/PageHeader.jsx

resources/js/pages/Dashboard.jsx
resources/js/pages/Admin/Index.jsx

resources/js/pages/Scanner.jsx
resources/js/pages/StageControl.jsx
resources/js/pages/Stage.jsx

app/Http/Middleware/HandleInertiaRequests.php
```

Do not begin architecture changes before understanding these files.

---

# PHASE 1 — Fix Critical Audience-Stage UI Leakage

## Goal

Ensure Stage Display can never display normal PWA/workstation notices.

This phase is mandatory before broader redesign work.

### 1.1 Fix Global PWA Rendering

Current global structure effectively renders:

```jsx
<App {...props} />
<PwaStatus />
```

for every page.

Change PWA status behavior so audience Stage Display does not receive normal PWA overlays.

Possible implementation approaches:

#### Preferred

Make `PwaStatus` route-aware and suppress workstation messages when the current page is Stage.

OR

Create a page-layout mechanism where Stage explicitly disables global workstation UI.

Do not use fragile CSS such as:

```css
.stage-page ~ .pwa-status {
    display: none;
}
```

The behavioral decision should be expressed in React/application logic.

### 1.2 Stage Offline Behavior

Do not simply hide every connection failure.

The Stage page itself already understands realtime state.

If the Stage loses realtime connectivity:

- preserve the last authoritative presentation;
- recover when possible;
- do not show installation/update UI;
- do not expose technical diagnostics to the audience.

Audience-safe wording may be used if a visible fallback is necessary.

### 1.3 Required Test

Add browser coverage ensuring `/stage` does NOT contain:

```text
Install this workstation app
Application update ready
Reload and update
```

under Stage conditions.

## Phase 1 Definition of Done

- Stage never renders PWA installation controls.
- Stage never renders normal workstation update controls.
- Existing Stage state/audio behavior remains unchanged.
- Existing Stage browser tests pass.

---

# PHASE 2 — Introduce Working Ceremony Context

## Goal

Separate administrative ceremony preparation from live ceremony activation.

This is the most important architectural phase.

## 2.1 Terminology

Use these terms consistently in code and UI:

```text
Live Ceremony
Working Ceremony
```

Avoid ambiguous names such as:

```text
currentCeremony
selectedCeremony
```

unless scope makes their meaning obvious.

## 2.2 Live Ceremony

Existing active-ceremony logic remains authoritative.

Do not change the rule requiring exactly one active ceremony for live operations.

Existing middleware logic may remain responsible for:

```text
ceremonyContext.state
ceremonyContext.active
```

However, rename to a clearer structure if it can be done safely.

Preferred future shape:

```js
liveCeremonyContext: {
    state: 'ready' | 'none' | 'conflict' | 'unavailable',
    ceremony: {
        id,
        name,
        academic_year,
        event_date
    } | null
}
```

Backward compatibility can be maintained temporarily.

## 2.3 Working Ceremony

Introduce a separate administrative context.

Preferred behavior:

- Administrator can choose a ceremony.
- Data encoder can choose a ceremony they are allowed to access.
- Choice persists across relevant admin navigation.
- Changing the working ceremony does not change ceremony status.
- Changing the working ceremony does not affect Scanner.
- Changing the working ceremony does not affect Stage Control.
- Changing the working ceremony does not affect Stage Display.

## 2.4 Persistence Strategy

Recommended first implementation:

Store the Working Ceremony ID in the authenticated session.

Example conceptual key:

```text
working_ceremony_id
```

Do not use only React local state because context must survive page transitions.

Do not make browser localStorage the authoritative source.

The server should validate that the selected ceremony still exists and is accessible.

If the stored ceremony becomes invalid:

1. Prefer the live ceremony if accessible.
2. Otherwise choose the most relevant prepared ceremony.
3. Otherwise return no working ceremony.

Do not activate anything automatically.

## 2.5 Add Shared Working Ceremony Context

Expose through Inertia shared props.

Example:

```js
workingCeremonyContext: {
    ceremony: null | {
        id,
        name,
        academic_year,
        event_date,
        status
    },
    available: [
        ...
    ]
}
```

Avoid sending large unnecessary ceremony payloads globally.

Keep it lightweight.

## 2.6 Add Ceremony Switch Endpoint

Create a safe authenticated endpoint.

Example:

```text
POST /workspace/ceremony
```

Payload:

```json
{
  "ceremony_id": 123
}
```

The endpoint must:

- validate authorization;
- validate ceremony existence;
- store session selection;
- redirect back safely.

It must NOT modify ceremony status.

It must NOT activate a ceremony.

## 2.7 Create Working Ceremony Switcher

Add a ceremony switcher to the application shell for authorized preparation roles.

Example desktop UI:

```text
Working ceremony
UM Commencement 2027
2026–2027 · Draft
          ▾
```

The switcher should be clearly different from:

```text
LIVE
UM Commencement 2026
```

Never imply that switching workspaces changes live operations.

## 2.8 Visual Distinction

Use wording, iconography, and status.

Example:

```text
Working on
UM Commencement 2027
DRAFT
```

and separately:

```text
Live ceremony
UM Commencement 2026
ACTIVE
```

Do not depend on color alone.

## Phase 2 Acceptance Tests

Verify:

### Test A

Select Ceremony A as Working Ceremony.

Then verify:

```text
Scanner live ceremony = unchanged
Stage Control live ceremony = unchanged
Stage Display live ceremony = unchanged
```

### Test B

Navigate:

```text
Graduates
→ QR
→ Announcements
→ Stage Design
```

Working Ceremony remains Ceremony A.

### Test C

Logout and sign back in.

Session behavior may reset depending on implementation, but it must resolve to a valid predictable ceremony.

### Test D

Delete or make the selected ceremony inaccessible.

System must recover without crashing.

---

# PHASE 3 — Replace the Navigation Architecture

## Goal

Make navigation represent user workflow rather than backend modules.

## 3.1 Rewrite `buildNavigation()`

Primary file:

```text
resources/js/components/appNavigation.js
```

New target groups:

```text
OVERVIEW

CEREMONY WORKSPACE

LIVE OPERATIONS

REVIEW

SYSTEM
```

## 3.2 Target Navigation

Conceptually:

```text
Overview

CEREMONY WORKSPACE
  Ceremony overview
  Setup
  Graduates
  Import
  QR & Media
  Announcements
  Stage Design
  Readiness

LIVE OPERATIONS
  Scanner
  Stage Control
  Stage Display

REVIEW
  Ceremony Summary
  Scan Logs
  Stage Events

SYSTEM
  Connect Devices
  Backups
  Operator Accounts
  Audit Logs
```

Hide unauthorized items exactly as current ability-based navigation does.

## 3.3 Do Not Add a Separate Queue Page

Stage Control already contains:

- current graduate;
- next graduate;
- waiting queue;
- skipped students;
- recent actions.

Do not create another primary Queue destination unless future requirements truly need an independent workflow.

## 3.4 All Ceremonies Access

Users still need to manage multiple ceremonies.

Provide:

```text
Manage ceremonies
```

inside the Working Ceremony switcher or Ceremony Workspace.

Do not force "Ceremonies" to remain a prominent peer alongside every ceremony preparation tool.

## 3.5 Stage Display Opens Externally

Keep Stage Display opening in a new tab/window where appropriate.

Continue showing the external-window indicator.

## 3.6 Navigation Active States

Active states must recognize ceremony-scoped routes.

For example:

```text
/admin/ceremonies/12/students
/admin/students?ceremony_id=12
```

should correctly identify:

```text
Graduates
```

during the transition architecture.

Avoid fragile URL matching spread across many components.

Centralize matching helpers where possible.

---

# PHASE 4 — Create the Ceremony Workspace

## Goal

Turn a ceremony from a simple CRUD record into the main preparation workspace.

## 4.1 Ceremony Workspace Overview

The primary ceremony page should become:

```text
CEREMONY OVERVIEW
```

not merely:

```text
Ceremony configuration
```

Target layout:

```text
UM Commencement 2027

2026–2027
June 18, 2027
UM Gymnasium
Draft

Preparation readiness
----------------------------------
Graduate records          320
Eligibility issues          4
Photos missing              6
QR ready                  314
Announcements ready       298
Announcements review       16
Stage design             Ready

Next action
16 announcement files require review

[ Review announcements ]

Preparation sections
Graduates
Import
QR & Media
Announcements
Stage Design
Setup
```

Do not create decorative KPI cards with equal weight.

Use restrained operational rows/panels.

## 4.2 Ceremony Local Navigation

Add a reusable ceremony subnavigation.

Possible component:

```text
resources/js/components/Admin/CeremonyWorkspaceNav.jsx
```

Target items:

```text
Overview
Setup
Graduates
Import
QR & Media
Announcements
Stage Design
Readiness
```

This can appear:

- below PageHeader;
- as a compact horizontal tab bar;
- or as a secondary sidebar inside the content region.

Prefer a compact desktop-style tab/toolbar.

Do not create another giant sidebar beside the existing global sidebar.

## 4.3 Ceremony Context Header

Create a reusable header/context component.

Possible:

```text
CeremonyContextHeader.jsx
```

Must display:

- ceremony name;
- academic year;
- status;
- event date if available.

Optional:

- live indicator;
- readiness status.

## 4.4 Setup

The existing Ceremony Edit/Form becomes:

```text
Setup
```

within the Ceremony Workspace.

Keep:

- identity;
- date;
- venue;
- status;
- presentation mode;
- stage theme assignment.

Do not combine unrelated readiness or graduate management into Setup.

## 4.5 Route Migration Strategy

Avoid immediately deleting legacy routes.

Existing routes such as:

```text
/admin/students
/admin/students/import
/admin/students/qr-codes
/admin/voice-generation
```

can remain temporarily.

Add ceremony-scoped URLs or context-aware navigation first.

Preferred long-term routing:

```text
/admin/ceremonies/{ceremony}
/admin/ceremonies/{ceremony}/setup
/admin/ceremonies/{ceremony}/graduates
/admin/ceremonies/{ceremony}/import
/admin/ceremonies/{ceremony}/qr
/admin/ceremonies/{ceremony}/announcements
/admin/ceremonies/{ceremony}/stage-design
/admin/ceremonies/{ceremony}/readiness
```

However:

Do not duplicate backend logic unnecessarily.

Routes may render existing Inertia page components with ceremony-specific props.

---

# PHASE 5 — Ceremony-Scope Existing Preparation Workflows

## Goal

Remove repeated ceremony selection and make workflows feel connected.

## 5A — Graduates

Current primary page:

```text
resources/js/pages/Admin/Students/Index.jsx
```

When opened from Ceremony Workspace:

- default filter to Working Ceremony;
- hide or de-emphasize ceremony selector;
- show current ceremony context clearly.

Target header:

```text
Graduates
UM Commencement 2027

320 graduates
```

Primary actions:

```text
Import graduates
Add graduate
QR preparation
```

Do not show all ceremonies unless the operator deliberately enters a cross-ceremony view.

### 5A.1 Improve Table Actions

Current table may expose several text links:

```text
View
QR card
Edit
```

Reduce repeated row-action clutter where possible.

Preferred:

- clicking graduate name opens detail;
- one overflow/context action can contain less-common actions;
- preserve keyboard accessibility.

Do not hide critical status information behind menus.

## 5B — Import

Keep the existing multi-step wizard.

Target path:

```text
Working Ceremony
→ Graduates
→ Import
```

When ceremony is already known:

remove the redundant destination ceremony selection.

Display instead:

```text
Destination
UM Commencement 2027
```

Add:

```text
Change ceremony
```

only if needed.

Changing the ceremony should deliberately change Working Ceremony, not silently alter one field.

Keep all current safeguards:

- CSV validation;
- media ZIP validation;
- mapping;
- preview;
- confirmation;
- summary.

## 5C — QR & Media

Current QR page is strong operationally.

Integrate it into Ceremony Workspace.

When opened:

```text
QR & Media
UM Commencement 2027
```

The ceremony selection should already be established.

Retain filters:

- college;
- program;
- eligibility;
- token state;
- sequence range.

Retain batch selection.

Retain print instructions.

### 5C.1 Consider Media Readiness Integration

Do not immediately build a massive new media dashboard.

Instead, provide top-level summary filters:

```text
All
Ready
Missing photo
Missing audio
Needs audio review
Rejected audio
```

Reuse current student filtering logic where possible.

## 5D — Announcements

This is a major target.

Current:

```text
/admin/voice-generation
```

depends too strongly on the active ceremony.

Refactor it to accept the Working Ceremony explicitly.

Preferred route:

```text
/admin/ceremonies/{ceremony}/announcements
```

Do not determine preparation ceremony by querying the active ceremony.

### 5D.1 Announcement Workspace Sections

Keep the strong existing workflow:

```text
Overview
Provider
Voice comparison
Generate
Review queue
```

Existing process:

```text
1. Select provider
2. Compare voices
3. Preview wording and pronunciation
4. Generate
5. Review and approve
```

is good.

Do not discard it.

### 5D.2 Review Queue

Keep:

- ceremony sequence ordering;
- review-next behavior;
- approved / pending counts.

Add easy return to:

```text
Announcements
```

after reviewing an individual graduate.

Avoid forcing:

```text
Graduate Detail → browser Back → Voice Generation
```

Use explicit contextual links.

## 5E — Stage Design

Current Stage Design library is reusable across ceremonies.

Preserve that concept.

Distinguish:

```text
Stage Design Library
```

from:

```text
Ceremony Stage Design
```

Within Ceremony Workspace:

```text
Stage Design
UM Commencement 2027
```

show:

- currently applied design;
- live preview;
- logo;
- background;
- layout;
- colors;
- show portrait setting.

A secondary action can open:

```text
Manage reusable design library
```

Do not make the library the primary ceremony workflow.

## 5F — Readiness

Create or refactor a Ceremony Readiness view.

Move the strongest readiness logic currently found in `/admin` into this workspace.

Required sections:

```text
Blocking issues
Graduate readiness
QR readiness
Media readiness
Announcement readiness
Stage configuration
Live-operation readiness
Backup/device readiness where relevant
```

Every blocker should provide one direct action.

Example:

```text
16 announcements need approval
[ Review announcements ]
```

Not:

```text
16 issues
```

without a recovery path.

---

# PHASE 6 — Merge Dashboard and Admin Overview

## Goal

Remove the two-dashboard experience.

## 6.1 Keep `/dashboard` as the Main Home

Recommended:

```text
/dashboard
```

becomes the unified Overview.

`/admin` can redirect to `/dashboard` after migration, unless required for compatibility.

## 6.2 Administrator Overview

Target content:

```text
Operations overview

Working ceremony
UM Commencement 2027
Preparation: 91%

Readiness issues
4 eligibility reviews
16 announcement reviews
6 missing photos

Live ceremony
UM Commencement 2026
Live operations ready

Primary action
Review announcement queue
```

Do not make it an analytics dashboard.

No decorative charts unless they answer an operational question.

## 6.3 Role-Specific Overview

Continue using the current role-aware concept.

### Scanner Operator

Primary:

```text
Open Scanner
```

### Stage Operator

Primary:

```text
Open Stage Control
```

### Data Encoder

Primary:

```text
Continue working ceremony preparation
```

### Technical Operator

Primary:

```text
System health / logs / device connection
```

### Administrator

Primary:

```text
Resolve ceremony readiness
```

## 6.4 Remove Redundant Link Grids

Do not duplicate the entire sidebar in the Overview.

Show:

- current status;
- important blockers;
- next action;
- a small number of primary workspaces.

The sidebar already provides navigation.

---

# PHASE 7 — Redesign Graduate Detail Information Architecture

## Goal

Reduce the long mixed-purpose Graduate Detail page.

## 7.1 Create Graduate Local Navigation

Recommended sections:

```text
Overview
Academic
Media & QR
Announcement
History
```

This may be implemented as:

- URL query section;
- nested routes;
- accessible local tabs.

Prefer real navigable sections if implementation is manageable.

Do not use fragile client-only tab state if deep links are required for announcement review anchors.

## 7.2 Graduate Overview

Show only high-value information.

Example:

```text
Robert Jhon Aracena
Sequence 120
BS Computer Science

Eligibility      Ready
Photo            Ready
QR               Ready
Announcement     Needs review
Stage            Not presented

[ Edit ]
[ Review announcement ]
[ Open QR ]
```

## 7.3 Academic

Move:

- student number;
- names;
- suffix;
- display name;
- announcement name;
- pronunciation;
- college;
- program;
- major;
- honors;
- sequence.

Keep editing separate from read-only summary.

## 7.4 Media & QR

Show:

```text
Portrait
QR readiness
QR card
Audio file readiness
```

Avoid mixing full voice-generation workflow here.

## 7.5 Announcement

Move:

- announcement preview;
- generated candidates;
- reviewer notes;
- audio player;
- approve;
- reject;
- regenerate.

This becomes the logical destination for Voice Generation review links.

## 7.6 History

Move:

- stage status/history;
- queue records;
- scan-log count;
- created/updated;
- protection status.

Dangerous deletion can remain at the bottom of Overview or History.

---

# PHASE 8 — Replace Browser-History Back Navigation

## Goal

Make navigation predictable.

## 8.1 Modify `PageHeader`

Current behavior should no longer default to `window.history.back()`.

Change PageHeader API.

Example:

```jsx
<PageHeader
    backHref="/admin/ceremonies/12/graduates"
    backLabel="Graduates"
    ...
/>
```

If no parent exists:

do not render a back button.

Do not guess from browser history.

## 8.2 Parent Navigation Rules

Examples:

```text
Student Detail
← Graduates

QR Card
← Graduate

Ceremony Setup
← Ceremony Overview

Import Mapping
← Import source

Announcement Settings
← Announcements

Announcement Review
← Announcements
```

## 8.3 Optional Breadcrumb

Use only when hierarchy has at least three meaningful levels.

Example:

```text
Ceremonies / UM Commencement 2027 / Robert Aracena
```

Keep compact.

Do not create large website-style breadcrumb bars.

---

# PHASE 9 — Standardize Dialogs and Confirmation UX

## Goal

Remove inconsistent native `window.confirm()` behavior.

## 9.1 Create Reusable Confirmation Dialog

Possible component:

```text
resources/js/components/ConfirmDialog.jsx
```

Requirements:

- `role="alertdialog"` where destructive;
- proper accessible heading;
- description;
- cancel button;
- explicit action button;
- Escape closes;
- Tab remains trapped;
- focus moves to Cancel by default;
- focus returns to initiating control;
- supports loading state.

Use Stage Control's confirmation implementation as the behavioral reference.

## 9.2 Migrate Important Native Confirmations

Examples:

- ceremony live status changes;
- delete ceremony;
- delete graduate;
- approve/reject audio where confirmation remains necessary;
- delete Stage Design;
- QR regeneration if confirmed;
- other destructive admin operations.

Do not add confirmation for normal safe actions.

---

# PHASE 10 — Improve Global System Status Presentation

## Goal

Keep system state visible without repeating the same information excessively.

Current AppShell includes:

- ceremony state in toolbar;
- connection state in toolbar;
- system status footer.

Review redundancy.

## 10.1 Target

Toolbar:

```text
Working Ceremony
Live Ceremony
Connection
Command
User
```

Status bar can focus on low-level persistent workstation state:

```text
Network
Realtime
Time
```

Avoid showing the exact same ceremony state twice unless useful.

## 10.2 Operational Modes

Scanner and Stage Control remain allowed to have stronger local status displays.

Do not remove those just because AppShell has status information.

Specialized screens need more visible state.

---

# PHASE 11 — Command Palette Improvements

## Goal

Make Ctrl+K useful for ceremony-centered workflow.

Do only after the new context architecture is stable.

## 11.1 Add Contextual Commands

Examples:

```text
Open working ceremony
Switch working ceremony
Open graduates
Import graduates
Open QR preparation
Open announcement review
Open Stage Control
Open Scanner
Open Stage Display
Open ceremony readiness
```

## 11.2 Optional Graduate Search

Only add if performance and backend behavior are safe.

Example:

```text
Graduate: Robert Aracena
```

Selecting should open that graduate in the Working Ceremony.

Do not preload every graduate into global Inertia props.

Use controlled remote search if needed.

---

# PHASE 12 — CSS and Design-System Cleanup

## Goal

Reduce styling drift without performing a risky visual rewrite.

This phase occurs after the new information architecture is functional.

## 12.1 Preserve Current Visual Direction

Keep:

- restrained light interface;
- desktop application shell;
- modest border radius;
- neutral surfaces;
- blue accent;
- status colors;
- compact tables;
- limited motion;
- system fonts;
- no decorative gradients;
- no glassmorphism.

Do not chase a new trendy style.

## 12.2 Split `app.css`

Gradually separate responsibilities.

Suggested structure:

```text
resources/css/
│
├── tokens.css
├── base.css
├── shell.css
│
├── components/
│   ├── buttons.css
│   ├── forms.css
│   ├── tables.css
│   ├── status.css
│   ├── dialogs.css
│   ├── navigation.css
│   └── workspace.css
│
└── modes/
    ├── scanner.css
    ├── stage-control.css
    └── stage-display.css
```

This is a recommended structure, not a mandatory one-shot migration.

Move code gradually.

Do not create a huge CSS-only commit that risks unintended regression.

## 12.3 Normalize Tokens

Move toward one semantic token family.

Preferred:

```text
--color-*
--space-*
--radius-*
--motion-*
--z-*
```

Gradually remove duplicate legacy:

```text
--gss-*
```

only when no references remain.

Do not remove aliases while still used.

## 12.4 Typography

Continue system font stack.

Do not introduce external web fonts unless there is a clear product reason.

This is a local-first operational application.

Reliability is more important than branding typography.

---

# PHASE 13 — Update `design.md`

The existing design document is strong but no longer fully aligned with the desired architecture.

Update it after the architecture is implemented.

## 13.1 Update Information Architecture

Replace the older primary navigation section with:

```text
Overview

Ceremony Workspace
- Overview
- Setup
- Graduates
- Import
- QR & Media
- Announcements
- Stage Design
- Readiness

Live Operations
- Scanner
- Stage Control
- Stage Display

Review
- Ceremony Summary
- Scan Logs
- Stage Events

System
- Connect Devices
- Backups
- Operator Accounts
- Audit Logs
```

## 13.2 Add Context Rules

Document explicitly:

```text
Working Ceremony != Live Ceremony
```

Working Ceremony:

- user-selected;
- administrative;
- safe;
- does not activate.

Live Ceremony:

- status-controlled;
- operational;
- exactly one for live operations.

## 13.3 Remove Incorrect Tailwind Reference

If Tailwind is still not installed/used, remove Tailwind from the documented current stack.

Do not install Tailwind just to match the document.

---

# PHASE 14 — Browser and UX Regression Testing

## Goal

Prove not only that backend workflows still work, but that the redesigned navigation works.

## 14.1 Preserve Existing Critical Browser Tests

Do not remove tests covering:

- Scanner;
- Stage Control;
- Stage Display;
- realtime behavior;
- accessibility;
- double-submit prevention;
- recovery;
- role restrictions.

## 14.2 Add Preparation Journey Test

Create a browser test covering:

```text
Sign in as administrator

Open Overview

Select Working Ceremony

Open Graduates

Open Import

Return to Graduates

Open QR & Media

Open Announcements

Open Stage Design

Open Readiness

Return to Ceremony Overview
```

At every step assert:

```text
Working Ceremony = expected ceremony
```

## 14.3 Live Ceremony Isolation Test

Scenario:

```text
Live Ceremony = Ceremony A
Working Ceremony = Ceremony B
```

Assert:

```text
Scanner uses Ceremony A
Stage Control uses Ceremony A
Stage Display uses Ceremony A

Graduates workspace uses Ceremony B
QR workspace uses Ceremony B
Announcements workspace uses Ceremony B
Readiness uses Ceremony B
```

This is a critical regression test.

## 14.4 Accessibility Testing

Continue Axe coverage.

Add Axe testing to:

- Dashboard;
- Ceremony Workspace;
- Graduates;
- Graduate Detail;
- Import;
- QR;
- Announcements;
- Stage Design.

## 14.5 Viewports

Minimum desktop QA:

```text
1366 × 768
1536 × 864
1920 × 1080
```

Scanner and Stage Control must keep primary actions above the fold at supported workstation sizes.

## 14.6 Visual Regression Screenshots

Add screenshots for:

```text
overview-1366x768
ceremony-workspace-1366x768
graduates-1366x768
graduate-detail-1366x768
import-1366x768
qr-preparation-1366x768
announcements-1366x768
stage-design-1366x768
scanner-1366x768
stage-control-1366x768
stage-idle-1366x768
stage-graduate-1366x768
stage-holding-1366x768
```

Do not create screenshots requiring highly unstable timestamps unless masked or controlled.

---

# 15. Component Architecture Recommendations

Reuse existing components where possible.

Potential new reusable components:

```text
WorkingCeremonySwitcher.jsx

CeremonyWorkspaceNav.jsx

CeremonyContextHeader.jsx

ReadinessStatus.jsx

ConfirmDialog.jsx

WorkspaceSection.jsx

StatusSummaryRow.jsx
```

Do not create components merely because two elements look vaguely similar.

Create abstractions only when the interaction or visual pattern is repeated.

---

# 16. Route Compatibility Strategy

Avoid breaking deep links unnecessarily.

If new ceremony-scoped routes replace old global routes, initially support both.

Examples:

Old:

```text
/admin/voice-generation
```

New:

```text
/admin/ceremonies/{ceremony}/announcements
```

Temporary compatibility:

```text
/admin/voice-generation
```

may redirect to:

```text
current Working Ceremony → Announcements
```

if a valid Working Ceremony exists.

Otherwise:

```text
redirect to ceremony selection
```

Do not silently use the Live Ceremony as a preparation fallback unless explicitly intended.

---

# 17. Data and Backend Safety Requirements

The UI redesign must not:

- expose QR tokens;
- weaken QR regeneration rules;
- allow cross-ceremony graduate mutations;
- make active-ceremony conflicts possible;
- bypass Laravel policies;
- move important business validation into React only;
- weaken audio approval rules;
- allow Stage operations against a Working Ceremony;
- allow Scanner to use a Working Ceremony;
- change durable queue semantics;
- weaken transaction behavior;
- replace server authority with client state.

Working Ceremony is navigation context.

It is not operational authority.

---

# 18. UX Writing Rules

Use operator-centered wording.

Prefer:

```text
16 announcements need review
```

instead of:

```text
Audio status exception count: 16
```

Prefer:

```text
No active ceremony
Activate a ceremony before opening Scanner.
```

instead of:

```text
Invalid ceremony context.
```

Prefer:

```text
Graduate added to queue
```

instead of:

```text
Request processed successfully.
```

Every error should answer:

1. What happened?
2. Was anything saved?
3. What should the operator do?
4. What action can they take now?

---

# 19. Visual Rules

The redesign must continue avoiding common AI-generated dashboard patterns.

Do NOT use:

- huge decorative dashboard cards;
- gradients everywhere;
- glowing borders;
- excessive shadows;
- excessive rounded containers;
- random icon backgrounds;
- glassmorphism;
- giant marketing headlines;
- decorative charts without operational value;
- arbitrary gradients representing status;
- large empty hero areas;
- floating cards everywhere;
- unnecessary animations.

Prefer:

- compact toolbars;
- clear sections;
- structured lists;
- tables where appropriate;
- status rows;
- restrained borders;
- consistent spacing;
- clear typography;
- obvious primary action;
- nearby recovery actions.

---

# 20. Important Mode Separation

These four surfaces have different UX requirements.

Do not force them into one visual template.

## Administration / Ceremony Workspace

Can have:

- AppShell;
- global navigation;
- ceremony switcher;
- tables;
- forms;
- filters;
- contextual navigation.

## Scanner

Must prioritize:

```text
Scan → Confirm → Next Graduate
```

No unrelated administration clutter.

## Stage Control

Must prioritize:

```text
Current → Next → Queue → Safe Stage Action
```

No unrelated preparation clutter.

## Stage Display

Must prioritize:

```text
Audience presentation only
```

No navigation.

No technical controls.

No PWA prompts.

---

# 21. Required Implementation Order

Codex must follow this sequence.

Do NOT jump directly to CSS polish.

```text
1. Baseline tests
2. Stage PWA safety fix
3. Working Ceremony backend/session context
4. Working Ceremony switcher
5. Navigation restructure
6. Ceremony Workspace shell
7. Ceremony Overview
8. Graduates integration
9. Import integration
10. QR & Media integration
11. Announcement ceremony scoping
12. Stage Design integration
13. Readiness integration
14. Dashboard/Admin merge
15. Graduate Detail restructuring
16. Semantic back navigation
17. Shared confirmation dialog
18. Command palette improvements
19. CSS cleanup
20. design.md update
21. Expanded Playwright/visual QA
```

This order is mandatory unless a technical dependency requires a minor adjustment.

---

# 22. Commit Strategy

Use small, reviewable commits.

Suggested sequence:

```text
fix(ui): prevent PWA status from leaking into audience stage

feat(workspace): add working ceremony session context

feat(shell): add working ceremony switcher

refactor(nav): reorganize navigation around ceremony workspace

feat(ceremony): add ceremony workspace navigation

feat(ceremony): add ceremony preparation overview

refactor(students): scope graduate workspace to working ceremony

refactor(import): integrate import with ceremony workspace

refactor(qr): integrate QR preparation with ceremony workspace

refactor(announcements): scope voice generation to working ceremony

refactor(stage-design): integrate ceremony stage design workflow

feat(readiness): add ceremony readiness workspace

refactor(dashboard): merge administration readiness into overview

refactor(students): split graduate detail workflow

refactor(ui): replace history back with semantic parent navigation

refactor(ui): standardize destructive confirmations

refactor(css): reduce legacy design-system duplication

test(ui): add preparation and working/live ceremony regression coverage

docs(design): update GSS UI architecture
```

Do not combine every phase into one commit.

---

# 23. Definition of Done for the Entire Redesign

The redesign is complete only when all of the following are true.

## Ceremony context

- Working Ceremony exists separately from Live Ceremony.
- Selecting a Working Ceremony does not activate it.
- Preparation screens preserve the selected Working Ceremony.
- Scanner always uses the Live Ceremony.
- Stage Control always uses the Live Ceremony.
- Stage Display always uses the Live Ceremony.

## Navigation

- User does not need to repeatedly navigate through Ceremonies to reach related preparation tools.
- Ceremony preparation tools feel like one workspace.
- Current ceremony is always obvious.
- Parent navigation is deterministic.
- `/dashboard` is the primary application overview.
- Duplicate overview behavior is removed.

## Graduates

- Graduate list defaults to Working Ceremony.
- Graduate Detail is no longer one excessively long mixed-purpose page.
- Announcement review returns to the correct ceremony context.

## Import

- Import retains existing validation.
- Known Working Ceremony does not need redundant reselection.

## QR

- QR preparation remains safe and batch-capable.
- QR workflow clearly belongs to current ceremony.

## Announcements

- Voice generation no longer depends on whichever ceremony happens to be live.
- Announcement generation/review is ceremony-scoped.

## Stage

- No PWA installation or update UI can appear on Stage Display.
- Existing stage recovery remains intact.

## Scanner / Stage Control

- No regression in keyboard handling.
- No regression in realtime behavior.
- No regression in failure recovery.
- No regression in duplicate-submit protection.

## Accessibility

- Axe checks remain clean.
- Dialog focus behavior remains correct.
- Keyboard navigation remains supported.
- Status does not rely on color alone.

## Testing

- Existing critical browser tests pass.
- Working-vs-live ceremony test exists.
- Complete ceremony preparation navigation test exists.
- Major UI screens have visual regression coverage.

---

# 24. Final Product Experience

After implementation, a typical administrator workflow should feel like this:

```text
LOGIN
  ↓
OVERVIEW

Working Ceremony
UM Commencement 2027

Readiness
91% prepared

16 announcements need review
6 photos missing
4 eligibility issues

[ Continue preparation ]
  ↓
CEREMONY WORKSPACE

UM Commencement 2027
DRAFT

Overview
Setup
Graduates
Import
QR & Media
Announcements
Stage Design
Readiness
  ↓
GRADUATES

320 graduates

[ Add Graduate ]
[ Import ]
[ QR Preparation ]
  ↓
ANNOUNCEMENTS

298 approved
16 need review
6 missing

[ Review Next ]
  ↓
READINESS

Eligibility       316 / 320
Photos            314 / 320
QR                320 / 320
Announcements     304 / 320
Stage Design      Ready
Backup            Ready

Remaining blockers: 22

[ Resolve blockers ]
```

When the ceremony is ready:

```text
Setup
→ Change status to Active
→ explicit confirmation
```

Then the live workflow is clearly separate:

```text
LIVE OPERATIONS

Scanner
Stage Control
Stage Display
```

The administrator may still prepare another ceremony without affecting the live one.

Example:

```text
LIVE CEREMONY
UM Commencement 2026

WORKING CEREMONY
UM Commencement 2027
```

This must be a valid and fully supported state.

---

# 25. Final Instruction to Codex

Do not interpret this task as:

> "Make the website prettier."

Interpret it as:

> "Refactor the Graduation Stage System's frontend information architecture so ceremony preparation becomes one coherent ceremony-centered workspace while preserving the proven reliability of Scanner, Stage Control, Stage Display, backend authorization, operational state management, and recovery behavior."

Prioritize in this order:

```text
1. Ceremony safety
2. Correct system state
3. Workflow clarity
4. Error prevention
5. Recovery
6. Navigation speed
7. Accessibility
8. Consistency
9. Visual refinement
```

Whenever uncertain whether to change existing Scanner, Stage Control, Stage Display, backend business logic, authorization, transaction behavior, or realtime recovery:

**preserve the existing behavior unless this plan explicitly requires a change.**
