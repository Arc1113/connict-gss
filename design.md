# Graduation Stage System — Design Specification

> **File:** `design.md`  
> **Status:** Approved design direction for UI/UX refinement  
> **Applies to:** Existing Graduation Stage System release candidate and all future interface changes  
> **Primary stack:** Laravel, React, Inertia.js, Vite, CSS, PostgreSQL, Laravel Reverb, Laravel Echo
> **Runtime:** Local network, multi-laptop, installable PWA  
> **Last updated:** 2026-07-31

---

## 1. Purpose of This Document

This document is the visual, interaction, and usability source of truth for the Graduation Stage System.

All agents and developers working on the frontend must read this file before:

- creating a new screen;
- redesigning an existing screen;
- changing application navigation;
- creating reusable components;
- changing colors, typography, spacing, or motion;
- adding keyboard shortcuts;
- modifying scanner, queue, stage-control, or stage-display behavior;
- performing visual cleanup or responsive work.

This document supplements the engineering playbook. It does not replace business rules, authorization, database constraints, recovery procedures, or ceremony-readiness requirements.

When this document conflicts with an existing safety, data-integrity, or ceremony-recovery rule, the safer operational behavior takes priority.

---

# 2. Product Positioning

## 2.1 What the GSS Is

The Graduation Stage System is a **desktop-class ceremony operations application**.

It is used by operators during a live graduation ceremony to:

- scan graduate QR codes;
- monitor accepted and rejected scans;
- manage the stage queue;
- present, skip, return, replay, hold, pause, and resume;
- monitor audio and device readiness;
- recover from mistakes and disconnections;
- manage ceremony data before the event;
- review logs, reports, backups, and readiness information.

The interface must behave like a reliable control console, not a marketing website.

## 2.2 What the GSS Is Not

The GSS must not look or behave like:

- a product landing page;
- a generic SaaS dashboard;
- an analytics-first business application;
- a social application;
- a decorative school website;
- a mobile-first consumer application;
- an experimental glassmorphism interface;
- an Apple product advertisement;
- a card grid with equal visual weight everywhere.

## 2.3 Product Character

Every screen should express these qualities:

- **Operational**
- **Fast**
- **Calm**
- **Reliable**
- **Formal**
- **Academic**
- **Focused**
- **Clear**
- **Recoverable**
- **Locally dependable**

The intended visual impression is:

> A professional graduation ceremony control application with the efficiency of Linear, the command speed of Raycast, the restraint of Apple productivity software, and the practical interaction behavior of Microsoft Fluent.

---

# 3. Design Priorities

Use the following priority order when design goals compete:

1. Ceremony safety
2. Correctness and clear system state
3. Error prevention and recovery
4. Speed of repeated operations
5. Accessibility and readability
6. Clear information hierarchy
7. Consistency
8. Visual refinement
9. Decorative appeal

A visually attractive screen that slows an operator or hides a problem is a design failure.

---

# 4. Core UX Principles

## Ceremony Context Rules

**Working Ceremony != Live Ceremony.** The Working Ceremony is an operator-selected, administrative preparation context and never activates a ceremony. The Live Ceremony is status-controlled and is the only context used by Scanner, Stage Control, and Stage Display.

## 4.1 One Screen, One Primary Purpose

Each screen must have one dominant user goal.

Examples:

- Scanner: capture and confirm scans.
- Stage Control: manage the live stage sequence.
- Stage Display: present the graduate to the audience.
- Student List: find and manage student records.
- Audio Readiness: verify announcement files.
- Backup and Restore: protect and recover system data.

Secondary information must not compete with the screen’s primary purpose.

## 4.2 System State Must Always Be Understandable

Operators must be able to answer these questions immediately:

- Which ceremony is active?
- Is the ceremony live, paused, prepared, or completed?
- Is the scanner ready?
- Is the stage display connected?
- Is Reverb connected?
- Is audio ready?
- Who is currently presented?
- Who is next?
- Was the last action successful?
- Can the last action be reversed?
- What should the operator do when something fails?

## 4.3 Repeated Actions Must Be Fast

Common operations must support:

- predictable placement;
- keyboard access;
- large click targets;
- minimal pointer travel;
- minimal modal dialogs;
- immediate feedback;
- safe undo where possible;
- preserved focus;
- no unnecessary page transitions.

## 4.4 Critical Actions Must Be Deliberate

Actions that can disrupt the ceremony require stronger protection.

Examples:

- pause ceremony;
- end ceremony;
- clear the live stage;
- restore a backup;
- delete a ceremony;
- deactivate an active operator;
- reset or clear a queue.

Use a confirmation dialog that states:

1. the action;
2. the impact;
3. the affected ceremony or record;
4. the safe cancellation option.

Do not require confirmation for every normal reversible action.

## 4.5 Recovery Must Be Near the Error

Errors must state:

- what happened;
- what was affected;
- whether data was saved;
- what the operator should do next;
- which recovery action is available.

Bad:

> Something went wrong.

Good:

> The graduate was saved, but the stage display did not receive the update. Check the stage connection, then select **Resend stage state**.

## 4.6 Do Not Depend on Color Alone

Every status must combine color with at least one of:

- text;
- icon;
- shape;
- border;
- position;
- accessible label.

## 4.7 Smoothness Must Never Delay Work

Motion should clarify state changes, not decorate the interface.

No transition may block scanning, queue control, navigation, or recovery.

---

# 5. Design Reference Hierarchy

References guide principles, not pixel-for-pixel copying.

## 5.1 Linear — Primary Structural Reference

Borrow:

- compact application shell;
- persistent sidebar;
- dense but readable lists;
- contextual toolbars;
- command palette;
- keyboard navigation;
- restrained borders;
- subtle selected states;
- efficient spacing.

Do not copy:

- issue-tracker terminology;
- dark developer-tool styling by default;
- controls unrelated to ceremony operations.

## 5.2 Raycast — Command and Search Reference

Borrow:

- universal command center;
- immediate search;
- arrow-key navigation;
- recent actions;
- shortcut hints;
- clear selected-row state;
- Escape-to-close behavior.

## 5.3 Apple Human Interface Guidelines — Restraint and Polish

Borrow:

- clear hierarchy;
- quiet interface chrome;
- content-first layout;
- carefully grouped toolbars;
- consistent alignment;
- moderate rounding;
- smooth, brief transitions;
- strong typography.

Do not use excessive:

- translucency;
- blur;
- glass panels;
- oversized headlines;
- gradient backgrounds;
- floating decorative surfaces.

## 5.4 Microsoft Fluent — Practical Desktop Behavior

Borrow:

- accessible focus management;
- command bars;
- navigation behavior;
- dialogs;
- menus;
- toasts;
- status communication;
- keyboard interaction;
- comfortable and compact density.

## 5.5 Vercel Geist — Technical Clarity

Borrow:

- semantic design tokens;
- clean tables;
- thin dividers;
- compact status indicators;
- restrained technical surfaces;
- clear empty, loading, and failure states.

---

# 6. Application Modes

The system must provide distinct experiences for different operational contexts.

## 6.1 Administration Mode

Used for:

- ceremonies;
- students;
- CSV import;
- QR generation;
- users;
- logs;
- reports;
- backups;
- configuration;
- readiness preparation.

Characteristics:

- persistent navigation;
- compact tables;
- search and filters;
- forms;
- contextual inspector or detail panels;
- safe batch actions;
- moderate information density.

## 6.2 Scanner Mode

Used by the QR scanner operator.

Characteristics:

- scanning is the dominant task;
- scanner input remains focused;
- scan result is large and unmistakable;
- recent scans remain visible;
- technical clutter is minimized;
- errors provide direct recovery;
- sidebar may collapse automatically;
- no unrelated administrative controls.

## 6.3 Stage Control Mode

Used by the live ceremony control operator.

Characteristics:

- current graduate, next graduate, and queue are always visible;
- core actions are reachable without scrolling;
- ceremony state is prominent;
- connection and audio state are persistent;
- queue changes are reflected immediately;
- mistakes are reversible when safe;
- emergency pause is visible but visually separated.

## 6.4 Stage Display Mode

Audience-facing output.

Characteristics:

- no application navigation;
- no operator controls;
- no technical errors;
- no QR codes or student numbers;
- no debug data;
- no browser-like visual clutter;
- maximum readability at distance;
- graceful holding, paused, offline, and empty states;
- restrained ceremony-appropriate animation.

---

# 7. Information Architecture

## 7.1 Primary Navigation

Organize navigation by operator intent.

```text
CEREMONY
- Overview
- Scanner
- Stage Control
- Queue
- Stage Display

DATA
- Students
- Programs
- Ceremonies
- CSV Import
- QR Codes

OPERATIONS
- Audio Readiness
- Scan Logs
- Stage Events
- Ceremony Summary

SYSTEM
- Device Status
- Backups
- Users
- Settings
```

The actual visible items must follow authorization and existing routes.

## 7.2 Navigation Rules

- The active destination must be visually obvious.
- The sidebar may collapse to icons on smaller desktop widths.
- Collapsed icons must have tooltips.
- Critical destinations must not be hidden inside a generic “More” menu.
- Navigation order must remain stable between sessions.
- Operators must be able to return to Scanner and Stage Control in one action.
- Do not use large dashboard cards as the primary navigation method.
- Breadcrumbs are optional for nested administration pages, but not required for primary operational screens.
- Preserve back and forward browser navigation where compatible with Inertia.
- Remember the last appropriate view, filter, and density preference when safe.

---

# 8. Desktop Application Shell

## 8.1 Standard Shell

```text
┌──────────────────────────────────────────────────────────────────────────────┐
│ GSS / Ceremony name            Ceremony: LIVE          System status   User  │
├────────────────┬─────────────────────────────────────────────┬───────────────┤
│                │ Page title                        Actions    │               │
│ Primary        ├─────────────────────────────────────────────┤ Contextual    │
│ navigation     │                                             │ inspector     │
│                │ Main operational workspace                  │ or details    │
│                │                                             │               │
├────────────────┴─────────────────────────────────────────────┴───────────────┤
│ Server · Database · Reverb · Stage · Audio · Scanner           Local time    │
└──────────────────────────────────────────────────────────────────────────────┘
```

## 8.2 Shell Measurements

Use these as targets, not rigid values:

```text
Top application bar:       48–52px
Expanded sidebar:          224–240px
Collapsed sidebar:         56–64px
Context inspector:         300–360px
Bottom status bar:         28–32px
Standard page padding:     20–24px
Compact page padding:      16–20px
Standard control height:   36–40px
Critical control height:   44–52px
```

## 8.3 Top Application Bar

The top bar should show only globally important information:

- application identity;
- active ceremony;
- ceremony state;
- command center trigger;
- notifications or critical warnings;
- user menu.

Do not place page-specific actions in the global top bar when they belong in the page toolbar.

## 8.4 Bottom Status Bar

The bottom status bar is encouraged for operational screens.

Possible indicators:

- Server
- Database
- Reverb
- Stage display
- Audio
- Scanner
- Last synchronized time
- Local clock

Do not show all indicators when they have no value on the current screen. Scanner and Stage Control should show the most complete operational status.

---

# 9. Command Center and Keyboard Interaction

## 9.1 Command Center

Provide a universal command center opened with:

```text
Ctrl + K
```

It should support:

- navigation;
- student search;
- ceremony search;
- recent destinations;
- authorized actions;
- system commands;
- keyboard shortcut discovery.

Example commands:

```text
Open Scanner
Open Stage Control
Open Queue
Search students
Present next graduate
Replay announcement
Open Audio Readiness
View connection status
Create backup
Show keyboard shortcuts
```

Dangerous commands must still require confirmation.

## 9.2 Recommended Shortcuts

Shortcuts must not interfere with scanner input or form fields.

```text
Ctrl + K      Open command center
Ctrl + /      Show keyboard shortcuts
Alt + 1       Ceremony Overview
Alt + 2       Scanner
Alt + 3       Stage Control
Alt + 4       Queue
Alt + 5       Students

Stage Control only:
Space         Present selected or next graduate
R             Replay announcement
S             Skip selected graduate
U             Return or undo last reversible action
H             Show holding screen
P             Open pause confirmation
Arrow Up/Down Move queue selection
Enter         Open selected item or confirm safe action
Escape        Close menu, drawer, or dialog
```

Rules:

- Single-letter shortcuts only work when focus is not inside an input, textarea, select, or editable area.
- Shortcut hints must appear in menus and tooltips.
- A shortcut reference must be searchable.
- Do not create shortcuts for destructive actions without confirmation.
- Scanner input behavior takes priority on the Scanner screen.

---

# 10. Visual Design System

## 10.1 Color Strategy

Use a neutral application palette with one restrained institutional accent.

The interface must work without gradients.

### Light Theme Tokens

```css
:root {
  --color-bg-app: #F4F5F7;
  --color-bg-sidebar: #ECEEF1;
  --color-surface: #FFFFFF;
  --color-surface-subtle: #F8F9FA;
  --color-surface-selected: #EAF2FF;
  --color-surface-hover: #F0F2F5;

  --color-text-primary: #17191C;
  --color-text-secondary: #5E6670;
  --color-text-muted: #7B8490;
  --color-text-inverse: #FFFFFF;

  --color-border: #D9DDE3;
  --color-border-strong: #BBC2CC;

  --color-accent: #1769E0;
  --color-accent-hover: #1259C5;
  --color-accent-subtle: #E8F1FF;

  --color-success: #16794A;
  --color-success-bg: #E8F6EF;
  --color-warning: #9A5B00;
  --color-warning-bg: #FFF3D6;
  --color-danger: #C73737;
  --color-danger-bg: #FDECEC;
  --color-info: #1769E0;
  --color-info-bg: #E8F1FF;

  --color-focus: #1769E0;
  --color-overlay: rgba(18, 22, 28, 0.42);
}
```

Colors may be adjusted to match approved university branding, but semantic roles and contrast must be preserved.

## 10.2 Dark Theme

Dark mode is optional and must not be implemented before operational screens are fully correct in the default light theme.

If implemented:

- use near-black neutral surfaces rather than pure black;
- preserve contrast;
- keep status colors distinguishable;
- avoid neon accents;
- verify the stage display separately;
- do not automatically switch during a live ceremony unless the operator explicitly chooses it.

## 10.3 Color Usage Rules

- Accent color is for active navigation, focus, links, and primary actions.
- Green means confirmed healthy or successful.
- Amber means degraded, needs attention, or awaiting action.
- Red means failure, destructive action, or ceremony risk.
- Blue means informational or selected.
- Neutral gray means secondary or inactive.
- Do not use status colors decoratively.
- Do not show more than one dominant accent color on a screen.
- Do not make entire large panels strongly red or green unless the situation is critical.

---

# 11. Typography

## 11.1 Font Stack

All fonts must be available locally at runtime.

```css
font-family:
  system-ui,
  -apple-system,
  BlinkMacSystemFont,
  "Segoe UI",
  "Noto Sans",
  Ubuntu,
  Cantarell,
  sans-serif;
```

Do not depend on Google Fonts or any external font CDN.

A bundled local font may be approved later, but it must not create an internet dependency.

## 11.2 Type Scale

```text
Application title:        18–20px / 600
Page title:               22–26px / 650
Section title:            16–18px / 600
Body:                     14–16px / 400
UI label:                 13–14px / 500
Caption/status:           12–13px / 450
Table text:               13–14px / 400–500
Critical numeric value:   24–36px / 650
```

## 11.3 Stage Display Typography

The audience-facing graduate name must scale based on viewport and content length.

Recommended target:

```css
font-size: clamp(3rem, 6vw, 7.5rem);
line-height: 0.98–1.08;
font-weight: 650–750;
```

Program and honors should remain visually secondary.

Rules:

- Never truncate a graduate name on the stage display.
- Allow controlled wrapping to a maximum of two or three lines depending on layout.
- Test long names, suffixes, honors, and multi-word programs.
- Avoid all caps for long names.
- Use tabular numbers for queue position, timestamps, and counts.

---

# 12. Spacing, Borders, Radius, and Elevation

## 12.1 Spacing Scale

Use a consistent scale:

```text
2px   micro alignment only
4px   icon/text gap or tight control spacing
8px   compact internal spacing
12px  standard compact spacing
16px  standard component padding
20px  page toolbar spacing
24px  section spacing
32px  major section separation
48px  large structural separation
64px  stage-display spacing
```

Avoid arbitrary one-off values unless required for alignment.

## 12.2 Borders

Use borders and spacing before shadows.

```text
Default border:        1px solid semantic border color
Strong divider:        1px solid strong border color
Focus ring:            2px visible ring with offset
Table row divider:     subtle 1px divider
```

## 12.3 Corner Radius

```text
Small controls:        6px
Inputs and buttons:    7–8px
Panels:                8–10px
Dialogs:               10–12px
Pills:                 full radius only for true tags/statuses
```

Do not make every container a large rounded card.

## 12.4 Shadows

Allowed for:

- dialogs;
- menus;
- command center;
- tooltips;
- temporary overlays;
- a floating emergency action when functionally justified.

Normal sections and tables should use borders, background contrast, and spacing instead.

---

# 13. Motion

## 13.1 Duration

```text
Hover/focus:             80–120ms
Menu and popover:        100–140ms
Panel/drawer:            140–180ms
Dialog:                  120–160ms
Stage content transition:180–300ms
Toast entry/exit:        140–200ms
```

## 13.2 Motion Rules

- Use opacity and small positional changes.
- Avoid dramatic scale, bounce, spring, and elastic effects.
- Do not animate table layout changes excessively.
- Do not delay button feedback.
- Scan feedback must appear immediately.
- Stage-display animation must remain dignified and readable.
- Queue reordering should preserve spatial understanding.
- Honor `prefers-reduced-motion`.
- Reduced-motion mode must remove nonessential movement while preserving state changes.

---

# 14. Density

The application should support two desktop density levels where practical:

## Comfortable

Used for:

- general administration;
- unfamiliar operators;
- touchpad-heavy workflows;
- smaller datasets.

## Compact

Used for:

- logs;
- large student lists;
- stage queue;
- experienced operators;
- 1366×768 laptops.

Density affects row height and padding, not font legibility or target accessibility.

Suggested row heights:

```text
Comfortable: 44–48px
Compact:     36–40px
```

Critical action controls must not become too small in compact mode.

---

# 15. Reusable Component Rules

## 15.1 Buttons

Variants:

- Primary
- Secondary
- Quiet
- Danger
- Icon
- Split action only when clearly justified

Rules:

- Use action verbs.
- One dominant primary action per local context.
- Do not use primary styling for several competing actions.
- Disable while submitting and prevent duplicate submission.
- Keep label width stable during loading when possible.
- Show a spinner without hiding the action meaning.
- Destructive buttons must use danger styling.
- Icon-only buttons require tooltips and accessible names.

## 15.2 Status Indicators

A status indicator must include:

- semantic label;
- color;
- icon or shape when helpful;
- accessible text;
- optional last-updated time.

Examples:

```text
● Connected
▲ Degraded
■ Paused
× Disconnected
```

Avoid showing every normal state as a large badge.

## 15.3 Alerts and Banners

Use:

- inline alert for local form or component problems;
- top banner for system-wide or ceremony-wide issues;
- toast for temporary confirmation;
- dialog for decisions requiring user confirmation.

Persistent connection failure must not be shown only as a disappearing toast.

## 15.4 Toasts

Toasts are appropriate for:

- saved changes;
- reversible queue action;
- copied value;
- backup created;
- noncritical confirmation.

Example:

> Graduate skipped. **Undo**

Do not use toasts for:

- system disconnected;
- ceremony paused;
- stage display offline;
- audio failure requiring action;
- data-loss risk.

## 15.5 Tables

Tables must support:

- sticky header when long;
- keyboard-accessible rows and actions;
- sorting indicators;
- clear empty state;
- loading state;
- error state;
- sensible column priority;
- horizontal overflow only when unavoidable;
- row selection independent from row action;
- bulk actions only after selection.

Rules:

- Do not place every row field inside separate pills.
- Do not hide critical row actions only on hover.
- Do not use zebra striping when dividers and hover states are sufficient.
- Preserve column alignment.
- Use tabular numbers for sequence, counts, and times.

## 15.6 Forms

Forms must:

- group related fields;
- use persistent labels;
- place validation near the field;
- preserve entered data after recoverable errors;
- show required fields clearly;
- use correct control types;
- avoid placeholder-only labels;
- use plain-language help only where necessary;
- warn before navigating away from unsaved changes.

Long forms should use logical sections, not a separate card for every few inputs.

## 15.7 Dialogs

Dialog behavior:

- focus moves into the dialog;
- initial focus is deliberate;
- Escape closes safe dialogs;
- focus returns to the trigger;
- title states the decision;
- body explains impact;
- actions use specific verbs;
- destructive action is visually separated.

Avoid stacking dialogs.

## 15.8 Drawers and Inspectors

Use a right-side inspector for contextual details that should not interrupt workflow.

Appropriate for:

- graduate details;
- queue item history;
- scan details;
- audio status;
- device details;
- quick edits.

Do not use an inspector for complex multi-step forms that require full-page attention.

---

# 16. Global Feedback and State Model

Every data-driven screen must define:

- initial loading;
- background refreshing;
- empty;
- success;
- validation error;
- permission error;
- server error;
- disconnected;
- reconnecting;
- stale data;
- read-only;
- disabled;
- destructive confirmation;
- success with undo, where applicable.

## 16.1 Connection State

Use a consistent connection vocabulary:

```text
Connected
Reconnecting
Disconnected
Degraded
Unknown
```

When disconnected:

- state what is unavailable;
- state what remains safe;
- do not imply an action succeeded without confirmation;
- preserve queued user intent only when the engineering flow safely supports it;
- provide Retry or Refresh State where appropriate.

## 16.2 Background Refresh

When live data refreshes:

- do not reset scroll unexpectedly;
- do not clear selection without reason;
- do not steal keyboard focus;
- visually mark new or changed rows briefly;
- preserve the current operator context.

---

# 17. Screen Specifications

# 17.1 Ceremony Overview

## Primary Goal

Give the operator an immediate operational summary and direct access to the next necessary action.

## Must Show

- active ceremony;
- ceremony status;
- current graduate;
- next graduate;
- queue count;
- scanner readiness;
- stage-display connection;
- audio readiness;
- unresolved critical warnings;
- last meaningful event;
- primary action based on ceremony state.

## Must Not Show

- oversized welcome message;
- promotional copy;
- decorative charts;
- fake analytics;
- equal-size metric card grid;
- long product description.

## Layout Direction

Use one dominant operational summary and one secondary readiness region.

```text
┌──────────────────────────────────────────────────────────────────────┐
│ Ceremony Overview                           [Open Stage Control]     │
├──────────────────────────────────────────────┬───────────────────────┤
│ LIVE STAGE                                   │ SYSTEM READINESS      │
│ Current graduate                            │ Scanner      Ready    │
│ Next graduate                               │ Stage        Connected│
│ Queue: 18 waiting                           │ Audio        Ready    │
│                                              │ Reverb       Connected│
├──────────────────────────────────────────────┴───────────────────────┤
│ Attention needed / recent ceremony activity                         │
└──────────────────────────────────────────────────────────────────────┘
```

---

# 17.2 Scanner

## Primary Goal

Accept QR input continuously and communicate the result immediately.

## Required Layout

```text
┌──────────────────────────────────────────────────────────────────────┐
│ Scanner                         Ready for scans      ● Connected     │
├─────────────────────────────────────────────┬────────────────────────┤
│                                             │ Recent scans           │
│ Large current scan state                    │ 14:40  Accepted         │
│                                             │ 14:39  Duplicate        │
│ [Ready / Accepted / Rejected / Duplicate]   │ 14:38  Accepted         │
│                                             │                        │
│ Graduate identity when accepted             │                        │
│ Clear recovery when rejected                │                        │
├─────────────────────────────────────────────┴────────────────────────┤
│ Hidden or visually subtle scanner input · Last sync · Local time    │
└──────────────────────────────────────────────────────────────────────┘
```

## Required Behavior

- Provide an explicit Camera / USB and keyboard capture-method switcher.
- Preserve the selected capture method on the current device without storing QR values.
- Camera mode requires an explicit operator action before requesting permission.
- Camera mode shows a live preview, a centered target, camera state, and the next safe action.
- Prefer the rear camera and expose camera or flashlight controls only when supported.
- Switching away from Camera mode immediately releases the camera stream.
- Camera frames remain on the device; only the decoded QR value enters the authenticated scan workflow.
- Scanner input receives focus on entry.
- Focus is restored after safe dialogs, navigation cancellation, and scan completion.
- Enter submits the complete token.
- Rapid duplicate submissions are prevented.
- A successful scan produces clear visual confirmation.
- A rejected scan produces a different visual and optional audio cue.
- Recent scans show time, result, and graduate identity when appropriate.
- The screen remains usable with manual keyboard input for testing.
- Invalid tokens never change the stage.
- Duplicate result explains the prior state and available recovery.
- Disconnection must be persistent and obvious.

## Scan Result Language

Use consistent result names:

```text
Accepted
Duplicate scan
QR code not recognized
Wrong ceremony
Graduate not eligible
Already completed
Ceremony paused
Scanner offline
Server unavailable
```

## Prohibited

- charts;
- administrative tables;
- large navigation;
- unrelated settings;
- decorative statistics;
- repeated instructional paragraphs.

---

# 17.3 Stage Control

## Primary Goal

Control the live ceremony sequence safely and quickly.

## Required Layout

```text
┌──────────────────────────────────────────────────────────────────────────────┐
│ Stage Control      LIVE      Stage connected      Audio ready     [Pause]   │
├────────────────────────────────────────────┬─────────────────────────────────┤
│ CURRENT                                    │ QUEUE                           │
│ Graduate name                              │ 1  Next graduate                │
│ Program / honors                           │ 2  Waiting graduate             │
│ Announcement state                         │ 3  Waiting graduate             │
│                                            │                                 │
│ [Replay] [Return] [Complete]               │ Selection and quick actions     │
├────────────────────────────────────────────┴─────────────────────────────────┤
│ NEXT: Graduate name                [Skip]               [Present next]       │
└──────────────────────────────────────────────────────────────────────────────┘
```

## Primary Hierarchy

1. Current graduate
2. Present next
3. Next graduate
4. Queue
5. Audio and stage status
6. Recovery actions
7. Secondary history

## Required Actions

- Present
- Present next
- Skip
- Return
- Replay announcement
- Complete
- Holding screen
- Pause
- Resume

Actions must reflect existing authorization and ceremony state.

## Interaction Rules

- The next safe primary action is visually dominant.
- Disabled actions explain why through tooltip or nearby text.
- Queue selection must be visible.
- Action feedback must not rely only on toast.
- Pause must require confirmation.
- Replay must prevent overlapping audio.
- Return and skip should provide Undo when safe.
- Real-time updates must not steal focus.
- Selected queue row remains stable when new scans arrive unless the selected item is removed.
- Holding mode must be clearly visible to the operator.

## Prohibited

- hiding core actions inside kebab menus;
- placing the queue below the fold on standard laptops;
- equal visual weight for all buttons;
- unnecessary charts;
- large welcome banner;
- decorative animation.

---

# 17.4 Queue

## Primary Goal

Inspect and manage ceremony progression.

## Must Show

- sequence position;
- graduate identity;
- program;
- queue status;
- scanned time;
- audio readiness where useful;
- current selection;
- authorized row actions.

## Rules

- Current and next graduates remain pinned or visually distinct.
- Queue statuses use text plus color.
- Reordering, when supported, must show clear drop position and persist safely.
- Manual movement requires audit logging.
- Filtering must not hide the currently presented graduate without warning.
- Large queues use virtualization or pagination only when necessary and compatible with real-time updates.

---

# 17.5 Stage Display

## Primary Goal

Present the graduate clearly and ceremonially to the audience.

## Required States

- Holding
- Ready
- Presenting graduate
- Paused
- Empty
- Recovering connection
- Safe offline fallback
- Ceremony completed

## Presenting Layout

```text
┌──────────────────────────────────────────────────────────────────────┐
│ University / Ceremony identity                                      │
│                                                                      │
│                         GRADUATE NAME                                │
│                         Program / Major                              │
│                         Honors, if applicable                        │
│                                                                      │
│ Optional approved photo or institutional visual                     │
└──────────────────────────────────────────────────────────────────────┘
```

## Rules

- Graduate name is dominant.
- Program and honors are secondary.
- Do not show operator status labels during normal presentation.
- Do not reveal student number, QR token, internal ID, logs, or technical details.
- Images must use controlled crops and fallback behavior.
- Background must preserve contrast in actual projector conditions.
- Do not use busy video backgrounds unless separately tested and explicitly approved.
- Stage transitions must be brief and dignified.
- If disconnected, preserve the last known safe visual or approved holding screen.
- Never display raw Laravel, React, Reverb, or browser errors.
- Test at 1920×1080 and the actual output resolution.

---

# 17.6 Student Administration

## Primary Goal

Find, validate, edit, and prepare graduate records efficiently.

## List Screen

Must include:

- search;
- filters;
- sequence;
- display name;
- program;
- eligibility;
- QR status;
- photo status;
- audio status;
- row actions;
- bulk selection where justified.

Preferred layout: dense table with a contextual inspector.

Avoid a grid of student cards.

## Create/Edit Screen

Group fields into:

1. Identity
2. Ceremony and sequence
3. Display information
4. Announcement information
5. Photo
6. Audio
7. Eligibility and status

Show a stage-display preview when useful, but do not make the preview dominate data entry.

---

# 17.7 Ceremony Administration

## Must Support

- ceremony list;
- preparation state;
- active ceremony identification;
- date and venue;
- presentation mode;
- readiness summary;
- activation and completion safeguards.

The active ceremony must be unmistakable.

Activation must explain unresolved readiness warnings.

---

# 17.8 CSV Import

## Primary Goal

Import student data safely with a clear inspect-then-confirm workflow.

## Required Steps

```text
Select file
→ Parse
→ Show field mapping and validation
→ Inspect valid and invalid rows
→ Confirm import
→ Show result summary
```

## Rules

- Never import immediately after file selection.
- Show exact row-level problems.
- Allow correction through source file guidance or approved inline correction.
- Separate warnings from blocking errors.
- Show counts for valid, invalid, duplicate, skipped, and imported rows.
- Keep the final confirmation explicit.
- Do not present import as a decorative wizard with unnecessary animation.

---

# 17.9 QR Codes

## Primary Goal

Generate, inspect, and print usable QR codes.

## Rules

- Show graduate identity beside each QR for operator verification.
- Do not expose personal information inside the QR token.
- Provide print preview.
- Warn about missing or duplicate tokens.
- Use predictable page breaks.
- Preserve adequate quiet zone and print contrast.
- Provide a clear regenerate action only when business rules permit it.

---

# 17.10 Audio Readiness

## Primary Goal

Ensure every required graduate announcement is ready before the ceremony.

## Must Show

- graduate;
- audio status;
- file presence;
- verification status;
- duration where available;
- preview;
- missing or invalid reason;
- filter by readiness;
- bulk readiness summary.

Missing audio is operationally important and must not be hidden inside a generic badge.

---

# 17.11 Logs and Reports

## Scan Logs

Prioritize:

- time;
- graduate;
- result;
- operator;
- device;
- actionable failure reason.

## Stage Events

Prioritize:

- event type;
- affected graduate;
- operator;
- time;
- before/after state where relevant.

## Ceremony Summary

Show only metrics that support post-event review, accountability, or readiness.

Avoid decorative charts when a clear table or summary is better.

---

# 17.12 Backups and Restore

## Primary Goal

Protect ceremony data and support safe recovery.

## Rules

- Clearly distinguish Create Backup from Restore Backup.
- Show creation time, size, verification status, and source.
- Restore requires strong confirmation.
- State that current data may be replaced.
- Show progress and completion.
- Never dismiss restore failures through a temporary toast only.
- Provide recovery guidance.

---

# 17.13 Users and Roles

## Rules

- Role and active state must be visible.
- Permission-related descriptions must use plain language.
- Deactivation must explain session impact.
- Do not expose password data.
- Critical role changes require confirmation.
- Audit user-management actions.

---

# 18. Content and Interface Writing

## 18.1 Style

Use:

- concise labels;
- plain language;
- sentence case;
- action verbs;
- specific statuses;
- consistent terminology;
- calm tone.

Avoid:

- marketing slogans;
- unnecessary friendliness during critical errors;
- robotic technical language;
- vague messages;
- long explanatory paragraphs;
- repeated page descriptions;
- exclamation marks for routine success.

## 18.2 Terminology

Use one term consistently:

```text
Graduate          not Student/Attendee interchangeably in live operations
Ceremony          not Event/Session interchangeably
Stage Control     not Control Center/Operator Dashboard interchangeably
Stage Display     not Audience Screen/Presentation Screen interchangeably
Queue             not Line/List interchangeably
Announcement      not Voice/Audio interchangeably when referring to the action
Replay announcement
Present graduate
Return to queue
Skip graduate
Pause ceremony
Resume ceremony
```

Database or legacy names may remain internally, but visible interface terms must be consistent.

## 18.3 Error Formula

Use:

```text
[What happened]. [What was affected]. [What the operator can do].
```

Example:

> The QR code is not assigned to this ceremony. The stage was not changed. Verify the graduate’s QR or search the student record.

---

# 19. Accessibility

Meet WCAG 2.2 AA where practical.

## Required

- full keyboard navigation;
- visible focus indicators;
- logical focus order;
- persistent labels;
- accessible names for icon buttons;
- semantic headings;
- proper table headers;
- dialog focus trapping and restoration;
- validation linked to fields;
- screen-reader status announcements;
- reduced-motion support;
- sufficient contrast;
- status independent of color;
- meaningful page titles;
- target sizes appropriate for fast operation.

## Target Size

- Absolute minimum interactive target: 24×24 CSS pixels.
- Normal application controls: at least 36–40px high.
- Ceremony-critical controls: 44–52px high where layout allows.

## Live Regions

Use polite announcements for:

- scan accepted;
- scan rejected;
- connection restored;
- queue updated when relevant.

Use assertive announcements only for:

- ceremony paused;
- stage disconnected;
- severe audio failure;
- critical action failure.

Avoid flooding assistive technology with every Reverb event.

---

# 20. Responsive and PWA Rules

## 20.1 Target Viewports

Required verification:

```text
1920×1080   Stage display and large operator monitor
1440×900    Primary desktop administration
1366×768    Common operator laptop
1024×768    Small laptop or tablet-like device
```

Mobile administration is secondary and may be limited.

Stage Control and Stage Display are desktop-first. Administration remains
desktop-first. Scanner is desktop-first for USB operation and has one approved
mobile operational mode for an authenticated phone camera scanner.

Phone-camera Scanner verification:

```text
390×844     Compact phone portrait
430×932     Large phone portrait
844×390     Compact phone landscape
932×430     Large phone landscape
```

The phone layout must keep capture mode, camera preview, current result, and the
next safe action ahead of recent history. It must not turn administration pages
into mobile-first card layouts.

The desktop **Connect devices** workspace is the approved entry point for phone
enrollment. It must show one scannable local setup address, certificate
readiness, a human-verifiable certificate check code, and recovery guidance.
The setup QR must never contain credentials, an authenticated session, graduate
tokens, or ceremony commands.

The phone onboarding page is a narrow operational exception served on the local
HTTP port so a new device can obtain the public local CA certificate. It must be
self-contained, must not load remote assets, must not accept credentials, and
must send the operator to the HTTPS Scanner before authentication or camera use.
Operating-system certificate trust and camera permission always require an
explicit operator action.

## 20.2 Responsive Behavior

At narrower desktop widths:

- collapse sidebar;
- reduce secondary details;
- move inspector to an overlay drawer;
- preserve primary actions;
- keep system status visible;
- allow table overflow only when necessary;
- do not convert every table row into a large card;
- do not hide current, next, or pause controls.

## 20.3 PWA Behavior

The PWA should feel installed:

- use standalone display mode;
- provide an application icon and appropriate name;
- avoid browser-dependent navigation patterns;
- preserve safe last route where appropriate;
- provide clear offline or disconnected state;
- never imply full offline capability when the server is unreachable;
- keep runtime assets local;
- avoid external font, icon, image, and script dependencies;
- use install prompts sparingly;
- do not interrupt ceremony operations with update prompts.

After one-time technical installation, normal operation must not require a
terminal. The server starts through the approved operating-system service, and
the desktop launcher may start that service and open the application. Startup
must never run unreviewed migrations or hide a failed health check.

Application updates must not unexpectedly reload an active ceremony screen.

---

# 21. Icons

Use one approved local icon library.

Recommended style:

- outline icons;
- consistent stroke;
- 16–20px in ordinary controls;
- 20–24px in prominent actions.

Rules:

- Icons support text; they do not replace unclear labels.
- Do not place an icon beside every heading.
- Do not mix icon families.
- Icon-only buttons require accessible labels and tooltips.
- Status icons must remain understandable in grayscale.

---

# 22. Anti-Patterns

The following are prohibited unless explicitly approved for a specific functional reason:

- generic SaaS metric-card dashboard;
- hero section inside the authenticated app;
- product marketing copy;
- purple-to-blue gradients;
- glassmorphism;
- excessive backdrop blur;
- cards inside cards;
- equal-sized card grids;
- oversized corner radii;
- random shadows;
- glowing borders;
- icons beside every title;
- pills for ordinary text;
- decorative charts;
- fake analytics;
- placeholder metrics;
- decorative illustrations on operational screens;
- huge empty areas;
- large headings that reduce working space;
- low-contrast gray text;
- hidden critical actions;
- hover-only essential controls;
- tiny click targets;
- modal dialogs for routine reversible actions;
- animation that delays the operator;
- inconsistent terminology;
- inconsistent spacing;
- different visual patterns for the same action;
- mobile-card stacking applied blindly to desktop tables.

---

# 23. Frontend Architecture Guidance

## 23.1 Component Layers

Organize components into two conceptual layers.

```text
UI primitives
- Button
- IconButton
- Input
- Select
- Checkbox
- Dialog
- Drawer
- Menu
- Tooltip
- Toast
- StatusIndicator
- Table
- EmptyState
- Skeleton

Graduation-domain components
- CeremonyStatus
- GraduateIdentity
- CurrentGraduatePanel
- NextGraduatePanel
- ScannerFeedback
- RecentScanList
- StageQueue
- QueueItem
- AudioReadiness
- DeviceStatus
- ConnectionBanner
- StageDisplayCanvas
```

Do not create a component merely because a JSX block exists once.

Create a reusable component when it has:

- repeated use;
- meaningful states;
- shared behavior;
- accessibility logic;
- a stable domain concept.

## 23.2 Design Tokens

Use semantic Tailwind configuration or CSS custom properties.

Do not scatter arbitrary hex colors, spacing values, and radii across components.

Preferred token categories:

```text
color
surface
text
border
status
spacing
radius
shadow
motion
z-index
layout
typography
```

## 23.3 State Ownership

- PostgreSQL remains the source of truth for important ceremony state.
- Inertia page props provide initial page state.
- Reverb/Echo updates live state.
- HTTP recovery restores authoritative state.
- Local React state controls temporary interface state only.
- Do not use optimistic UI for high-risk stage transitions unless the server result remains clearly authoritative.

---

# 24. Performance Expectations

The interface should feel immediate on the local network.

Targets:

- button feedback begins within 100ms;
- local navigation feels immediate;
- command palette opens without visible delay;
- typing and scanner input never stutter;
- large tables avoid unnecessary full-page rerenders;
- stage updates avoid layout shifts;
- only required media is preloaded;
- stage image and audio failures do not freeze the interface;
- loading indicators appear only when there is a perceptible wait.

Do not add heavy visual libraries for effects that can be achieved with CSS.

---

# 25. Visual QA Requirements

A UI change is incomplete until it is inspected in a browser.

For every affected screen, verify:

- default state;
- loading;
- empty;
- success;
- validation error;
- server error;
- disconnected;
- reconnecting;
- long names;
- missing photo;
- missing audio;
- large data set;
- keyboard-only use;
- compact viewport;
- large viewport;
- focus visibility;
- text wrapping;
- overflow;
- alignment;
- animation;
- reduced motion;
- authorization restrictions.

Required viewport checks:

```text
1440×900
1366×768
1024×768
1920×1080 for stage display
```

For stage-related changes, also verify:

- actual projector or representative display;
- distance readability;
- duplicate scan;
- rapid scans;
- Reverb disconnect/reconnect;
- page refresh;
- audio replay;
- missing audio;
- pause/resume;
- holding screen;
- queue changes while an item is selected.

Never claim visual verification if the route was not opened and inspected.

---

# 26. Definition of Done for UI Work

A UI task is complete only when:

```text
[ ] The screen has one clear primary purpose.
[ ] The most important information is visually dominant.
[ ] The primary action is obvious.
[ ] Critical system state is visible.
[ ] Keyboard use is supported.
[ ] Loading, empty, success, error, and disconnected states exist.
[ ] Error messages contain recovery actions.
[ ] Long names and long content do not break the layout.
[ ] Authorization restrictions are represented correctly.
[ ] Target sizes and focus states are accessible.
[ ] The screen follows semantic design tokens.
[ ] The screen works at required viewports.
[ ] The screen avoids prohibited AI-generated patterns.
[ ] Motion does not delay work.
[ ] Browser-based visual verification was completed.
[ ] Relevant automated tests pass.
[ ] Documentation and UI changelog are updated.
```

---

# 27. Redesign Sequence for the Existing GSS

Do not redesign all screens at once.

Use this order:

## Step 1 — Audit and Tokens

- inventory current layouts and components;
- identify repeated styles;
- create semantic tokens;
- identify high-risk operational inconsistencies;
- document current screenshots.

## Step 2 — Application Shell

- top bar;
- sidebar;
- page toolbar;
- bottom status bar;
- navigation behavior;
- command center;
- focus and keyboard foundation.

## Step 3 — Live Operational Screens

1. Scanner
2. Stage Control
3. Queue
4. Stage Display
5. Ceremony Overview

These screens have the highest real-world risk and should be refined before administrative polish.

## Step 4 — Preparation Screens

1. Students
2. Ceremonies
3. CSV Import
4. QR Codes
5. Audio Readiness

## Step 5 — Accountability and System Screens

1. Scan Logs
2. Stage Events
3. Ceremony Summary
4. Backups
5. Users
6. Settings
7. Device status

## Step 6 — PWA and Final Visual QA

- standalone PWA behavior;
- viewport verification;
- reduced motion;
- offline/disconnected messaging;
- performance;
- full ceremony rehearsal.

After each screen:

- run tests;
- inspect in browser;
- capture comparison screenshots when available;
- update `CHANGELOG_UI.md`;
- stop before moving to the next screen unless the assigned task explicitly includes more.

---

# 28. Instructions for Codex or Another Coding Agent

Use the following rules whenever this file is supplied to a coding agent.

```text
Read design.md completely before changing frontend code.

The Graduation Stage System is a desktop-class operational PWA, not a marketing
website or generic SaaS dashboard.

Before editing:

1. Inspect the actual repository, routes, layouts, components, Tailwind
   configuration, design tokens, and tests.
2. Open the affected screen in a browser.
3. Identify the primary user, workflow, and operational risk.
4. Compare the current screen against design.md.
5. Report the smallest defensible improvement plan.
6. State the files likely to change.
7. State the browser states and viewports that will be verified.

During implementation:

1. Preserve business rules, permissions, HTTP workflows, PostgreSQL state,
   Reverb/Echo behavior, and recovery mechanisms.
2. Reuse existing conventions.
3. Use semantic design tokens.
4. Do not introduce external runtime dependencies.
5. Do not redesign unrelated screens.
6. Do not add decorative cards, gradients, charts, or animations.
7. Keep critical actions visible and keyboard accessible.
8. Implement loading, error, empty, disconnected, and recovery states.
9. Avoid breaking scanner focus or live queue selection.
10. Keep audience-facing technical errors hidden.

After implementation:

1. Run relevant automated tests, linting, and production build.
2. Open and inspect all changed routes.
3. Test required states and viewports.
4. Verify keyboard focus and shortcuts.
5. Verify long names and failure cases.
6. Report exactly what was tested.
7. List known limitations honestly.
8. Update CHANGELOG_UI.md.
9. Stop after the assigned screen or phase.
```

---

# 29. Initial Redesign Brief

The first visual-refinement task should be:

```text
Audit and redesign the shared application shell only.

Scope:
- authenticated desktop shell
- top application bar
- primary sidebar
- page toolbar pattern
- bottom operational status bar
- command center foundation
- semantic design tokens
- focus indicators
- compact and comfortable density foundation

Do not redesign Scanner, Stage Control, Stage Display, tables, forms, or reports
beyond the minimum changes needed to place them safely inside the new shell.

Preserve all existing routes, permissions, data, and real-time behavior.

Verify:
- 1440×900
- 1366×768
- 1024×768
- keyboard navigation
- sidebar expanded and collapsed
- command center open and closed
- connection status variants
- long ceremony name
- long user name
```

---

# 30. Final Design Test

The design is successful when a first-time observer can look at the system and correctly describe it as:

> A fast, reliable graduation ceremony operations application.

They should not describe it as:

> A product website, marketing dashboard, template-based SaaS interface, or decorative school portal.
