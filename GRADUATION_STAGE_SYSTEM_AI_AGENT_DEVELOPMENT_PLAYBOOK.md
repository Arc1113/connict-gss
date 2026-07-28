# Graduation Stage System
## ChatGPT Planning and AI-Agent Development Playbook

> **Current project status:** Pre-development planning. The application repository and production UI have not yet been created.
>
> **Purpose:** This file is a phase-by-phase plan for:
>
> 1. Planning the product with ChatGPT before development begins.
> 2. Creating the project structure and reusable AI-agent instructions.
> 3. Building the application later with Codex or another coding agent.
> 4. Preventing generic, cluttered, or “AI-generated” UI through an approved UI/UX foundation.
>
> **Primary objective:** Build a reliable, local-network-only graduation stage system where scanning a graduate's QR code identifies the graduate, records the event, updates the stage display in real time, and plays a verified local announcement.
>
> **Critical instruction:** Work on **one phase at a time**. Do not continue to the next phase until the current phase passes its acceptance criteria and a human approves the result.
>
> **Planning rule:** During pre-development planning, ChatGPT must produce decisions, specifications, textual wireframes, acceptance criteria, and project documents. It must not pretend that code, browser testing, or repository inspection has occurred when no repository exists.

---

## Revision Note

This version integrates pre-development planning and the UI/UX improvement system directly into the main playbook.

The most important changes are:

- Phase 0 now happens before a repository exists and is intended for ChatGPT planning.
- Phase 1 now creates the project structure together with `AGENTS.md`, UI/UX documents, agent skills, design tokens, and the base visual foundation.
- Frontend phases now require approved screen specifications, realistic states, accessibility, and browser-based visual verification.
- A Figma prototype is optional; textual wireframes and static browser previews are the default alternative.

---

# 1. Agent Role

The required role changes according to the current phase.

## 1.1 During pre-development planning

Act as a:

- Senior product analyst
- System architect
- Senior UI/UX designer
- Accessibility reviewer
- Event-operations specialist
- Technical documentation writer
- AI-agent workflow designer

The planning agent must convert ideas into clear, reviewable decisions before coding begins.

## 1.2 During implementation

Act as a senior Laravel and React engineer responsible for producing secure, maintainable, testable, accessible, and event-ready software.

Prioritize:

1. Correctness
2. Reliability
3. Data integrity
4. Recovery from errors
5. Simple operator experience
6. Clear visual hierarchy
7. Accessibility
8. Clear documentation
9. Maintainable code

Do not prioritize feature quantity, visual decoration, or rapid code generation over quality.

---

# 2. Project Summary

Build a local web-based graduation stage system with the following workflow:

```text
Graduate QR is scanned
        ↓
React scanner page submits the QR token through HTTP
        ↓
Laravel validates the token and ceremony rules
        ↓
PostgreSQL stores the scan and stage state
        ↓
Laravel broadcasts the resulting event
        ↓
Laravel Reverb transmits the WebSocket event
        ↓
Laravel Echo receives the event in the browser
        ↓
React updates the stage display
        ↓
Verified local announcement audio plays
```

The system must work without internet access.

---

# 3. Final Technology Stack

```text
Backend:                 Laravel
Frontend:                React
Frontend integration:    Inertia.js
Asset bundler:           Vite
Database:                PostgreSQL
Real-time server:        Laravel Reverb
WebSocket client:        Laravel Echo
QR input:                USB 2D QR scanner
Primary deployment:      Dedicated local network
Frontend deployment:     Compiled production assets
```

## Architecture decision

Use a **single Laravel application containing the React frontend through Inertia**.

Do not create separate Laravel API and React repositories unless explicitly approved later.

## Main runtime devices

```text
Laptop 1: Server
- Laravel
- PostgreSQL
- Laravel Reverb
- React production build
- Photos, audio, logs, and backups

Laptop 2: Scanner
- Browser opened to /scanner
- USB 2D QR scanner connected

Laptop 3: Stage
- Browser opened to /stage
- Connected to projector or LED wall
- Connected to venue audio system
```

An optional fourth laptop may be prepared as a cold-standby server.

---

# 4. Non-Negotiable Engineering Rules

Every agent must follow these rules.

## 4.1 Inspect before editing

Before changing code:

1. Inspect the repository structure.
2. Read the existing README, configuration, migrations, models, routes, and tests.
3. Identify the current project phase.
4. Reuse existing conventions.
5. Do not duplicate functionality that already exists.

## 4.2 Stay inside the assigned phase

Do not implement future-phase features.

Examples:

- During project setup, do not build the complete dashboard.
- During database design, do not add advanced reports.
- During the vertical prototype, do not redesign every page.
- During audio implementation, do not rebuild authentication.

## 4.3 Do not silently change architecture

Do not replace:

- PostgreSQL with SQLite
- Inertia with a separate React SPA
- Reverb with polling
- Laravel authentication with custom token authentication
- Local assets with internet-hosted CDNs

Any architecture change requires explicit human approval.

## 4.4 PostgreSQL is the source of truth

React state is temporary.

Important state must be stored in PostgreSQL, including:

- Current ceremony
- Current stage state
- Stage queue
- Scan history
- Presented graduate
- Completion status
- Audit actions

A browser refresh or reconnection must not destroy important state.

## 4.5 HTTP performs actions; Reverb announces changes

Use HTTP for:

- Opening pages
- Submitting QR scans
- Creating or editing records
- Fetching current state
- Presenting the next graduate
- Replaying audio
- Clearing the stage

Use Reverb and Echo for:

- Live queue updates
- Live stage updates
- Ceremony status changes
- Replay commands
- Connection-aware event notifications

Do not use WebSockets as the only persistence or validation mechanism.

## 4.6 Broadcast only after successful persistence

The required order is:

```text
Validate request
    ↓
Run database transaction
    ↓
Commit database changes
    ↓
Broadcast event after commit
```

Never broadcast a stage event before the database transaction succeeds.

## 4.7 No internet dependency

Do not rely on:

- Google Fonts
- External CDNs
- Cloud databases
- Online text-to-speech
- External QR generation APIs
- Internet-hosted images
- Remote JavaScript packages at runtime

All required runtime assets must exist locally.

## 4.8 Protect the audience-facing screen

The `/stage` page must never display:

- Debug errors
- Stack traces
- QR tokens
- Student numbers
- Admin controls
- Scanner controls
- Technical connection logs
- Browser notifications

## 4.9 Testing is part of implementation

A phase is incomplete until its required tests pass.

Do not report success based only on visual inspection.

## 4.10 Stop after the phase

After completing a development phase:

1. Run tests.
2. Run linting and formatting.
3. Perform required browser and visual checks for frontend work.
4. Summarize changed files.
5. Report known limitations.
6. Provide manual verification steps.
7. Stop and wait for approval.

After completing a planning phase:

1. Summarize decisions made.
2. List unresolved questions and assumptions.
3. Identify documents created or updated.
4. Provide the exact prompt for the next phase.
5. Stop and wait for approval.

## 4.11 Plan the experience before building screens

Do not begin a frontend screen until its purpose, user, information hierarchy, states, and acceptance criteria are documented.

For every major screen, define:

- Primary user
- Device and expected viewport
- Immediate user goal
- Dominant information
- Primary action
- Secondary information
- Common mistakes
- Loading state
- Empty state
- Success state
- Error state
- Disconnected state where relevant
- Keyboard behavior
- Responsive behavior
- Recovery actions

## 4.12 Follow the approved UI/UX foundation

Frontend work must follow the approved project documents under `docs/ui/`.

Do not allow each AI-agent session to invent a new visual style.

Do not introduce:

- A parallel design system
- A new UI library
- New color roles
- New typography rules
- New spacing conventions
- A different screen hierarchy

without explicit approval and documentation.

## 4.13 Avoid generic AI-generated UI

The interface must not default to a generic SaaS dashboard.

Avoid unless a real product requirement justifies them:

- Equal-sized statistic card grids
- Cards inside cards
- Excessive rounded rectangles
- Purple-blue gradients
- Glassmorphism
- Decorative glowing effects
- Fake analytics
- Decorative charts
- Oversized marketing headings
- Long explanatory copy in operational screens
- Icons beside every heading
- Badges and pills for ordinary text
- Unnecessary animation
- Every section having equal visual importance

Use clear hierarchy, restrained visual treatment, concise content, semantic status colors, and realistic information instead.

## 4.14 Visually verify frontend work

Compiling successfully does not prove that a screen is usable.

For frontend phases, the agent must:

1. Run the application.
2. Open the affected route in a real browser.
3. Test the primary workflow.
4. Test the required states.
5. Inspect the required viewport sizes.
6. Check overflow, wrapping, alignment, spacing, contrast, focus, and readability.
7. Correct visible high-impact problems.
8. Report exactly what was visually verified.

Never claim browser or visual verification when it was not performed.

## 4.15 Figma is optional

A Figma prototype is not required.

When no prototype exists, use:

- Product requirements
- Screen specifications
- Textual wireframes
- Static realistic sample data
- Temporary design-preview routes when appropriate
- Browser screenshots
- Human review

A vague instruction such as “make it modern” is not an acceptable design specification.

---

# 5. Required Agent Response Format

## 5.1 Planning-phase opening format

Use this when development has not started:

```text
Phase:
Current planning status:
Known decisions:
Questions or assumptions to resolve:
Documents to create or update:
Planning method:
Risks:
```

## 5.2 Planning-phase completion format

```text
Phase completed:
Decisions finalized:
Documents created:
Documents updated:
Textual wireframes produced:
Unresolved assumptions:
Human review checklist:
Exact prompt for the next phase:
```

## 5.3 Development-phase opening format

```text
Phase:
Current repository status:
Relevant approved requirements:
Relevant UI/UX specifications:
Files likely to change:
Implementation plan:
Risks:
Tests to run:
Viewports and workflows to verify:
```

## 5.4 Development-phase completion format

```text
Phase completed:
Files added:
Files modified:
Database changes:
Tests executed:
Test results:
Browser routes inspected:
Viewports inspected:
UI states inspected:
Manual verification:
Known limitations:
Recommended next phase:
```

The agent must not claim that files, tests, commands, browser checks, or repository inspections exist unless they were actually performed.

---

# 6. Global Definition of Done

A planning deliverable is complete only when:

- The user and operational goal are clear.
- Important assumptions are visible.
- The happy path and failure paths are documented.
- Screen purpose and information hierarchy are defined where relevant.
- Acceptance criteria are measurable.
- The document does not pretend that unperformed implementation or testing occurred.
- A human has reviewed the decisions before the next phase begins.

A developed feature is complete only when:

- The code follows existing project conventions.
- The feature follows the approved UI/UX documents.
- Input validation exists.
- Authorization is enforced where needed.
- Database constraints protect important rules.
- Loading, empty, success, error, and disconnected states exist where relevant.
- Error states are visible and actionable for operators.
- The primary information and action are visually clear.
- Keyboard operation works where required.
- No sensitive values are exposed to the browser.
- Relevant automated tests pass.
- Required browser and viewport checks are completed.
- Manual verification steps are documented.
- No internet connection is required at runtime.
- Existing features remain working.

---

# 7. Core Domain Concepts

## 7.1 Ceremony

A graduation event or ceremony session.

Recommended statuses:

```text
draft
prepared
active
paused
completed
archived
```

## 7.2 Graduate

The person whose QR code is scanned and whose information is displayed.

Important separate values:

```text
display_name
announcement_name
pronunciation_guide
```

## 7.3 QR token

A random unique token associated with one graduate.

Example:

```text
GRAD-2027-K9D4X7P2Q8
```

Do not encode personal information directly into the QR code.

## 7.4 Stage queue item

Represents a graduate's movement through the stage process.

Recommended statuses:

```text
waiting
ready
presenting
announced
completed
skipped
cancelled
```

## 7.5 Stage state

The authoritative current state of the stage, including:

- Current graduate
- Current queue item
- Display mode
- Audio status
- Automatic or controlled mode
- Last update version

## 7.6 Scan log

A permanent record of every scan attempt, including invalid and duplicate scans.

## 7.7 Stage event

A permanent record of important stage actions such as:

```text
student_queued
student_presented
announcement_replayed
stage_cleared
student_completed
student_skipped
ceremony_paused
```

---

# 8. Recommended Database Tables

The final schema may evolve during implementation, but the following entities are required.

## `users`

Suggested fields:

```text
id
name
username or email
password
role
is_active
created_at
updated_at
```

## `ceremonies`

Suggested fields:

```text
id
name
academic_year
venue
event_date
status
presentation_mode
activated_at
completed_at
created_at
updated_at
```

## `students`

Suggested fields:

```text
id
ceremony_id
student_number
qr_token
first_name
middle_name
last_name
suffix
display_name
announcement_name
pronunciation_guide
college
program
major
honors
sequence_number
photo_path
audio_path
eligibility_status
created_at
updated_at
```

## `stage_queue_items`

Suggested fields:

```text
id
ceremony_id
student_id
status
queue_position
scanned_at
ready_at
presented_at
announced_at
completed_at
scanned_by
presented_by
created_at
updated_at
```

## `stage_states`

Suggested fields:

```text
id
ceremony_id
current_queue_item_id
display_mode
audio_status
is_muted
version
created_at
updated_at
```

## `scan_logs`

Suggested fields:

```text
id
ceremony_id
student_id
raw_token_hash
scanner_device
result
message
scanned_by
scanned_at
```

## `stage_events`

Suggested fields:

```text
id
ceremony_id
queue_item_id
event_type
payload
performed_by
created_at
```

## `devices`

Suggested fields:

```text
id
name
device_type
device_key
ip_address
last_seen_at
status
created_at
updated_at
```

## `audit_logs`

Suggested fields:

```text
id
user_id
action
entity_type
entity_id
old_values
new_values
ip_address
created_at
```

---

# 9. Required Database Constraints

At minimum, enforce:

- Unique QR token
- Unique student number within a ceremony
- Unique sequence number within a ceremony
- Valid ceremony status
- Valid queue status
- Required ceremony relationship
- Required graduate relationship
- Prevention of duplicate active queue items
- Prevention of multiple presenting graduates in one ceremony

Use application validation and database constraints together.

---

# 10. Required Routes and Interfaces

The exact route names may follow project conventions.

## Page routes

```text
/login
/admin
/admin/ceremonies
/admin/students
/admin/import
/admin/qr-codes
/scanner
/stage-control
/stage
/logs
/settings
```

## Action or JSON endpoints

```text
POST /scans
GET  /stage/state
GET  /stage/queue
POST /stage/present-next
POST /stage/present/{queueItem}
POST /stage/replay
POST /stage/clear
POST /stage/skip/{queueItem}
POST /stage/complete/{queueItem}
POST /ceremonies/{ceremony}/activate
POST /ceremonies/{ceremony}/pause
POST /ceremonies/{ceremony}/resume
POST /students/import
POST /students/generate-qr
```

---

# 11. Required Broadcast Events

Recommended events:

```text
StudentQueued
QueueUpdated
StudentPresented
StudentCompleted
StageCleared
AudioReplayRequested
CeremonyPaused
CeremonyResumed
```

Recommended channel pattern:

```text
private-ceremony.{ceremonyId}
private-stage-control.{ceremonyId}
private-stage-display.{ceremonyId}
```

Reverb is responsible for transmission.

Echo is responsible for browser-side subscription and event listening.

PostgreSQL remains the source of truth.

---

# 12. Approved UI/UX Product Plan

This section defines the initial product experience before the first screen is coded.

The details may be refined during Phase 0, but they must not be ignored or replaced with a generic template.

## 12.1 UI/UX objective

The application must feel like a professional live-event operations system for an academic ceremony.

It must be:

- Clear under time pressure
- Formal and appropriate for graduation
- Calm rather than visually noisy
- Fast to scan
- Easy to operate with minimal training
- Difficult to misuse
- Accessible
- Reliable during network or device interruptions
- Consistent across all operator screens
- Highly readable on the audience-facing display

The goal is not to make the application merely look “modern.”

The goal is to make every screen deliberate, understandable, reliable, and appropriate for its user.

## 12.2 Initial visual direction

Use the following as the default design direction unless Phase 0 approves a different branded direction.

### Operator and administration screens

- Light neutral application background
- White or subtly tinted working surfaces
- Deep navy or charcoal primary text
- Muted university-gold accent
- Green, amber, and red used only for semantic statuses
- Thin borders and spacing before shadows
- Moderate corner radius
- Compact controls
- Clear section dividers
- One sans-serif font family stored locally or provided by the operating system

### Stage display

- Dark navy, charcoal, or near-black background
- High-contrast white graduate name
- Restrained gold accent
- Large typography readable from a distance
- Minimal official school identity
- No controls, debug data, connection logs, tables, or navigation
- Restrained transitions that never delay the next graduate

### Motion

- Use motion only to communicate a state change.
- Keep operational transitions short.
- Prevent repeated or overlapping animation.
- Support reduced-motion preferences.
- Never delay scanning, presenting, or recovery actions for animation.

### Visual assets

- All fonts, logos, icons, photos, and media required at runtime must be stored locally.
- Do not use external font services, CDNs, or internet-hosted imagery.
- Use one consistent icon library if icons are required.

## 12.3 Information hierarchy rules

Every screen must have one primary purpose.

Information must be classified as:

1. Critical
2. Supporting
3. Secondary
4. Optional

Critical information must be visually dominant.

Optional information must be removed, collapsed, or moved when it competes with the primary workflow.

Do not give every panel equal size, color, and visual weight.

## 12.4 Planned screen experience

### Scanner screen

**Primary user:** Scanner operator  
**Primary goal:** Capture a graduate QR code immediately and know whether the scan succeeded.

The screen should emphasize:

- Scanner readiness
- Active ceremony
- Network and server connection status
- Last scan result
- Graduate name for a successful scan
- Clear duplicate, invalid, ineligible, or paused-ceremony feedback
- Immediate recovery guidance
- Manual lookup fallback when approved

The screen should avoid:

- Analytics
- Full graduate tables
- Large navigation
- Decorative cards
- Unrelated ceremony controls
- Long instructions that remain visible during normal operation

Recommended hierarchy:

```text
1. Scanner readiness and scan input
2. Current scan result
3. Connection and ceremony status
4. Last successful scan
5. Recovery or manual fallback
```

### Stage-control screen

**Primary user:** Stage operator  
**Primary goal:** Control the live graduate sequence and recover from mistakes safely.

The screen should emphasize:

- Current graduate as the dominant region
- Next graduates as the secondary region
- Stage-display connection
- Audio status
- Present, complete, replay, skip, return, clear, and pause actions
- Recent actions as a compact tertiary list
- Confirmation or safe undo for risky actions

Recommended hierarchy:

```text
1. Current graduate
2. Primary stage action
3. Next graduates
4. Connection and audio status
5. Recent actions
```

Avoid equal-sized dashboard cards and decorative statistics.

### Stage display

**Primary users:** Audience and graduating student  
**Primary goal:** Present the correct graduate clearly and professionally.

The screen should emphasize:

- Graduate display name
- Program
- Honors when present
- Optional approved photo
- Official ceremony or institution identity

The screen must not show:

- Operator controls
- Navigation
- QR tokens
- Student numbers
- Technical warnings
- Debug information
- Timestamps
- Small administrative metadata

Long names and long program titles must remain readable without breaking the layout.

### Administration screens

**Primary users:** Administrator and data encoder  
**Primary goal:** Prepare accurate ceremony and graduate data efficiently.

Administration should emphasize:

- Search
- Filters
- Data quality
- Clear forms
- Validation
- Import preview
- Row-level errors
- Sequence management
- Local media status
- Permission-aware actions

Use compact tables and forms where they support efficient data work.

Do not convert every row or field into a large card.

### Login and setup screens

These screens should be simple, calm, and functional.

Avoid marketing copy, decorative statistics, large illustrations, and unnecessary onboarding steps.

## 12.5 Required UI/UX planning documents

The following files must be created during the approved phases.

```text
docs/
├── product.md
├── requirements.md
├── architecture.md
├── user-roles.md
├── user-flows.md
└── ui/
    ├── design-system.md
    ├── ux-rules.md
    ├── screen-specifications.md
    ├── content-rules.md
    ├── accessibility.md
    ├── responsive-rules.md
    ├── component-guidelines.md
    ├── ui-testing-checklist.md
    ├── visual-review-checklist.md
    └── references/
```

Use lowercase or the repository's approved naming convention consistently.

Do not create repetitive documents merely to satisfy the list.

Each document must have a clear responsibility.

## 12.6 Required AI-agent guidance files

During project bootstrap, create:

```text
AGENTS.md
.agents/
└── skills/
    ├── ui-ux-design/
    │   └── SKILL.md
    ├── ui-ux-review/
    │   └── SKILL.md
    └── visual-qa/
        └── SKILL.md
```

If the selected coding agent does not support this exact skill structure, preserve the same instructions in the closest supported project-local format.

### `AGENTS.md`

Keep it concise.

It should contain:

- Project summary
- Approved architecture
- Essential commands
- Phase boundaries
- Required documents to read
- Permanent UI/UX rules
- Testing requirements
- Visual-verification requirements
- Prohibited patterns

Do not copy the full contents of every UI document into `AGENTS.md`.

### UI/UX design skill

The design skill should require the agent to:

1. Read the relevant requirements and screen specification.
2. Identify the user, goal, device, and risks.
3. Produce textual layout options before implementation when no approved layout exists.
4. Define all important UI states.
5. Use realistic content.
6. Establish acceptance criteria.
7. Implement only after the structure is clear.

### UI/UX review skill

The review skill should require the agent to:

1. Inspect the real screen.
2. Test the primary workflow.
3. Find hierarchy, usability, accessibility, and consistency problems.
4. Identify generic AI-generated patterns.
5. Rank issues by severity.
6. Recommend the smallest defensible corrections.
7. Reinspect the screen after changes.

### Visual-QA skill

The visual-QA skill should require the agent to:

1. Run the application.
2. Open the affected route.
3. Test the required states.
4. Inspect required viewport sizes.
5. Check overflow, wrapping, spacing, alignment, contrast, and focus.
6. Fix high-impact visible defects.
7. Report what was actually inspected.

## 12.7 No-Figma workflow

Because no Figma prototype currently exists, use this workflow.

### Step 1 — Define the screen

Document:

- User
- Goal
- Context
- Device
- Time pressure
- Required information
- Required actions
- Common mistakes
- Required states

### Step 2 — Produce textual layout alternatives

For important screens, create two or three structurally different options.

For each option, explain:

- Information hierarchy
- Advantages
- Risks
- Best use case

Do not code the first idea automatically.

### Step 3 — Approve a textual wireframe

Example stage-control wireframe:

```text
┌──────────────────────────────────────────────────────────────┐
│ Graduation Control             Stage Connected   Audio Ready │
├───────────────────────────────────┬──────────────────────────┤
│ CURRENT GRADUATE                  │ NEXT IN LINE             │
│                                   │                          │
│ Christopher Emmanuel             │ 1. Ana Reyes             │
│ Villanueva-Santos                 │ 2. Mark Flores           │
│                                   │ 3. Juan Dela Cruz        │
│ BS Computer Science               │                          │
│ With Honors                       │                          │
│                                   │                          │
│ [Complete] [Replay] [More]        │ [Present Next]           │
├───────────────────────────────────┴──────────────────────────┤
│ RECENT ACTIONS                                               │
│ 10:42  Maria Santos completed                                │
│ 10:41  John Cruz duplicate scan rejected                     │
└──────────────────────────────────────────────────────────────┘
```

### Step 4 — Use realistic sample data

Always test with:

- Very long names
- Short names
- Long program titles
- Missing honors
- Missing photo
- Missing audio
- Duplicate QR
- Invalid QR
- Ineligible graduate
- Empty queue
- Disconnected stage
- Reconnected state

### Step 5 — Build a temporary static preview when needed

A temporary design-preview route may be created during the relevant implementation phase.

It must:

- Use static sample data
- Avoid modifying production workflow
- Exist only to review layout, hierarchy, states, and responsiveness
- Be removed or protected before production deployment

### Step 6 — Visually inspect and iterate

The first generated screen is a draft, not the final result.

Inspect it in the browser, compare it with its specification, and correct the highest-impact problems before integration is considered complete.

## 12.8 Required responsive targets

At minimum, plan and test:

```text
Stage display:          1920 × 1080
Operator desktop:       1440 × 900
Common laptop:          1366 × 768
Small laptop/tablet:    1024 × 768
```

Mobile support is required only for screens explicitly approved for mobile use.

Do not blindly turn desktop interfaces into long stacks of cards.

For each screen, document:

- What remains visible
- What may collapse
- What may scroll
- What must never be hidden
- Minimum supported width
- Overflow behavior

## 12.9 Content and feedback rules

Use:

- Short labels
- Clear verbs
- Specific status messages
- Actionable recovery instructions
- Consistent terminology
- Sentence case
- Plain language

Avoid:

- “Something went wrong”
- Technical error codes without explanation
- Repeated page descriptions
- Long paragraphs during live operations
- Robotic confirmations
- Vague buttons such as “Submit” when a specific action is known

Examples:

```text
Bad:  Error
Good: QR code not recognized

Bad:  Submit
Good: Add graduate

Bad:  Something went wrong
Good: The stage display did not receive the update. Check its connection and try again.
```

## 12.10 Accessibility requirements

At minimum:

- Full keyboard navigation for operator workflows
- Logical focus order
- Visible focus indicators
- Accessible names for icon-only controls
- Form labels
- Errors associated with the affected fields
- Status communication that does not rely only on color
- Sufficient contrast
- Reduced-motion support
- Appropriate live-region announcements
- Large readable stage-display text
- QR scanner focus behavior that does not trap normal keyboard use
- Minimum interactive target sizes appropriate for pressured operation

## 12.11 Frontend phase gate

Before implementing a frontend screen, the agent must confirm:

```text
[ ] Relevant product requirement is approved
[ ] User and primary goal are documented
[ ] Screen specification exists
[ ] Information hierarchy is approved
[ ] Textual wireframe is approved or current phase allows creating it
[ ] Required states are listed
[ ] Responsive targets are listed
[ ] Accessibility requirements are listed
[ ] Realistic sample data is available
[ ] Acceptance criteria are measurable
```

After implementing a frontend screen, the agent must confirm:

```text
[ ] Primary workflow was tested
[ ] Loading state was inspected
[ ] Empty state was inspected
[ ] Success state was inspected
[ ] Error state was inspected
[ ] Disconnected state was inspected when relevant
[ ] Long content was inspected
[ ] Keyboard behavior was tested
[ ] Required viewports were inspected
[ ] Generic AI-generated patterns were reviewed
[ ] Visible high-impact defects were corrected
[ ] Documentation and UI changelog were updated
```

---

# 13. Development Phases

---

## Phase 0 — Pre-Development Requirements, UX Planning, and Architecture Freeze

### Goal

Create an approved product, workflow, architecture, and UI/UX baseline before any project repository or feature code is created.

This phase is intended to be completed primarily with ChatGPT.

### Current condition

The application has not yet entered development.

If no repository exists:

- Do not claim to inspect a repository.
- Do not invent installed versions.
- Do not claim to run commands or tests.
- Produce planning documents that can later be added to the repository.

If a partial repository already exists, inspect only what is actually available and clearly separate observed facts from planned decisions.

### Scope

- Freeze MVP requirements.
- Confirm runtime devices and local-network architecture.
- Confirm user roles.
- Confirm graduate data fields.
- Confirm stage display content.
- Confirm automatic and controlled modes.
- Confirm QR preparation and distribution process.
- Confirm announcement format and local-audio workflow.
- Define happy paths and failure paths.
- Define the initial UI/UX direction.
- Define screen purposes and information hierarchy.
- Produce textual wireframes.
- Record unresolved assumptions and risks.
- Define development acceptance criteria.

### Do not implement

- Laravel project
- React project
- Database migrations
- UI components
- Reverb events
- QR generation
- Import features
- Audio playback
- Production assets

### Tasks

1. Review this complete playbook.
2. Confirm the final project name and project purpose.
3. Confirm the expected ceremony workflow from preparation through completion.
4. Confirm the responsibilities and permissions of:
   - administrator
   - data encoder
   - scanner operator
   - stage operator
   - technical operator
5. Confirm required graduate fields.
6. Confirm the approved scanner behavior.
7. Confirm automatic-mode and controlled-mode behavior.
8. Confirm the audience-facing stage content.
9. Confirm audio preparation, pronunciation review, and replay behavior.
10. Document normal workflows.
11. Document failure and recovery scenarios.
12. Define MVP and deferred features.
13. Define the initial visual direction from Section 12.
14. Produce textual wireframes for:
    - scanner
    - stage control
    - stage display
    - graduate administration
15. Define UI states and acceptance criteria for each major screen.
16. Create planning versions of:
    - `docs/product.md`
    - `docs/requirements.md`
    - `docs/architecture.md`
    - `docs/user-roles.md`
    - `docs/user-flows.md`
    - `docs/ui/design-direction.md`
    - `docs/ui/screen-specifications.md`
17. Record:
    - finalized decisions
    - assumptions
    - unresolved questions
    - initial risks
    - human approval checklist
18. Produce the exact Phase 1 prompt.

### Required planning content

The screen specifications must include:

- Screen name
- Future route
- Primary user
- Device
- Primary goal
- Dominant information
- Primary action
- Secondary information
- Required states
- Keyboard behavior
- Responsive targets
- Accessibility requirements
- Recovery behavior
- Prohibited content
- Textual wireframe
- Acceptance criteria

### Deliverables

- Product brief
- Requirements document
- Architecture document
- User-role document
- User-flow document
- Initial UI/UX direction
- Screen specifications
- Textual wireframes
- Assumptions list
- Initial risk list
- MVP boundary
- Exact Phase 1 prompt

### Acceptance criteria

- No major MVP requirement is undocumented.
- Architecture matches this playbook.
- User roles and responsibilities are clear.
- Normal and failure workflows are documented.
- Each major screen has one clear primary purpose.
- The scanner, stage-control, and stage-display hierarchy is approved.
- UI/UX rules do not rely on a missing Figma prototype.
- MVP and deferred features are separated.
- The documents distinguish approved decisions from assumptions.
- A human approves the Phase 0 planning documents.

### Stop condition

Stop after planning documents and textual wireframes are approved.

Do not initialize the project or begin feature coding during Phase 0.

---

## Phase 1 — Project Bootstrap, Agent Guidance, and UI Foundation

### Goal

Create a clean, reproducible Laravel, React, Inertia, Vite, and PostgreSQL foundation together with the project-local UI/UX instructions that future AI agents must follow.

### Preconditions

Phase 0 planning documents and textual wireframes must be approved.

The coding agent must read:

- This playbook
- `docs/product.md`
- `docs/requirements.md`
- `docs/architecture.md`
- `docs/user-roles.md`
- `docs/user-flows.md`
- Approved Phase 0 UI/UX documents

### Scope

- Laravel application setup
- React and Inertia integration
- PostgreSQL connection
- Environment configuration
- Base formatting and testing tools
- Base application layout
- Project documentation structure
- Concise `AGENTS.md`
- Reusable UI/UX agent skills
- Design tokens and base styling foundation
- Local asset policy
- Browser-verification tooling where approved
- UI documentation migrated into the repository

### Do not implement

- Complete graduate management
- Functional QR scanning
- Reverb workflow
- Persistent stage queue
- Audio announcement workflow
- Reports
- Production admin dashboard
- Decorative themes
- Feature screens beyond a minimal shell or approved static design preview

### Required project structure

Create only the directories and files needed by this phase.

Recommended planning and agent structure:

```text
project-root/
├── AGENTS.md
├── README.md
├── docs/
│   ├── product.md
│   ├── requirements.md
│   ├── architecture.md
│   ├── user-roles.md
│   ├── user-flows.md
│   └── ui/
│       ├── design-system.md
│       ├── ux-rules.md
│       ├── screen-specifications.md
│       ├── content-rules.md
│       ├── accessibility.md
│       ├── responsive-rules.md
│       ├── component-guidelines.md
│       ├── ui-testing-checklist.md
│       ├── visual-review-checklist.md
│       └── references/
├── .agents/
│   └── skills/
│       ├── ui-ux-design/
│       │   └── SKILL.md
│       ├── ui-ux-review/
│       │   └── SKILL.md
│       └── visual-qa/
│           └── SKILL.md
└── CHANGELOG_UI.md
```

If a selected coding agent does not support `.agents/skills/`, preserve the same reusable workflows in its supported project-local instruction format.

### Tasks

1. Initialize or verify the Laravel project.
2. Install or verify React and Inertia.
3. Configure Vite.
4. Configure PostgreSQL through environment variables.
5. Create a local development database.
6. Configure application timezone.
7. Add baseline authentication scaffolding only if approved.
8. Add code formatting and linting.
9. Confirm backend tests can run.
10. Confirm frontend linting can run.
11. Add browser testing or screenshot tooling only when justified and documented.
12. Create a basic authenticated React page or minimal application shell.
13. Create `.env.example` without secrets.
14. Document setup commands.
15. Move approved Phase 0 planning documents into the repository.
16. Create a concise `AGENTS.md` that:
    - summarizes the project
    - preserves architecture decisions
    - enforces phase boundaries
    - points agents to detailed UI documents
    - prohibits generic AI-generated UI patterns
    - requires testing and visual verification
17. Create or finalize:
    - `docs/ui/design-system.md`
    - `docs/ui/ux-rules.md`
    - `docs/ui/screen-specifications.md`
    - `docs/ui/content-rules.md`
    - `docs/ui/accessibility.md`
    - `docs/ui/responsive-rules.md`
    - `docs/ui/component-guidelines.md`
    - `docs/ui/ui-testing-checklist.md`
    - `docs/ui/visual-review-checklist.md`
18. Create the UI/UX design, UI/UX review, and visual-QA agent skills.
19. Establish initial design tokens for:
    - semantic colors
    - typography
    - spacing
    - borders
    - corner radius
    - shadows
    - motion
    - focus states
20. Ensure all runtime fonts and visual assets are local.
21. Create only the base UI primitives currently needed.
22. Do not create a large unused component library.
23. Add a minimal static design-preview route only if needed to verify the base shell and approved design direction.
24. Visually inspect the base shell at approved desktop viewports.
25. Create the initial `CHANGELOG_UI.md`.

### UI foundation requirements

The base UI foundation must:

- Use semantic design tokens.
- Use one approved typography system.
- Use consistent spacing.
- Provide visible keyboard focus.
- Include reusable status colors.
- Avoid external runtime assets.
- Avoid unnecessary gradients and shadows.
- Avoid card-heavy starter-template styling.
- Make future scanner, stage-control, stage-display, and administration screens visually related without making them structurally identical.

### Required checks

```text
Laravel starts successfully.
React page renders through Inertia.
PostgreSQL connection succeeds.
Migrations can run.
Frontend assets compile.
Backend tests run.
Frontend linting runs.
Production frontend build succeeds.
AGENTS.md points to the approved project documents.
UI/UX skill files are discoverable in the selected agent workflow.
No runtime font, icon, image, or script requires internet.
Base layout follows the approved visual direction.
Base layout was opened in a browser.
Required desktop viewports were inspected.
```

### Deliverables

- Running project foundation
- PostgreSQL configuration
- React/Inertia base page
- Setup documentation
- Environment example file
- Project-local planning documents
- Concise `AGENTS.md`
- UI/UX design system
- UX and content rules
- Screen specifications
- Accessibility and responsive rules
- UI testing and visual-review checklists
- Reusable UI/UX agent skills
- Initial design tokens
- UI changelog
- Browser-verified base shell

### Acceptance criteria

- A new developer can follow the README and run the project.
- Phase 0 decisions are represented in project-local documentation.
- Future agents have clear UI/UX instructions without requiring the full playbook in every prompt.
- No runtime dependency requires internet after packages are installed and assets are built.
- Database migrations run successfully.
- Production frontend build succeeds.
- The base shell does not look like a generic SaaS dashboard.
- The base shell was inspected in a browser at 1440×900 and 1366×768.
- No complete future-phase feature was implemented.
- A human approves the UI foundation and agent guidance files.

### Stop condition

Stop after the project foundation, UI/UX documentation, agent instructions, and base shell are reproducible and approved.

---

## Phase 2 — Authentication, Roles, and Core Data Model

### Goal

Create the secure domain foundation.

### Scope

- Authentication
- Role authorization
- Core migrations
- Eloquent models
- Factories and seeders
- Database constraints

### Do not implement

- Scanner workflow
- Reverb
- Stage display
- Audio playback
- CSV import
- QR printing

### Tasks

1. Implement user authentication.
2. Define roles:
   - administrator
   - data_encoder
   - scanner_operator
   - stage_operator
   - technical_operator
3. Implement policies or middleware.
4. Create migrations for:
   - ceremonies
   - students
   - stage_queue_items
   - stage_states
   - scan_logs
   - stage_events
   - devices
   - audit_logs
5. Create corresponding models and relationships.
6. Add enum-like validation or constrained values.
7. Add database indexes.
8. Add unique constraints.
9. Add factories and seed data.
10. Add tests for permissions and relationships.

### Acceptance criteria

- Unauthorized users cannot access restricted pages.
- Database constraints prevent duplicate QR tokens.
- Database constraints prevent duplicate student numbers per ceremony.
- Relationships work correctly.
- Seed data can create a sample ceremony and sample graduates.
- Tests pass.

### Stop condition

Stop after the data model and permissions are stable.

---

## Phase 3 — Critical Vertical Slice

### Goal

Prove the complete scan-to-stage architecture with a small sample dataset.

### Scope

- Five sample graduates
- Scanner page
- HTTP scan endpoint
- QR validation
- PostgreSQL transaction
- Reverb
- Echo
- Stage page
- One local audio file per sample graduate
- State recovery after refresh

### Do not implement

- Full admin dashboard
- CSV import
- QR batch printing
- Advanced reports
- Multiple visual themes
- Complex queue reordering

### Required flow

```text
USB scanner or manual token entry
        ↓
POST /scans
        ↓
Laravel validates token
        ↓
PostgreSQL stores scan and stage state
        ↓
Laravel broadcasts after commit
        ↓
Reverb sends event
        ↓
Echo receives event
        ↓
React displays graduate
        ↓
Local audio plays
```

### Tasks

1. Build the `/scanner` Inertia React page.
2. Implement automatic input focus.
3. Accept scanner keyboard input ending with Enter.
4. Create `POST /scans`.
5. Validate:
   - token exists
   - ceremony is active
   - graduate is eligible
   - graduate is not already presenting or completed
6. Run scan processing in a database transaction.
7. Record a scan log.
8. Create or update the stage queue item.
9. Create or update the stage state.
10. Broadcast only after commit.
11. Configure Laravel Reverb.
12. Configure Laravel Echo.
13. Build the `/stage` React page.
14. Subscribe to the ceremony stage channel.
15. Display the graduate.
16. Play verified local audio.
17. Implement `GET /stage/state`.
18. Restore state after stage-page refresh.
19. Read the approved scanner and stage-display specifications before implementing UI.
20. If the approved layout is not detailed enough, produce textual layout alternatives and obtain approval before coding.
21. Use realistic sample data, including long names and long programs.
22. Add loading, empty, success, error, duplicate, invalid, missing-audio, disconnected, and reconnected states where relevant.
23. Preserve automatic QR-scanner focus without trapping normal keyboard navigation.
24. Visually verify `/scanner` at 1440×900, 1366×768, and 1024×768.
25. Visually verify `/stage` at 1920×1080 and 1366×768.
26. Confirm the stage display remains readable at distance and contains no operator or technical information.
27. Add automated tests for scan validation and persistence.
28. Update `CHANGELOG_UI.md`.

### Acceptance criteria

- A QR token entered on one browser updates the stage browser immediately.
- The scan is stored exactly once.
- Invalid QR tokens do not change the stage.
- Duplicate scans are rejected or handled according to documented rules.
- Stage refresh restores the current graduate.
- Missing Reverb events can be recovered using `GET /stage/state`.
- Audio does not overlap.
- The feature works across separate devices on one local network.
- Scanner feedback is immediate, specific, and actionable.
- The stage display follows the approved hierarchy and remains readable with long names.
- Loading, error, disconnected, and recovery states were visually inspected.
- The required viewports were opened in a browser.
- No generic dashboard cards, fake metrics, or operator controls appear on the stage display.

### Manual verification

Test with:

- Valid QR
- Invalid QR
- Duplicate QR
- Browser refresh
- Reverb disconnect and reconnect
- Missing audio
- Very long name

### Stop condition

Stop when the complete vertical slice is stable. Do not proceed until approved.

---

## Phase 4 — Ceremony and Graduate Administration

### Goal

Allow authorized users to prepare ceremonies and graduate records.

### Scope

- Ceremony CRUD
- Graduate CRUD
- Validation
- Search and filters
- Photo upload
- Audio upload and preview

### Do not implement

- CSV import
- QR batch printing
- Advanced reports
- Full stage queue control

### Tasks

1. Build ceremony list, create, edit, and detail pages.
2. Build graduate list, create, edit, and detail pages.
3. Enforce role permissions.
4. Validate graduate names, program, sequence, and ceremony.
5. Add photo upload.
6. Add announcement audio upload.
7. Add audio preview.
8. Add filters:
   - ceremony
   - college
   - program
   - eligibility
   - stage status
9. Add search by name and student number.
10. Add pagination.
11. Add tests for CRUD and authorization.
12. Add audit logs for important changes.
13. Follow the approved administration screen specification and component guidelines.
14. Test forms with validation errors, long values, missing media, and permission restrictions.
15. Visually inspect primary administration routes at 1440×900, 1366×768, and 1024×768.
16. Update `CHANGELOG_UI.md`.

### Acceptance criteria

- Authorized users can manage ceremonies and graduates.
- Unauthorized users cannot modify records.
- Uploaded files are stored locally.
- Invalid files are rejected.
- Existing stage workflow remains operational.
- Tests pass.

### Stop condition

Stop after reliable manual data management is complete.

---

## Phase 5 — CSV Import and Data Preparation

### Goal

Allow safe bulk preparation of graduate data.

### Scope

- CSV upload
- Column mapping
- Preview
- Validation
- Error reporting
- Confirmed import
- Import summary

### Do not implement

- Excel-specific editing
- Cloud spreadsheet integration
- Automatic internet lookup
- QR printing

### Tasks

1. Define supported CSV format.
2. Implement CSV upload.
3. Preview rows before saving.
4. Allow column mapping.
5. Validate each row.
6. Detect:
   - duplicate student numbers
   - duplicate sequence numbers
   - missing names
   - invalid ceremony
   - unsupported values
7. Show row-level errors.
8. Require explicit confirmation.
9. Run import in a transaction.
10. Produce an import summary.
11. Add tests for valid and invalid imports.
12. Document the CSV template.

### Acceptance criteria

- Invalid rows are clearly reported.
- No records are silently skipped.
- The user sees a preview before import.
- Import can be rolled back when a critical error occurs.
- Duplicate records are handled according to documented rules.
- Tests pass.

### Stop condition

Stop after import is safe and repeatable.

---

## Phase 6 — QR Generation and Scanner Hardening

### Goal

Generate secure QR codes and make scanner operation reliable under ceremony pressure.

### Scope

- Random token generation
- Individual QR generation
- Batch QR generation
- Printable QR cards
- Scanner usability
- Duplicate and rapid-scan protection

### Do not implement

- Public QR portal
- Cloud QR service
- Phone camera scanner unless separately approved
- Advanced reporting

### Tasks

1. Generate random non-sequential QR tokens.
2. Enforce uniqueness.
3. Generate local QR images.
4. Generate individual QR cards.
5. Generate batch printable output.
6. Print graduate name and sequence below the code.
7. Add token regeneration with confirmation.
8. Improve scanner auto-focus behavior.
9. Prevent double submission while processing.
10. Add visual and audio feedback.
11. Add rapid-scan protection.
12. Add manual graduate lookup fallback.
13. Test with an actual USB 2D QR scanner.
14. Verify success, duplicate, invalid, processing, disconnected, and manual-fallback feedback visually.
15. Confirm the scanner remains usable entirely by keyboard and scanner input.
16. Inspect the scanner route at 1440×900, 1366×768, and 1024×768.
17. Document scanner configuration.
18. Update `CHANGELOG_UI.md`.

### Acceptance criteria

- Every QR identifies exactly one graduate.
- QR codes work without internet.
- Scanner input is captured reliably.
- Rapid duplicate scans do not create duplicate stage events.
- Damaged or unreadable QR codes can be handled through manual search.
- Printable output is readable by the physical scanner.

### Stop condition

Stop after physical-scanner testing passes.

---

## Phase 7 — Persistent Queue and Stage Control

### Goal

Protect stage operation from early scans, sequence changes, and operator mistakes.

### Scope

- Persistent queue
- Automatic mode
- Controlled mode
- Stage-control page
- Present next
- Present selected
- Skip
- Return
- Complete
- Clear stage
- Undo where safe

### Do not implement

- Advanced analytics
- Multi-campus synchronization
- Cloud control

### Tasks

1. Build `GET /stage/queue`.
2. Build the stage-control React page.
3. Display current graduate and upcoming graduates.
4. Implement automatic mode.
5. Implement controlled mode.
6. Enforce one presenting graduate at a time.
7. Implement:
   - present next
   - present selected
   - skip
   - return to queue
   - complete
   - clear stage
8. Record every action in stage events.
9. Broadcast queue and stage changes.
10. Restore queue after refresh.
11. Add concurrency protection.
12. Add authorization tests.
13. Add workflow tests.
14. Follow the approved stage-control hierarchy:
    - current graduate dominant
    - primary action obvious
    - next graduates secondary
    - connection and audio status always visible
    - recent actions compact and tertiary
15. Test rapid actions, long names, empty queue, paused ceremony, disconnected stage, and recovery states.
16. Visually inspect `/stage-control` at 1440×900, 1366×768, and 1024×768.
17. Update `CHANGELOG_UI.md`.

### Acceptance criteria

- Queue order persists in PostgreSQL.
- Only one graduate can be presenting.
- A rapid scan cannot incorrectly replace the current graduate.
- Stage control works after refresh.
- Every operator action is recorded.
- Automatic and controlled modes behave according to documented rules.
- Tests pass.

### Stop condition

Stop after the queue workflow survives refreshes and rapid actions.

---

## Phase 8 — Announcement Audio Reliability

### Goal

Make announcement playback predictable and event-ready.

### Scope

- Local verified audio
- Preloading
- Replay
- Stop
- Mute
- Missing-audio handling
- Playback state
- Volume consistency guidance

### Do not implement

- Online text-to-speech
- AI-generated speech during the ceremony
- Cloud media storage

### Tasks

1. Define supported audio formats.
2. Preload the current and next audio.
3. Prevent overlapping playback.
4. Stop current audio before replay.
5. Add one-time audio activation.
6. Add replay control.
7. Add stop control.
8. Add mute control.
9. Show missing-audio warning to stage operators.
10. Keep technical warnings off the audience screen.
11. Record replay actions.
12. Document audio-preparation workflow.
13. Test with the actual venue audio output when available.

### Acceptance criteria

- Audio works without internet.
- Two announcements cannot overlap.
- Replay is reliable.
- Missing audio does not crash the stage.
- The audience screen remains clean.
- Operators receive clear warnings.
- Playback works after page refresh and reconnection.

### Stop condition

Stop after real-device audio testing passes.

---

## Phase 9 — Logs, Audit Trail, Reports, Backup, and Restore

### Goal

Make all critical activity traceable and recoverable.

### Scope

- Scan logs
- Stage event history
- Audit logs
- Ceremony completion report
- PostgreSQL backup
- Restore procedure
- Local media backup

### Do not implement

- Business intelligence dashboards
- Cloud backup
- Internet synchronization

### Tasks

1. Build scan-log views.
2. Build stage-event views.
3. Build audit-log views.
4. Add filters and export where approved.
5. Build ceremony completion summary.
6. Create a PostgreSQL backup command or script.
7. Back up uploaded photos and audio.
8. Document restore steps.
9. Test restoration on a separate database.
10. Add confirmation for destructive ceremony reset.
11. Record reset operations.

### Acceptance criteria

- Every scan and important stage action is traceable.
- Backup can be created without manual database editing.
- Restore is successfully tested.
- Media files are included in backup instructions.
- Reset operations require authorization and confirmation.

### Stop condition

Stop after a verified restore test.

---

## Phase 10 — Local Deployment and Device Setup

### Goal

Prepare a stable offline deployment for three laptops.

### Scope

- Production build
- Server configuration
- Fixed local address
- Reverb service
- PostgreSQL service
- Local assets
- Device setup documentation
- Kiosk or full-screen guidance

### Do not implement

- Public internet deployment
- Cloud hosting
- External DNS dependency

### Tasks

1. Build production frontend assets.
2. Disable debug mode.
3. Confirm no CDN or internet dependencies.
4. Configure the server laptop's fixed IP.
5. Configure PostgreSQL startup.
6. Configure Laravel application startup.
7. Configure Reverb startup.
8. Configure local firewall rules.
9. Test scanner and stage access from separate laptops.
10. Test wired and wireless network paths.
11. Document:
    - server startup
    - scanner startup
    - stage startup
    - shutdown
    - emergency restart
12. Disable sleep and automatic updates on event devices.
13. Prepare backup network instructions.
14. Prepare cold-standby server instructions.

### Acceptance criteria

- Three devices communicate without internet.
- The system survives a browser refresh.
- Services restart predictably.
- All runtime assets load locally.
- A technician can start the system using documentation alone.

### Stop condition

Stop after a complete local deployment test.

---

## Phase 11 — Automated Testing, Failure Testing, and Full Rehearsal

### Goal

Prove event readiness.

### Scope

- Backend tests
- Frontend tests
- Workflow tests
- Failure simulation
- Full ceremony rehearsal
- Readiness checklist

### Required scenarios

1. Valid QR
2. Invalid QR
3. Duplicate QR
4. Wrong ceremony
5. Ineligible graduate
6. Two rapid scans
7. Out-of-sequence graduate
8. Missing audio
9. Replay during playback
10. Stage refresh
11. Scanner refresh
12. Reverb disconnect
13. Reverb reconnect
14. PostgreSQL restart
15. Laravel restart
16. Server restart
17. Wrong graduate selected
18. Long name
19. Multiple honors
20. Manual search
21. Stage clear
22. Automatic mode
23. Controlled mode
24. Backup restoration
25. Actual projector and audio test

### Tasks

1. Run the full automated test suite.
2. Run frontend linting.
3. Run production build.
4. Simulate network loss.
5. Simulate browser refresh.
6. Simulate service restarts.
7. Test backup restoration.
8. Test physical scanner.
9. Test projector output.
10. Test sound-system output.
11. Conduct a full sequence rehearsal.
12. Record issues.
13. Perform a complete UI/UX review using the approved checklists.
14. Inspect every operational screen at its required viewport.
15. Verify keyboard workflows, focus visibility, contrast, reduced motion, long content, and actionable error recovery.
16. Confirm the stage screen contains no controls, debug information, or technical warnings.
17. Fix only verified issues.
18. Repeat failed scenarios.
19. Update `CHANGELOG_UI.md`.
20. Produce an event-readiness report.

### Final acceptance criteria

- Works without internet.
- Correct graduate appears for each valid QR.
- Invalid scans do not affect the stage.
- Duplicate scans do not create duplicate presentations.
- Audio does not overlap.
- State survives refresh and reconnection.
- Queue remains correct.
- Operators can recover from mistakes.
- Backup restoration works.
- Full rehearsal succeeds.

### Stop condition

Stop after the event-readiness report is approved.

---

# 14. Deferred Features

Do not add these during the initial release unless explicitly approved:

- Public graduate portal
- Cloud synchronization
- Facial recognition
- Mobile application
- Online registration
- AI-generated speech during the event
- Multi-campus synchronization
- Livestream integration
- Advanced analytics
- Guest attendance tracking
- Public APIs

---

# 15. Security and Privacy Requirements

- Do not place personal information directly in QR codes.
- Do not expose database credentials to React.
- Do not allow browsers to connect directly to PostgreSQL.
- Validate and authorize every state-changing request.
- Hash or avoid storing raw invalid QR tokens where appropriate.
- Restrict stage and control channels.
- Hide technical errors from the audience screen.
- Store passwords using Laravel's standard password hashing.
- Keep `.env` out of version control.
- Do not log secrets.
- Limit uploaded file types and sizes.
- Protect destructive actions with confirmation and authorization.

---

# 16. Performance and Reliability Requirements

- Use database indexes for token and queue lookups.
- Avoid loading all graduates into the browser.
- Paginate admin lists.
- Keep the stage event payload small.
- Preload current and next media only.
- Prevent duplicate concurrent scan handling.
- Use transactions for multi-record state changes.
- Restore state through HTTP after WebSocket reconnection.
- Do not make the stage display depend exclusively on transient React state.
- Do not use the Vite development server during the ceremony.

---

# 17. Coding Quality Requirements

- Use Laravel Form Requests for non-trivial validation.
- Use policies or middleware for authorization.
- Keep controllers small.
- Put complex workflows in action or service classes.
- Use descriptive events.
- Use database transactions for stage state changes.
- Add tests for business rules.
- Avoid duplicated constants and status strings.
- Prefer framework conventions over custom infrastructure.
- Document non-obvious decisions.
- Do not over-engineer early phases.

---

# 18. Recommended Laravel Structure

```text
app/
├── Actions/
│   ├── ScanStudent.php
│   ├── QueueStudent.php
│   ├── PresentStudent.php
│   ├── CompleteStudent.php
│   └── ResetCeremony.php
├── Events/
│   ├── StudentQueued.php
│   ├── QueueUpdated.php
│   ├── StudentPresented.php
│   ├── StudentCompleted.php
│   ├── StageCleared.php
│   └── AudioReplayRequested.php
├── Http/
│   ├── Controllers/
│   ├── Requests/
│   └── Middleware/
├── Models/
├── Policies/
├── Services/
│   ├── QRCodeService.php
│   ├── ImportService.php
│   ├── AnnouncementService.php
│   ├── StageService.php
│   └── BackupService.php
└── Support/
```

This is a recommendation, not permission to create empty classes prematurely.

Create a class only when the current phase needs it.

---

# 19. Recommended React and UI Structure

```text
resources/
├── css/
│   └── app.css
└── js/
    ├── Components/
    │   ├── UI/
    │   │   ├── Button.jsx
    │   │   ├── IconButton.jsx
    │   │   ├── Field.jsx
    │   │   ├── StatusIndicator.jsx
    │   │   ├── Alert.jsx
    │   │   └── Dialog.jsx
    │   ├── Graduation/
    │   │   ├── GraduateIdentity.jsx
    │   │   ├── ScannerStatus.jsx
    │   │   ├── ConnectionIndicator.jsx
    │   │   ├── QueueItem.jsx
    │   │   └── AudioControls.jsx
    ├── Layouts/
    ├── Pages/
    │   ├── Admin/
    │   ├── Ceremonies/
    │   ├── Students/
    │   ├── Scanner/
    │   ├── StageControl/
    │   └── Stage/
    ├── Services/
    │   ├── echo.js
    │   └── audio.js
    └── app.jsx
```

Use semantic design tokens in the approved styling system.

Separate generic UI primitives from graduation-domain components.

Do not create every listed component during bootstrap. Create a component only when the current phase needs it.

Follow the actual starter-kit conventions where they differ.

---

# 20. First Prompt — ChatGPT Planning Stage

Use this prompt in the ChatGPT conversation that is planning the project.

```text
You are helping me plan the Graduation Stage System described in
GRADUATION_STAGE_SYSTEM_AI_AGENT_DEVELOPMENT_PLAYBOOK.md.

The project is still in pre-development planning. No application repository or
production UI exists yet.

Your assigned task is Phase 0 only:
Pre-Development Requirements, UX Planning, and Architecture Freeze.

Rules:

1. Read the complete playbook.
2. Do not write application code.
3. Do not claim to inspect a repository, run commands, open a browser, or execute
   tests when none exists.
4. Review and organize all known project decisions.
5. Identify contradictions, missing requirements, assumptions, and risks.
6. Finalize:
   - product purpose
   - MVP boundary
   - user roles
   - ceremony workflow
   - failure and recovery workflows
   - architecture
   - required data
   - scanner behavior
   - stage-control behavior
   - stage-display content
   - local-audio workflow
7. Use Section 12 to create the UI/UX plan.
8. Produce textual wireframes for:
   - scanner
   - stage control
   - stage display
   - graduate administration
9. Create planning-ready content for:
   - docs/product.md
   - docs/requirements.md
   - docs/architecture.md
   - docs/user-roles.md
   - docs/user-flows.md
   - docs/ui/design-direction.md
   - docs/ui/screen-specifications.md
10. Work only on Phase 0.
11. Stop after Phase 0 is complete.
12. At the end, provide the exact prompt for Phase 1.

Before drafting the documents, report:

- current planning status
- decisions already known
- assumptions or contradictions found
- documents to create
- planning sequence
- risks
```

## Phase 1 coding-agent handoff prompt

Use the following only after Phase 0 has been approved and development is ready to begin.

```text
You are working on the Graduation Stage System described in
GRADUATION_STAGE_SYSTEM_AI_AGENT_DEVELOPMENT_PLAYBOOK.md.

Your assigned task is Phase 1 only:
Project Bootstrap, Agent Guidance, and UI Foundation.

Rules:

1. Read the complete playbook and all approved Phase 0 planning documents.
2. Inspect the actual repository before editing.
3. If no repository exists, initialize it using the approved stack.
4. Do not implement future-phase features.
5. Create the reproducible Laravel, React, Inertia, Vite, and PostgreSQL
   foundation.
6. Create the project-local documentation, concise AGENTS.md, UI/UX agent skills,
   design tokens, base shell, testing foundation, and visual-QA process required
   by Phase 1.
7. Do not build the complete scanner, stage-control, stage-display, graduate
   administration, queue, Reverb, or audio workflow.
8. Run only the checks required by Phase 1.
9. Open the base shell in a browser and inspect the required viewports.
10. Do not claim success for any command, test, or visual check that was not
    actually performed.
11. Stop after Phase 1 acceptance criteria are satisfied.

Before editing, report:

- current repository status
- approved Phase 0 documents found
- files likely to change
- project and UI-foundation plan
- risks
- tests and viewports to verify
```

---

# 21. Reusable Phase Prompt Template

Use this template after the previous phase is approved.

```text
You are working on the Graduation Stage System described in
GRADUATION_STAGE_SYSTEM_AI_AGENT_DEVELOPMENT_PLAYBOOK.md.

Your assigned task is Phase [NUMBER]: [PHASE NAME].

Rules:

1. Read the entire playbook.
2. Read the approved documents produced by earlier phases.
3. Work only within the assigned phase.
4. Do not implement future-phase features.
5. Preserve the approved architecture:
   - Laravel
   - React
   - Inertia
   - Vite
   - PostgreSQL
   - Reverb
   - Echo
   - local-network-only runtime
6. For a planning phase:
   - do not invent a repository, files, commands, tests, or browser results
   - produce decisions, specifications, assumptions, and acceptance criteria
7. For a development phase:
   - inspect the repository before editing
   - follow existing project conventions
   - read the relevant docs/ui files before frontend changes
   - add or update required automated tests
   - run relevant tests, linting, and builds
   - perform required browser and viewport checks
8. Do not claim success for commands, tests, repository inspection, or visual
   verification that did not occur.
9. Stop after the phase acceptance criteria are satisfied.

Before work, report:

- current phase status
- approved inputs
- files or documents likely to change
- phase steps
- risks
- tests and visual checks, when applicable

After work, report:

- documents or files added
- documents or files modified
- decisions or database changes
- commands executed
- test results
- browser routes, states, and viewports inspected
- manual review steps
- known limitations
- exact recommended next-phase prompt

Do not continue to the next phase.
```

---

# 22. Agent Review Checklist

Before approving a planning phase, the human reviewer should check:

```text
[ ] Agent stayed within phase scope
[ ] Known decisions and assumptions are separated
[ ] No repository, command, test, or browser result was invented
[ ] User roles and workflows are clear
[ ] Failure and recovery scenarios are documented
[ ] UI hierarchy and screen purpose are clear
[ ] Textual wireframes are understandable
[ ] Acceptance criteria are measurable
[ ] MVP and deferred features are separated
[ ] Exact next-phase prompt was provided
```

Before approving a development phase, the human reviewer should check:

```text
[ ] Agent stayed within phase scope
[ ] Architecture was not changed silently
[ ] Relevant requirements and UI documents were followed
[ ] Database changes are documented
[ ] Validation exists
[ ] Authorization exists where required
[ ] Required UI states exist
[ ] Keyboard and accessibility requirements were considered
[ ] Automated tests were added
[ ] Tests were actually run
[ ] Production build still works
[ ] Required routes and viewports were actually inspected
[ ] No generic AI-generated UI patterns were introduced
[ ] No internet dependency was introduced
[ ] Manual verification steps are clear
[ ] Known limitations are honest
[ ] UI changelog was updated for frontend work
[ ] No future-phase feature was partially added without need
```

---

# 23. First Milestone

The first major milestone is not the full admin system.

It is this verified vertical slice:

```text
A QR token is entered on the scanner laptop
        ↓
Laravel validates and records it in PostgreSQL
        ↓
Laravel broadcasts after the transaction commits
        ↓
Reverb transmits the event
        ↓
Echo receives it
        ↓
The stage React page displays the correct graduate
        ↓
The correct local audio plays
        ↓
The stage state remains correct after refresh
```

Do not spend significant effort on advanced reports, visual themes, or optional features until this milestone is stable.

---

# 24. Final Product Readiness Standard

The application is ready for ceremony use only when:

```text
[ ] Works without internet
[ ] Runs across three laptops
[ ] Uses a dedicated local network
[ ] Physical USB QR scanner has been tested
[ ] Valid scans show the correct graduate
[ ] Invalid scans do not alter the stage
[ ] Duplicate scans are controlled
[ ] Stage state survives refresh
[ ] Queue survives reconnection
[ ] Audio does not overlap
[ ] Missing audio is handled safely
[ ] Operator mistakes can be recovered
[ ] Every important action is logged
[ ] PostgreSQL backup is verified
[ ] Restore procedure is verified
[ ] Actual projector is tested
[ ] Actual sound system is tested
[ ] Scanner, stage-control, stage, and administration screens follow approved specifications
[ ] Required loading, empty, success, error, and disconnected states are verified
[ ] Keyboard workflows and visible focus states are verified
[ ] Stage display is readable with long names and long program titles
[ ] No operator controls or technical warnings appear on the audience display
[ ] Required viewport checks are completed
[ ] Final UI/UX review checklist passes
[ ] Full ceremony rehearsal succeeds
```
