# Graduation Stage System
## Automated Voice Announcement Improvement Plan

> **Purpose:** A phase-by-phase Codex implementation plan for adding pre-generated Azure Speech announcements to the completed Graduation Stage System (GSS).
>
> **Primary rule:** Work on **one enhancement phase at a time**. Stop after each phase, run the required verification, report the results, and wait for human approval.
>
> **Release baseline:** The existing GSS has completed original Phases 0–11 and reached **CONDITIONAL GO**. This enhancement must preserve the verified scanner, persistent queue, stage control, audience display, approved-only local audio, reporting, backup, restore, and local deployment architecture.

---

# 1. Enhancement Objective

Add a preparation-time text-to-speech workflow that generates a ceremonial voice announcement for each graduate.

The announcement may include:s

- Graduate announcement name
- Degree or program
- Major
- Academic honors
- Selected awards
- Other short institution-approved distinctions

Example:

```text
We proudly present Robert John Lirasan,
Bachelor of Science in Computer Science,
Magna Cum Laude,
recipient of the Best in Research Award
and the Leadership Excellence Award.
```

The system must generate the recording **before the ceremony**, save it locally, require human preview and approval, and play only the approved local file during the ceremony.

---

# 2. Non-Negotiable Operational Rule

Do not generate speech live after a QR scan.

Required workflow:

```text
Graduate data prepared
        ↓
Announcement text composed
        ↓
Azure Speech generates a candidate recording
        ↓
Candidate is stored privately and locally
        ↓
Human reviews content and pronunciation
        ↓
Candidate is approved
        ↓
Approved audio becomes the active announcement
        ↓
QR is scanned during the ceremony
        ↓
Approved local audio plays without internet
```

The ceremony runtime must remain independent of Azure and the internet.

---

# 3. Approved Initial Provider

Use **Azure Speech Text to Speech** as the first provider.

Initial voice candidates:

```text
en-PH-JamesNeural
en-PH-RosaNeural
fil-PH-AngeloNeural
fil-PH-BlessicaNeural
```

Recommended initial comparison:

```text
Primary male candidate:   en-PH-JamesNeural
Primary female candidate: en-PH-RosaNeural
Alternative Filipino voices:
fil-PH-AngeloNeural
fil-PH-BlessicaNeural
```

Do not assume one voice will pronounce every Filipino name correctly. Compare voices using fictional and institution-approved test names.

Azure Speech supports SSML controls for voice, pronunciation, pauses, speaking rate, pitch, emphasis, and volume.

The Azure Speech Free F0 tier currently includes 0.5 million neural text-to-speech characters per month. Pricing and quotas may change, so the application must treat this as configurable external-service information rather than a permanent guarantee.

---

# 4. Official Technical References

Use current official documentation during implementation:

- Azure voice and language support  
  `https://learn.microsoft.com/en-us/azure/ai-services/speech-service/language-support`

- Azure Text to Speech REST API  
  `https://learn.microsoft.com/en-us/azure/ai-services/speech-service/rest-text-to-speech`

- Azure SSML overview  
  `https://learn.microsoft.com/en-us/azure/ai-services/speech-service/speech-synthesis-markup`

- Azure SSML voice and sound controls  
  `https://learn.microsoft.com/en-us/azure/ai-services/speech-service/speech-synthesis-markup-voice`

- Azure SSML pronunciation controls  
  `https://learn.microsoft.com/en-us/azure/ai-services/speech-service/speech-synthesis-markup-pronunciation`

- Azure Speech pricing  
  `https://azure.microsoft.com/en-us/pricing/details/speech/`

Do not copy secrets, sample keys, or portal-specific values into source control.

---

# 5. Preserve the Existing GSS Architecture

Continue using:

```text
Laravel backend
React frontend
Inertia.js
Vite production assets
PostgreSQL source of truth
Laravel Reverb
Laravel Echo
HTTP commands and recovery
Persistent stage queue
Approved-only local audio playback
Local-network-only ceremony operation
Docker deployment
Local backup and restore
```

Do not redesign or replace:

- QR scanning
- Queue insertion
- Present Next and Present Selected
- Stage controls
- Audience stage
- Phase 8 audio approval
- Phase 9 backup and restore
- Phase 10 deployment scripts
- Phase 11 readiness workflow

---

# 6. Provider Architecture

Do not tightly couple announcement business logic to Azure.

Create a provider abstraction such as:

```php
interface TextToSpeechProvider
{
    public function synthesize(
        AnnouncementSynthesisRequest $request
    ): GeneratedSpeechResult;
}
```

Initial implementation:

```text
AzureSpeechProvider
```

Potential future providers, not part of this first enhancement:

```text
LocalKokoroProvider
GoogleCloudTextToSpeechProvider
```

Only Azure must be implemented unless another provider is explicitly approved later.

---

# 7. Azure REST Integration

Prefer Laravel's existing HTTP client and Azure's Text to Speech REST API.

Recommended request:

```text
Method:
POST

Content-Type:
application/ssml+xml

Authentication:
Ocp-Apim-Subscription-Key
or an approved bearer-token flow

Output header:
X-Microsoft-OutputFormat

Initial output format:
audio-24khz-48kbitrate-mono-mp3
```

The initial MP3 format is suitable because it is locally storable, compact, and broadly compatible with the approved Chromium stage browser.

Do not add several production formats without a verified operational need.

---

# 8. Configuration and Secret Rules

Add deployment-safe environment values such as:

```text
AZURE_SPEECH_ENABLED=false
AZURE_SPEECH_KEY=
AZURE_SPEECH_REGION=
AZURE_SPEECH_ENDPOINT=
AZURE_SPEECH_DEFAULT_VOICE=en-PH-JamesNeural
AZURE_SPEECH_DEFAULT_LOCALE=en-PH
AZURE_SPEECH_OUTPUT_FORMAT=audio-24khz-48kbitrate-mono-mp3
AZURE_SPEECH_REQUEST_TIMEOUT=30
AZURE_SPEECH_MAX_RETRIES=3
AZURE_SPEECH_MONTHLY_CHARACTER_BUDGET=500000
```

Requirements:

- Real keys exist only in ignored environment files.
- Keys never reach React or Inertia props.
- Keys are never stored in PostgreSQL.
- Keys are never written to logs or audit entries.
- `.env.example` contains blank placeholders only.
- The application fails safely when Azure is disabled or unconfigured.
- Ceremony playback continues when Azure is unavailable.

---

# 9. Proposed Domain Model

## 9.1 Student awards

Create a `student_awards` table or an equivalent approved normalized structure.

Suggested fields:

```text
id
student_id
award_name
spoken_text
display_order
is_announcement_enabled
created_at
updated_at
```

Example:

```text
award_name:
Best in Research

spoken_text:
recipient of the Best in Research Award

display_order:
1

is_announcement_enabled:
true
```

Do not store all awards as one uncontrolled comma-separated field.

## 9.2 Additional approved information

Review whether the student needs an optional field such as:

```text
announcement_additional_text
```

It must be:

- Optional
- Length-limited
- Institution-approved
- Plain text, not raw SSML
- Safely escaped
- Included only when enabled

Do not create an unlimited biography field.

## 9.3 Announcement templates

Create a ceremony-scoped template structure.

Suggested fields:

```text
id
ceremony_id
name
introduction_text
include_program
include_major
include_honors
include_awards
include_additional_text
awards_lead_in
closing_text
default_voice
default_locale
speaking_rate
pause_after_name_ms
pause_between_sections_ms
is_active
version
created_by
updated_by
created_at
updated_at
```

Do not allow administrators to enter arbitrary SSML.

## 9.4 Announcement generations

Create an `announcement_generations` table or equivalent.

Suggested fields:

```text
id
student_id
ceremony_id
announcement_template_id
provider
provider_voice
locale
plain_text
ssml
source_hash
status
character_count
output_format
candidate_audio_path
provider_request_id
attempt_count
generated_by
generated_at
reviewed_by
reviewed_at
review_notes
failure_code
failure_message
created_at
updated_at
```

Recommended statuses:

```text
draft
pending
generating
generated
needs_review
approved
rejected
failed
outdated
cancelled
```

Candidate files must remain separate from the student's active audio until approval.

---

# 10. Source Fingerprint and Stale-Audio Protection

Build a deterministic source fingerprint from:

```text
announcement_name
TTS spoken-name override
program
major
honors
enabled awards
award spoken text
award order
additional text
template version
voice
locale
speaking rate
pause configuration
```

Use SHA-256 or another approved cryptographic hash.

```text
source_hash = SHA256(canonical_announcement_source)
```

At generation:

```text
Store the source hash with the candidate.
```

At approval:

```text
Recalculate the source hash.
Reject approval when the source has changed.
```

At stage-payload creation:

```text
Expose generated audio only when:
- readiness is approved
- file is readable
- active audio source is Azure TTS
- stored source hash matches the current source
```

This source-hash check is required even when automatic invalidation is implemented.

---

# 11. Existing Audio Workflow Integration

Preserve manual audio upload.

Supported active-audio sources:

```text
manual_upload
azure_tts
```

Add only the minimum required student metadata, such as:

```text
audio_source_type
audio_generation_id
audio_source_hash
```

Approval of an Azure candidate must:

1. Reauthorize the reviewer.
2. Lock the student and generation rows.
3. Recalculate the source hash.
4. Confirm the candidate file exists and is readable.
5. Use the existing safe media replacement service.
6. Promote or copy the candidate to the active managed-audio location.
7. Set the audio source to `azure_tts`.
8. Set readiness to approved.
9. Save reviewer and approval timestamp.
10. Create safe audit history.
11. Commit.
12. Remove superseded managed files only after commit.

Manual upload must remain able to supersede Azure-generated audio.

---

# 12. Announcement Composition Rules

Use a deterministic composer with this order:

```text
Introduction
Graduate announcement name
Degree or program
Major where approved
Academic honors
Selected awards
Additional approved distinction
Closing
```

Example source:

```text
Announcement name:
Robert John Lirasan

Program:
Bachelor of Science in Computer Science

Honors:
Magna Cum Laude

Awards:
Best in Research
Leadership Excellence Award
```

Example output:

```text
We proudly present Robert John Lirasan,
Bachelor of Science in Computer Science,
Magna Cum Laude,
recipient of the Best in Research Award
and the Leadership Excellence Award.
```

---

# 13. Natural Award-List Rules

## No award

```text
Robert John Lirasan,
Bachelor of Science in Computer Science.
```

## One award

```text
Robert John Lirasan,
Bachelor of Science in Computer Science,
recipient of the Best in Research Award.
```

## Two awards

```text
Robert John Lirasan,
Bachelor of Science in Computer Science,
recipient of the Best in Research Award
and the Leadership Excellence Award.
```

## Three or more awards

```text
recipient of the Best in Research Award,
the Leadership Excellence Award,
and the Outstanding Capstone Award.
```

Requirements:

- No dangling punctuation.
- No duplicated “and”.
- No spoken empty fields.
- No repeated award.
- Deterministic order.
- Unicode preserved.
- Long announcements produce warnings.

---

# 14. Announcement Length Policy

Use warning thresholds rather than silent truncation.

Initial proposal:

```text
Recommended:
80–450 characters

Warning:
451–700 characters

Blocking review threshold:
More than 700 characters unless an administrator override is approved
```

The exact limits require institutional approval.

Show:

- Character count
- Approximate speaking-time estimate, clearly labeled as an estimate
- Number of awards
- Configured Azure budget impact

Do not claim a precise audio duration from character count alone.

---

# 15. Pronunciation Controls

Preserve:

```text
announcement_name
pronunciation_guide
```

Add an optional TTS-specific plain-text field if required:

```text
tts_spoken_name
```

Example:

```text
Display:
Robert Jhon Aracena

TTS spoken name:
Robert John Ara-seh-na
```

Requirements:

- Plain text only
- Optional
- Clearly labeled
- Changing it invalidates generated audio
- Visible beside the human pronunciation guide during review

Do not automatically treat a human pronunciation note as valid IPA.

---

# 16. Safe SSML Builder

Create a dedicated class such as:

```text
BuildGraduationAnnouncementSsml
```

The builder must:

- Escape all dynamic content.
- Produce valid XML.
- Use an allowlisted voice.
- Use the correct locale.
- Apply restrained pauses.
- Apply a restrained speaking rate.
- Prevent raw SSML injection.
- Preserve readable fallback text.
- Produce deterministic output.

Example structure:

```xml
<speak version="1.0"
       xmlns="http://www.w3.org/2001/10/synthesis"
       xml:lang="en-PH">
    <voice name="en-PH-JamesNeural">
        <prosody rate="-5%">
            We proudly present
            <break time="250ms"/>
            Robert John Lirasan,
            <break time="200ms"/>
            Bachelor of Science in Computer Science,
            <break time="160ms"/>
            Magna Cum Laude,
            <break time="160ms"/>
            recipient of the Best in Research Award
            and the Leadership Excellence Award.
        </prosody>
    </voice>
</speak>
```

Use safe XML-building or correctly escaped templates.

---

# 17. Azure Request Handling

The Azure provider must include:

- Configurable endpoint and region
- Configurable voice and output format
- Connection timeout
- Request timeout
- Bounded retries
- Exponential backoff with jitter
- Safe error mapping
- Response-size checks
- Audio content validation
- Atomic local file writes

Retry transient failures only:

```text
429
502
503
network timeout
temporary connection failure
```

Do not repeatedly retry permanent failures:

```text
400 invalid request or SSML
401 authentication failure
403 forbidden
unsupported voice
invalid configuration
```

---

# 18. Candidate Audio Storage

Store generated candidates outside public directories until approved.

Suggested pattern:

```text
storage/app/private/announcement-candidates/{ceremony_uuid}/{generation_uuid}.mp3
```

Requirements:

- No student name in filename.
- No student number in filename.
- No key or endpoint data in path.
- Collision-resistant identifiers.
- Authorized preview route.
- Failed partial files cleaned up.
- Candidate retention policy documented.
- Active approved audio remains in the existing managed media system.

---

# 19. Character Usage and Cost Control

Store per generation:

```text
character_count
provider
voice
generated_at
result
```

Show monthly estimates:

```text
Successful generated characters
Number of generation requests
Configured monthly budget
Estimated remaining budget
```

Rules:

- Azure remains the authoritative billing source.
- Local totals are estimates only.
- Warn near the configured budget.
- Require confirmation for Force Regenerate.
- Reuse identical successful candidates when allowed.
- Do not call Azure on page load.
- Do not generate after every keystroke.

---

# 20. Single-Graduate UI

Add a focused section to the graduate detail page.

Display:

- Announcement name
- Program
- Major
- Honors
- Enabled awards
- Additional approved information
- Template
- Voice
- Plain-text preview
- Character count
- Current active audio source
- Audio readiness
- Generation history

Actions:

```text
Preview announcement
Generate candidate
Compare voice
Preview candidate
Reject candidate
Approve candidate
Regenerate
Use manual audio instead
```

Do not combine Generate and Approve.

---

# 21. Awards Management UI

Required actions:

- Add award
- Edit award
- Remove award where safe
- Move award up or down
- Set spoken phrase
- Enable or disable spoken announcement
- Preview awards sentence
- Detect duplicates
- Invalidate stale generated audio after meaningful changes

Prefer accessible move-up/move-down controls over drag-and-drop unless drag-and-drop is clearly justified and keyboard accessible.

---

# 22. Ceremony Announcement Settings

Allow authorized users to configure:

- Default template
- Default voice
- Locale
- Speaking rate within safe limits
- Pause after graduate name
- Pause between sections
- Include program
- Include major
- Include honors
- Include awards
- Include additional information
- Recommended announcement-length limit

Requirements:

- Preview with fictional data.
- Template changes increment a version.
- Source hashes make existing generations outdated.
- Users never edit raw SSML.

---

# 23. Voice Comparison

Allow an administrator to generate temporary samples using approved voices.

Requirements:

- Use a fictional sample.
- Explicit action only.
- Show character usage before generation.
- Store samples temporarily.
- Never make a sample active automatically.
- Let the reviewer choose a ceremony default.
- Do not treat a pleasant voice as proof of correct pronunciation.

---

# 24. Batch Generation

Add batch generation only after single-graduate generation is stable.

Filters may include:

- Ceremony
- Missing active audio
- Outdated generated audio
- Needs review
- College
- Program
- Sequence range
- Selected students

Requirements:

- Server-side authorization.
- Bounded batch size.
- Deterministic order.
- Do not regenerate current identical approved audio without explicit instruction.
- Do not bulk approve.
- Display progress and safe failure summaries.
- Resume after browser refresh where applicable.

---

# 25. Batch Processing Strategy

Use Laravel's database queue only when necessary for safe long-running generation.

If implemented:

- Use a preparation-only queue.
- Document the worker command.
- Ceremony runtime must not depend on the TTS worker.
- Use low bounded concurrency.
- Add retry and backoff limits.
- Make jobs idempotent.
- Prevent duplicate generation for the same source hash.
- Support cancellation of pending jobs.
- Store progress in PostgreSQL.

Do not use a browser loop as the authoritative batch processor.

---

# 26. Generation Idempotency

Use an idempotency key based on:

```text
student_id
source_hash
provider
voice
output_format
```

Before calling Azure:

1. Check for an identical successful candidate.
2. Reuse it when permitted.
3. Require explicit Force Regenerate for another request.
4. Record the decision safely.

This prevents unnecessary Azure usage and repeated charges.

---

# 27. Approval and Rejection

## Approval

1. Authorize reviewer.
2. Lock generation and student.
3. Confirm reviewable status.
4. Recalculate source hash.
5. Reject stale candidate.
6. Confirm file readability and type.
7. Promote through existing media storage.
8. Mark active source as Azure TTS.
9. Mark readiness approved.
10. Audit safely.
11. Commit.
12. Clean superseded files after commit.

## Rejection

- Require authorization.
- Save safe review notes.
- Mark candidate rejected.
- Preserve existing valid active audio.
- Do not expose provider details to audience UI.
- Audit safely.

---

# 28. Automatic Invalidation

Changes to these values must invalidate generated approval or make the source hash stale:

```text
announcement name
TTS spoken name
program
major
honors
award values
award order
award spoken text
award enablement
additional announcement text
template and template version
voice
locale
speaking rate
pause configuration
```

At minimum, the stage payload must refuse stale generated audio.

Manual uploaded audio should not be invalidated automatically unless the institution explicitly decides that edited student information makes the recording inaccurate.

---

# 29. Stage Playback Integration

Never call Azure while scanning, presenting, replaying, or recovering state.

```text
Approved active audio exists
and file is readable
and generated source hash is current
        ↓
Expose same-origin local audio URL
        ↓
Preload and play locally
```

Otherwise:

```text
Do not play stale or unapproved audio
Show “Live announcer required” to the stage operator
Keep the audience screen clean
```

Do not block visual presentation solely because audio is unavailable.

---

# 30. Offline Ceremony Guarantee

Before ceremony deployment:

- Approved files exist locally.
- Generated audio is included in backup.
- Stage URLs are same-origin.
- Azure may be disabled.
- Internet may be disconnected.
- Queue, stage, audio, logs, reports, and recovery continue working.

Deployment health must classify:

```text
Core ceremony services: critical
Azure generation service: preparation-only
```

Azure being unreachable must not cause the ceremony health check to fail.

---

# 31. Roles

## Administrator

May:

- Manage templates and ceremony voice settings
- Generate individual and batch candidates
- Run voice comparison
- Approve and reject candidates
- Force regeneration
- View usage estimates and safe generation history

## Data encoder

May:

- Manage awards and spoken phrases
- Edit announcement and pronunciation fields
- Preview text
- Generate and preview candidates where approved
- Mark candidates as needing review

Must not approve by default.

## Stage operator

May:

- See operational audio readiness
- Replay approved local audio
- See human-announcer fallback

Must not call Azure or manage templates.

## Scanner and technical operators

No TTS content-management access unless explicitly approved.

All permissions must be enforced by Laravel.

---

# 32. Audit and Error Privacy

Audit safe events such as:

```text
award created or updated
award reordered
announcement template updated
generation requested
generation succeeded or failed
candidate approved or rejected
generated audio became outdated
batch started or completed
default voice changed
budget override confirmed
```

Never audit:

- Azure key
- Authorization header
- Raw binary audio
- Full provider response
- Private candidate path
- QR token
- Session data

Map provider failures to safe codes:

```text
azure_not_configured
azure_authentication_failed
azure_rate_limited
azure_invalid_ssml
azure_voice_unavailable
azure_timeout
azure_service_unavailable
candidate_storage_failed
candidate_validation_failed
```

---

# 33. Backup, Restore, and Deployment Updates

Update the backup scope to include:

- Student awards
- Announcement templates
- Generation records
- Approved generated audio
- Candidate audio according to retention policy
- Usage estimates
- Audit history

Disposable restore verification must check:

- Award counts
- Template records
- Generation records
- Active audio references
- Source hashes
- Media checksums

Do not back up Azure credentials.

Document two operating modes:

## Preparation mode

```text
Azure enabled
Internet available
Authorized preparation users
TTS queue worker running if batch jobs are enabled
```

## Ceremony mode

```text
Azure may be disabled
Internet not required
TTS worker not required
Approved local audio only
Human announcer ready
```

---

# 34. Enhancement Phases

## ENH-TTS-0 — Requirements and Design Freeze

### Goal

Finalize content, roles, schema, integration, and safety decisions before code changes.

### Tasks

1. Inspect the completed GSS repository.
2. Review existing student audio fields and Phase 8 approval workflow.
3. Review Phase 9 backups and Phase 10 deployment.
4. Confirm the awards data currently available.
5. Define the initial announcement template.
6. Define fields that may be spoken.
7. Define initial voice candidates.
8. Define reviewer roles.
9. Define length and budget warnings.
10. Document Azure as preparation-only.
11. Create:
    - `docs/automated-announcements.md`
    - `docs/azure-speech-setup.md`
    - `docs/announcement-content-policy.md`

### Do not implement

- Migrations
- Azure calls
- Awards UI
- Generation UI
- Batch jobs

### Acceptance criteria

- Content policy approved.
- Proposed schema documented.
- Source-hash design documented.
- Offline guarantee documented.
- Human approval received.

### Stop condition

Stop and wait for approval.

---

## ENH-TTS-1 — Awards and Announcement Domain Model

### Goal

Add structured awards, templates, generation history, and source fingerprints.

### Scope

- PostgreSQL migrations
- Enums
- Models and relationships
- Policies
- Factories and fictional seeds
- Tests

### Required tests

- Award ordering and enablement
- Duplicate prevention
- Template relationships
- Generation-status casts
- Source-hash stability
- Source-hash change detection
- Foreign-key behavior
- Role authorization

### Acceptance criteria

- Clean migration and rollback pass.
- Full existing GSS suite passes.
- No ceremony behavior changes.

### Stop condition

Stop and wait for approval.

---

## ENH-TTS-2 — Announcement Composer and SSML Builder

### Goal

Generate safe deterministic plain text and SSML without calling Azure.

### Tasks

- Implement announcement composer.
- Implement natural award-list formatting.
- Handle missing optional fields.
- Implement length warnings.
- Implement TTS spoken-name override.
- Implement safe SSML builder.
- Escape all content.
- Block raw SSML injection.
- Generate source hash.
- Add preview serializer.

### Required tests

- No, one, two, and many awards
- Disabled and reordered awards
- Unicode names
- Long programs and honors
- Missing optional fields
- Spoken-name override
- XML escaping
- SSML injection attempt
- Deterministic output
- Length warning

### Stop condition

Stop and wait for approval.

---

## ENH-TTS-3 — Azure Provider Integration

### Goal

Generate one private candidate MP3 through Azure Speech.

### Tasks

- Add safe environment configuration.
- Create provider interface.
- Implement Azure provider.
- Add timeout, retry, and error mapping.
- Validate audio response.
- Write candidate file atomically.
- Track character use.
- Add explicit provider health check.
- Add mocked tests and controlled integration instructions.

### Required tests

- Missing configuration
- Authentication failure
- Invalid SSML
- Timeout
- Rate limit
- Server error retry
- Successful MP3
- Wrong content type
- Empty or oversized response
- Partial write failure
- Secret redaction
- Identical-request reuse

### Acceptance criteria

- Fictional test candidate generated privately.
- No key exposure.
- No automatic active-audio change.
- Ceremony works with Azure disabled.

### Stop condition

Stop and wait for approval.

---

## ENH-TTS-4 — Awards and Single-Graduate Generation UI

### Goal

Allow authorized users to manage awards, preview announcement text, and generate one candidate.

### Tasks

- Add awards editor.
- Add template and voice selection.
- Add plain-text preview.
- Show length and character use.
- Add Generate Candidate.
- Add protected candidate preview.
- Add generation history.
- Add error and duplicate-submit protection.

### Acceptance criteria

- Candidate remains separate from active audio.
- Manual upload remains available.
- No automatic approval.
- Role restrictions pass.

### Stop condition

Stop and wait for approval.

---

## ENH-TTS-5 — Review, Approval, Rejection, and Invalidation

### Goal

Integrate generated candidates with the verified Phase 8 approval system.

### Tasks

- Implement candidate approval and rejection.
- Revalidate source hash.
- Promote audio safely.
- Record active source type.
- Add audit history.
- Add automatic invalidation.
- Enforce source-hash check in stage payload.
- Update backup behavior.

### Required tests

- Approve and reject
- Unauthorized approval
- Stale candidate rejection
- Award, honors, template, and voice changes invalidate
- Failed promotion preserves active audio
- Manual audio supersedes Azure
- Stage excludes stale audio
- Audit excludes secrets and private paths

### Stop condition

Stop and wait for approval.

---

## ENH-TTS-6 — Ceremony Settings and Voice Comparison

### Goal

Allow approved selection of a ceremony template, pacing, and voice.

### Tasks

- Add ceremony announcement settings.
- Add voice allowlist.
- Add safe speaking-rate and pause limits.
- Add fictional sample preview.
- Add controlled voice comparison.
- Version templates.
- Mark source hashes stale after changes.

### Stop condition

Stop and wait for approval.

---

## ENH-TTS-7 — Batch Candidate Generation

### Goal

Generate candidates for many graduates in a bounded, resumable preparation workflow.

### Requirements

- Server-side selection validation
- Bounded batch size
- Deterministic order
- Preparation-only database queue when justified
- Low concurrency
- Retry limits
- Idempotent jobs
- Cancellation
- Progress persistence
- Budget warning
- No automatic approval
- Ceremony independent of TTS worker

### Stop condition

Stop and wait for approval.

---

## ENH-TTS-8 — Full Regression and Offline Rehearsal

### Goal

Prove the enhancement is preparation-safe and ceremony-independent.

### Required scenarios

1. No-award announcement.
2. One award.
3. Multiple awards.
4. Long award list.
5. Difficult Filipino name.
6. Spoken-name correction.
7. Voice comparison.
8. Generate, reject, regenerate, and approve.
9. Change an award and verify stale status.
10. Manual audio replacement.
11. Batch generation.
12. Azure outage and invalid key.
13. Rate-limit response.
14. Internet disconnected during ceremony deployment.
15. QR scan and local approved playback.
16. Stale audio refused.
17. Human-announcer fallback.
18. Backup and disposable restore.
19. Complete original Phase 3–11 regression.

### Final status

Use:

```text
GO
CONDITIONAL GO
NO-GO
```

### Stop condition

Stop after readiness sign-off.

---

# 35. Required Verification Standards

All database tests must use PostgreSQL.

Run as applicable:

```text
Clean migrations
Rollback and reapplication
Full Laravel suite
Focused enhancement tests
Concurrency tests
Laravel Pint
ESLint
Vite production build
Composer validation and audit
npm audit
Docker Compose validation
Deployment Compose validation
Browser verification
Offline ceremony verification
Backup creation
Disposable restore
Git whitespace check
Secret scan
Azure-key exposure scan
Private-path exposure scan
Remote-runtime-asset scan
```

Do not report a check as passed unless it was executed.

---

# 36. Critical Security Tests

Verify Azure secrets do not appear in:

- React props
- JavaScript bundles
- HTML
- Browser responses
- Logs
- Audit records
- Error pages
- Backups
- Git history
- Screenshots

Verify private candidates cannot be accessed by:

- Guests
- Scanner operators
- Stage operators
- Unrelated users
- Users guessing UUIDs

---

# 37. Critical Data-Integrity Tests

Test:

- Concurrent identical generation requests
- Approval while award is edited
- Approval while template changes
- Manual upload racing with candidate approval
- Batch and single generation race
- Azure timeout after response begins
- File-write failure
- Database rollback after file creation
- Backup during generation
- Restore of approved generated audio
- Stage payload immediately after a source change

---

# 38. Definition of Done

```text
[ ] Awards are structured and ordered
[ ] Announcement text is deterministic
[ ] SSML is safely generated
[ ] Azure key remains server-only
[ ] Single-candidate generation works
[ ] Candidate audio is private
[ ] Human review is mandatory
[ ] Approval promotes audio safely
[ ] Rejection works
[ ] Source changes invalidate generated approval
[ ] Stage source-hash protection works
[ ] Manual upload remains supported
[ ] Batch generation is bounded
[ ] Usage estimate exists
[ ] Azure outage does not affect ceremony
[ ] Ceremony works without internet
[ ] Backup includes generated audio and metadata
[ ] Disposable restore succeeds
[ ] Complete existing GSS regression passes
```

---

# 39. First Prompt for Codex

Copy this prompt after placing this plan in the project repository.

```text
You are working on the completed Graduation Stage System.

Read the complete file:

GSS_AUTOMATED_VOICE_ANNOUNCEMENT_IMPROVEMENT_PLAN.md

The current GSS completed original Phases 0–11 and reached CONDITIONAL GO.
Do not redesign or weaken the verified scanner, persistent queue, stage control,
audience display, local-audio approval, backup, restore, or deployment architecture.

Your assigned task is:

ENH-TTS-0 — Requirements and Design Freeze only.

Do not begin ENH-TTS-1.

Required actions:

1. Inspect the repository and current Git status.
2. Read:
   - README.md
   - docs/requirements.md
   - docs/architecture.md
   - docs/audio-preparation.md
   - docs/backup-and-restore.md
   - docs/operational-recovery.md
   - docs/deployment.md
3. Inspect existing Student audio fields, audio readiness workflow, media storage,
   policies, audit logging, stage payload, backup scripts, and deployment scripts.
4. Identify exact integration points for:
   - student awards
   - announcement templates
   - generation candidates
   - source hashes
   - Azure configuration
   - candidate approval promotion
   - stale-audio protection
5. Do not create migrations, install packages, call Azure, or change runtime code.
6. Create or update only:
   - docs/automated-announcements.md
   - docs/azure-speech-setup.md
   - docs/announcement-content-policy.md
7. Document:
   - proposed schema
   - announcement sentence rules
   - award inclusion rules
   - role permissions
   - provider abstraction
   - Azure REST design
   - secret handling
   - source fingerprint design
   - approval and invalidation rules
   - offline ceremony guarantee
   - cost-control strategy
   - testing strategy
   - unresolved decisions
8. Preserve manual audio upload.
9. Azure must remain preparation-only.
10. Stop after ENH-TTS-0 and wait for approval.

Before editing, report:

- Current Git status
- Current release baseline
- Existing audio architecture
- Existing student fields
- Existing media replacement behavior
- Existing approval behavior
- Existing stage audio policy
- Existing backup behavior
- Proposed integration points
- Proposed documents
- Risks
- Unresolved decisions
- Verification plan

At completion, report:

- Enhancement phase completed
- Files created
- Files modified
- Architecture decisions
- Proposed schema
- Proposed roles
- Announcement-content rules
- Awards rules
- Azure integration design
- Source-hash design
- Approval and invalidation design
- Offline behavior
- Risks
- Unresolved decisions
- Manual review checklist
- Recommended next enhancement phase

Do not proceed to ENH-TTS-1.
```

---

# 40. Reusable Enhancement Prompt

```text
You are working on the completed Graduation Stage System and the approved
GSS_AUTOMATED_VOICE_ANNOUNCEMENT_IMPROVEMENT_PLAN.md.

Your assigned task is:

ENH-TTS-[NUMBER] — [PHASE NAME] only.

Rules:

1. Read the complete improvement plan.
2. Inspect the repository and previous enhancement documentation.
3. Work only inside the assigned enhancement phase.
4. Preserve the verified original GSS architecture.
5. Azure generation is preparation-only.
6. Ceremony playback remains local and offline.
7. Manual audio upload remains supported.
8. Generated candidates are never auto-approved.
9. Azure keys remain server-only.
10. Add PostgreSQL-backed tests.
11. Run relevant original-system regressions.
12. Do not claim commands passed unless executed.
13. Stop after the assigned phase.

Before editing, report:

- Current Git status
- Previous enhancement commit
- Current architecture
- Phase goal
- Files likely to change
- Schema changes
- Provider changes
- Authorization plan
- Security risks
- Data-integrity risks
- Tests
- Browser verification
- Rollback plan

After implementation, report:

- Phase completed
- Files created
- Files modified
- Packages installed
- Routes added
- Schema changes
- Access rules
- Provider behavior
- Announcement behavior
- Source-hash behavior
- Approval behavior
- Failure handling
- Commands executed
- Test results
- Original GSS regression results
- Security checks
- Browser verification
- Known limitations
- Recommended next enhancement phase

Do not proceed to the next phase.
```

---

# 41. Final Engineering Principle

The improvement is not successful merely because Azure produces speech.

It is successful only when:

```text
The correct graduate information
is converted into a well-formed ceremonial announcement,
reviewed by a human,
stored locally,
protected from becoming stale,
and played reliably without internet on event day.
```
