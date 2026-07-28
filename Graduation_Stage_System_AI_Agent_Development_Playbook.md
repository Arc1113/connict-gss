# Graduation Stage System
## AI-Agent Development Playbook

> **Purpose:** This file is a phase-by-phase execution plan for AI coding agents such as Codex.  
> **Primary objective:** Build a reliable, local-network-only graduation stage system where scanning a graduate's QR code identifies the graduate, records the event, updates the stage display in real time, and plays a verified local announcement.
>
> **Critical instruction:** Work on **one phase at a time**. Do not continue to the next phase until the current phase passes its acceptance criteria and a human approves the result.

---

# 1. Agent Role

Act as a senior Laravel and React engineer responsible for producing secure, maintainable, testable, and event-ready software.

Prioritize:

1. Correctness
2. Reliability
3. Data integrity
4. Recovery from errors
5. Simple operator experience
6. Clear documentation
7. Maintainable code

Do not prioritize feature quantity over quality.

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

After completing a phase:

1. Run tests.
2. Run linting and formatting.
3. Summarize changed files.
4. Report known limitations.
5. Provide manual verification steps.
6. Stop and wait for approval.

---

# 5. Required Agent Response Format

At the beginning of each phase, the agent must respond with:

```text
Phase:
Current repository status:
Files likely to change:
Implementation plan:
Risks:
Tests to run:
```

At the end of each phase, the agent must respond with:

```text
Phase completed:
Files added:
Files modified:
Database changes:
Tests executed:
Test results:
Manual verification:
Known limitations:
Recommended next phase:
```

The agent must not claim that tests passed unless they were actually executed.

---

# 6. Global Definition of Done

A feature is complete only when:

- The code follows existing project conventions.
- Input validation exists.
- Authorization is enforced where needed.
- Database constraints protect important rules.
- Error states are handled visibly for operators.
- No sensitive values are exposed to the browser.
- Relevant automated tests pass.
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

# 12. Development Phases

---

## Phase 0 — Requirements Freeze and Repository Assessment

### Goal

Create a precise implementation baseline before writing feature code.

### Scope

- Inspect existing repository.
- Confirm runtime requirements.
- Confirm user roles.
- Confirm graduate data fields.
- Confirm stage display requirements.
- Confirm whether automatic and controlled modes are both required.
- Confirm QR distribution process.
- Confirm announcement format.
- Record unresolved assumptions.

### Do not implement

- Database migrations
- UI screens
- Reverb events
- QR generation
- Import features

### Tasks

1. Inspect the repository and development environment.
2. Document the current Laravel, PHP, Node, npm, and PostgreSQL versions.
3. Confirm the project uses React with Inertia.
4. Confirm local-network-only deployment.
5. Create or update:
   - `README.md`
   - `docs/requirements.md`
   - `docs/architecture.md`
6. Write the ceremony workflow.
7. Write the main failure scenarios.
8. Define MVP and later enhancements.

### Deliverables

- Requirements document
- Architecture document
- Repository assessment
- Assumptions list
- Initial risk list

### Acceptance criteria

- No major requirement remains undocumented.
- Architecture matches this playbook.
- MVP boundaries are clear.
- A human approves the documents.

### Stop condition

Stop after documentation and wait for approval.

---

## Phase 1 — Project Bootstrap and Development Environment

### Goal

Create a clean, reproducible Laravel, React, Inertia, Vite, and PostgreSQL foundation.

### Scope

- Laravel application setup
- React and Inertia integration
- PostgreSQL connection
- Environment configuration
- Base formatting and testing tools
- Base layout

### Do not implement

- Graduate management
- QR scanning
- Reverb
- Stage queue
- Audio
- Reports

### Tasks

1. Initialize or verify the Laravel project.
2. Install or verify React and Inertia.
3. Configure Vite.
4. Configure PostgreSQL through environment variables.
5. Create a local development database.
6. Configure application timezone.
7. Add baseline authentication scaffolding if approved.
8. Add code formatting and linting.
9. Confirm tests can run.
10. Create a basic authenticated React page.
11. Create `.env.example` without secrets.
12. Document setup commands.

### Required checks

```text
Laravel starts successfully.
React page renders through Inertia.
PostgreSQL connection succeeds.
Migrations can run.
Frontend assets compile.
Backend tests run.
Frontend linting runs.
```

### Deliverables

- Running project foundation
- PostgreSQL configuration
- React/Inertia page
- Setup documentation
- Environment example file

### Acceptance criteria

- A new developer can follow the README and run the project.
- No runtime dependency requires internet after packages are installed and assets are built.
- Database migrations run successfully.
- Production frontend build succeeds.

### Stop condition

Stop after the project foundation is reproducible.

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
19. Add loading, success, and error states.
20. Add automated tests for scan validation and persistence.

### Acceptance criteria

- A QR token entered on one browser updates the stage browser immediately.
- The scan is stored exactly once.
- Invalid QR tokens do not change the stage.
- Duplicate scans are rejected or handled according to documented rules.
- Stage refresh restores the current graduate.
- Missing Reverb events can be recovered using `GET /stage/state`.
- Audio does not overlap.
- The feature works across separate devices on one local network.

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
14. Document scanner configuration.

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
13. Fix only verified issues.
14. Repeat failed scenarios.
15. Produce an event-readiness report.

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

# 13. Deferred Features

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

# 14. Security and Privacy Requirements

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

# 15. Performance and Reliability Requirements

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

# 16. Coding Quality Requirements

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

# 17. Recommended Laravel Structure

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

# 18. Recommended React Structure

```text
resources/js/
├── Components/
│   ├── StudentCard.jsx
│   ├── ScannerStatus.jsx
│   ├── ConnectionIndicator.jsx
│   ├── QueueItem.jsx
│   └── AudioControls.jsx
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

Follow the actual starter-kit conventions where they differ.

---

# 19. First Agent Prompt

Copy the prompt below into Codex or another coding agent when starting the project.

```text
You are working on the Graduation Stage System described in AGENT_DEVELOPMENT_PLAYBOOK.md.

Your assigned task is Phase 0 only: Requirements Freeze and Repository Assessment.

Rules:
1. Read the complete playbook.
2. Inspect the repository before editing.
3. Do not implement feature code.
4. Document the current repository, environment, requirements, architecture, assumptions, and risks.
5. Create or update README.md, docs/requirements.md, and docs/architecture.md.
6. Do not proceed to Phase 1.
7. At the end, report:
   - files added
   - files modified
   - unresolved assumptions
   - risks
   - manual review checklist
8. Stop and wait for approval.

Before making changes, present:
- current repository status
- proposed documentation plan
- files to change
- risks
```

---

# 20. Reusable Phase Prompt Template

Use this template after the previous phase is approved.

```text
You are working on the Graduation Stage System described in AGENT_DEVELOPMENT_PLAYBOOK.md.

Your assigned task is Phase [NUMBER]: [PHASE NAME].

Rules:
1. Read the entire playbook.
2. Inspect the current repository and completed phase documentation.
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
6. Follow existing project conventions.
7. Add or update automated tests required by this phase.
8. Run relevant tests, linting, and builds.
9. Do not claim success for commands you did not run.
10. Stop after the phase acceptance criteria are satisfied.

Before editing, report:
- current repository status
- phase goal
- files likely to change
- implementation steps
- risks
- tests to run

After implementation, report:
- files added
- files modified
- database changes
- commands executed
- test results
- manual verification steps
- known limitations
- recommended next phase

Do not continue to the next phase.
```

---

# 21. Agent Review Checklist

Before approving a phase, the human reviewer should check:

```text
[ ] Agent stayed within phase scope
[ ] Architecture was not changed silently
[ ] Database changes are documented
[ ] Validation exists
[ ] Authorization exists where required
[ ] Automated tests were added
[ ] Tests were actually run
[ ] Production build still works
[ ] No internet dependency was introduced
[ ] Manual verification steps are clear
[ ] Known limitations are honest
[ ] No future-phase feature was partially added without need
```

---

# 22. First Milestone

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

# 23. Final Product Readiness Standard

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
[ ] Full ceremony rehearsal succeeds
```
