# Automated Voice Announcements

Status: **ENH-TTS-6 ceremony settings and controlled voice comparison implemented**

Last reviewed: 2026-08-04

This document defines the approved architecture for preparation-time automated
graduate announcements. ENH-TTS-1 supplies the PostgreSQL domain foundation.
ENH-TTS-2 adds immutable composition inputs/results, deterministic plain text,
safe SSML, and a side-effect-free backend preview service. ENH-TTS-3 adds a
disabled-by-default Azure provider, private atomic candidate storage, generation
records, safe health results, usage estimates, and identical-candidate reuse.
ENH-TTS-4 adds authorized award editing, deterministic preview, individual
generation, private candidate playback, and safe history on the graduate detail
page. ENH-TTS-5 adds administrator-only review, compensated local promotion,
automatic source invalidation, stage-time hash validation, and private-candidate
backup coverage without adding a ceremony-time cloud dependency. ENH-TTS-6 adds
administrator-managed versioned ceremony settings, bounded fictional previews,
and explicit temporary voice comparison without using graduate records.

## Implemented ENH-TTS-6 settings and voice comparison

Administrators can publish a new immutable ceremony-template version from the
ceremony settings workspace. Voice and locale must be an approved pair from the
server allowlist; speaking rate, pauses, and recommended/maximum character
limits are bounded server-side. Publishing deactivates the prior version,
preserves its history, and marks candidates built from earlier settings stale.

Draft preview uses one fixed fictional graduate and the same deterministic
composer and SSML builder as candidate generation. It writes no template or
generation and never calls Azure. Voice comparison is a separate explicit
command for one to four distinct allowlisted voices. It shows a character
estimate first, requires a configured provider, and stores validated MP3s in a
separate private temporary area with protected no-store playback and expiry.
Expired sample rows and files are pruned on the next comparison command.

Comparison samples cannot be promoted as graduate audio and never use live
graduate data. Their private paths, SSML, credentials, and provider bodies are
not serialized or audited. Temporary sample files and row data are excluded
from backups; their successful character counts remain part of the local
monthly usage estimate.

## Implemented ENH-TTS-5 review and runtime guard

Only an administrator may approve or reject a `needs_review` candidate. Approval
locks the student and generation, rebuilds the announcement with the candidate's
same-ceremony template and allowlisted voice, and compares the current source
hash before reading or promoting the private MP3. A stale attempt is committed
as `outdated` and returns validation feedback.

Successful approval writes a uniquely named managed MP3 through a temporary
same-disk path, then records the Azure source type, generation reference, source
hash, reviewer, readiness, and safe audit metadata transactionally. A file or
database failure removes the new file and preserves the prior active audio.
Rejection requires a note and never changes active audio. A later manual upload
clears the generated source reference and remains the explicit override.

Student spoken fields, structured awards, and template/voice changes invalidate
affected reviewable or approved generations. Active generated audio becomes
`needs_review`. `AnnouncementAudio` independently rebuilds and compares the
current hash before stage, preload, or replay URLs are serialized, so stale or
unreadable generated media falls back to the live announcer.

Backups now include private candidates under a non-public media subtree with
the existing per-file checksums. Credentials, SSML, provider bodies, and private
database paths remain absent from browser props and review audits.

## Objective and non-negotiable boundary

An authorized preparation user will eventually compose a deterministic
ceremonial announcement, generate a private candidate through Azure Speech,
preview it, review its content and pronunciation, and explicitly approve it.
Approval will promote a local copy into the existing managed graduate-audio
workflow.

```text
Reviewed graduate data
    -> deterministic plain text and safe SSML
    -> explicit preparation-time Azure request
    -> private local candidate
    -> human content/pronunciation/quality review
    -> explicit approval and local promotion
    -> existing approved-only local stage playback
```

Azure is a preparation-only dependency. Scanning, queue insertion, stage
commands, replay, browser recovery, and the audience display must never call or
wait for Azure. The ceremony remains operable with Azure disabled, the TTS
worker stopped, and the internet disconnected.

Cloud TTS is prohibited during ceremony runtime. Azure Speech is permitted only
during authorized preparation, with generated files reviewed, approved, and
stored locally before ceremony. ENH-TTS-3 makes the preparation-time network
exception executable only when an authorized backend caller explicitly starts
generation and safe server configuration enables Azure.

## Implemented ENH-TTS-4 preparation workspace

The existing graduate detail page now receives a server-built announcement
workspace. It shows approved source fields, ordered awards, same-ceremony
template versions, allowlisted voices, deterministic plain text, character and
award counts, approximate-duration labeling, length warnings, provider
configuration status, safe generation history, and administrator-only monthly
usage estimates. TTS spoken-name and additional approved distinction fields are
editable through the existing graduate form.

Data encoders and administrators may add, edit, enable/disable, remove, and use
keyboard-accessible move-up/move-down controls for structured awards. Award
writes lock the student, normalize order transactionally, rely on the PostgreSQL
normalized duplicate constraint, and record safe audits without candidate paths.
Preview accepts only a template from the graduate's ceremony and a configured
voice/locale pair; it makes no provider request.

Generation is an explicit POST and the UI disables repeated submission while a
request is active. The backend reuses an identical readable candidate and now
also blocks a second identical `pending`/`generating` request. Administrator
force regeneration requires confirmation. Provider failures return only the
safe mapped message.

Candidate audio is served inline only from an authenticated, authorized nested
student/generation route. The route confirms ownership, validates the private
path and MP3 again, uses `private, no-store` caching, and exposes a generic
filename rather than the stored path. No candidate path, key, SSML, provider
response, or authorization header enters Inertia props.

The workspace has no Approve or Reject Candidate action. Candidate status stays
`needs_review`; active/manual audio and stage readiness remain untouched.

## Implemented ENH-TTS-3 provider and candidate boundary

`TextToSpeechProvider` decouples the generation action from Azure.
`AzureSpeechProvider` uses Laravel's HTTP client with no redirects, the
subscription-key header, `application/ssml+xml`, the approved MP3 output header,
and a bounded user agent. The endpoint is either derived from a validated Azure
region or constrained to HTTPS Azure Speech host suffixes with no user info,
query, fragment, or non-443 port.

The provider retries only connection failures, timeouts, HTTP 429, 502, and 503,
up to three retries after the initial request. Backoff is bounded exponential
delay with jitter; a small bounded integer `Retry-After` is honored. HTTP
400/401/403 and unexpected responses do not retry. Upstream bodies, credentials,
headers, endpoint values, SSML, names, and paths never enter stored failure
messages.

A successful response must be HTTP 200, `audio/mpeg` or `audio/mp3`, non-empty,
at most 15 MiB, and begin with an ID3 header or MPEG frame sync. The local disk
is rooted at `storage/app/private`. Candidate filenames contain two random UUIDs
and no graduate data. Bytes are written to a mode-0600 temporary file on the
same filesystem, flushed/synchronized, atomically renamed, and removed on every
failed promotion. Candidate paths remain hidden by the generation model.

`GenerateAnnouncementCandidate` authorizes the actor, validates local provider
configuration, locks the student for source assembly, persists an immutable
`pending`/`generating` snapshot, synthesizes outside the transaction, stores the
candidate, then commits `needs_review`. Provider/storage failures persist only
allowlisted safe codes. An identical readable `needs_review` candidate is reused
without another provider call. Only an administrator may explicitly force a
new request. Generation never changes the student's active audio, readiness,
stage state, or audience payload.

`AnnouncementUsage` sums successful generated characters for a calendar month
from PostgreSQL and compares the local estimate with the configured budget and
warning point. Reuse is not counted twice. This is not an invoice or Azure quota
balance.

## Implemented ENH-TTS-2 composition boundary

`PreviewGraduationAnnouncement` resolves the ceremony's active template and
loads awards in `display_order`, then primary-key order. It maps only approved
announcement fields into `AnnouncementCompositionInput`; student numbers, QR
tokens, contact data, timestamps, media paths, credentials, and review notes
cannot enter the DTO. Preview creates no `AnnouncementGeneration`, performs no
HTTP request or broadcast, writes no file, and changes no student or stage
state.

`ComposeGraduationAnnouncement` normalizes surrounding and repeated whitespace,
preserves Unicode, and builds non-empty sections in this fixed order:
introduction, spoken name, program, major, honors, enabled awards, additional
distinction, closing. `tts_spoken_name` has priority over `announcement_name`;
whitespace-only values are absent, and no display-name inference occurs. A
missing name blocks. An enabled missing program blocks. Major and honors are
omitted with a warning only when Unicode case-folded, punctuation-normalized
whole-phrase comparison safely detects equality or containment; similar but
distinct wording remains.

Awards use non-empty `spoken_text`, otherwise `award_name`. Disabled awards are
not spoken. The dedicated formatter rejects empty or duplicate effective
phrases, raw SSML, placeholders, leading separators/conjunctions, and wording
that repeats the template lead-in. It emits no clause for zero awards, `and`
for two, and an Oxford comma for three or more. The template lead-in is emitted
once.

Plain text uses one space between comma-separated sections and exactly one
terminal punctuation mark. It is never truncated. Unicode-safe length status
is `normal` through the configured recommended limit, `warning` above that
through maximum, and `blocking` above maximum. Defaults remain 450 and 700.
The deterministic duration range counts Unicode words, assumes configurable
145–165 words per minute, adjusts by the validated integer speaking rate, and
adds emitted section pauses. It is explicitly approximate; Azure output may
differ.

`BuildGraduationAnnouncementSsml` consumes only a ready composed result. PHP's
`XMLWriter` creates the Azure synthesis namespace, `version="1.0"`, controlled
`xml:lang`, one allowlisted voice, an integer percentage prosody rate, and
integer-millisecond breaks. Dynamic text is passed only to `XMLWriter::text`,
so XML characters are escaped and cannot become elements or attributes. Known
raw SSML markup blocks composition. No style, role, emotion, phoneme, audio, or
arbitrary attribute is supported.

The server allowlist is `config/announcements.php`: James/Rosa pair with
`en-PH`; Angelo/Blessica pair with `fil-PH`. Speaking rate is limited to
-20% through +10%, and each pause to 0–1500 ms. The fingerprint now includes
`composition_schema_version: 1`; normalized source values, every ordered award
record and enablement flag, template settings, voice, locale, rate, pauses, and
output format determine the hash.

## Existing audio architecture to preserve

The inspected Phase 12 repository currently provides:

- PostgreSQL-backed student fields for `announcement_name`,
  `pronunciation_guide`, program, optional major, honors, and active audio
  metadata;
- active-audio readiness values `missing`, `uploaded`, `needs_review`,
  `approved`, and `rejected` in `AudioReadinessStatus`;
- manual MP3, WAV, OGG, and WebM upload, browser microphone recording, and
  CSV/ZIP audio import, all entering `uploaded` status;
- generated, managed paths under `storage/graduates/audio/...`;
- compensating replacement through `StudentMediaStorage` and
  `StudentAdministration`: store first, lock/update/audit in PostgreSQL, remove
  the new file after rollback, and remove the superseded managed file only
  after commit;
- data-encoder upload/preview/needs-review access and administrator-only final
  approval/rejection;
- approved/readable path checks centralized in `AnnouncementAudio`;
- approved-only URLs in `StageStatePayload`, one approved next-audio preload,
  and approved-only replay through `ReplayCurrentAnnouncement`; and
- local database/media backup with manifest and SHA-256 file checksums.

Generated audio must extend these boundaries, not create a second stage player
or bypass the existing readiness state, media custody, policy, audit, payload,
or fallback rules.

## Provider abstraction

Business workflows should depend on a small server-side interface rather than
Azure-specific request code:

```php
interface TextToSpeechProvider
{
    public function synthesize(
        AnnouncementSynthesisRequest $request,
    ): GeneratedSpeechResult;

    public function health(): ProviderHealthResult;
}
```

`AnnouncementSynthesisRequest` should contain validated SSML, plain-text
character count, allowlisted provider voice, locale, and output format. It must
not accept credentials, arbitrary endpoints, raw user SSML, public paths, or
student model serialization. `GeneratedSpeechResult` should carry validated
audio bytes or a private temporary-file handle, a safe provider request ID when
available, content type, size, and a safe result code.

The first implementation is `AzureSpeechProvider`, using Laravel's existing
HTTP client and the Azure Text to Speech REST API. No Azure SDK is required.
Future providers are out of scope and must satisfy the same contract and safety
rules.

## Implemented domain foundation

The structures below are implemented in the normalized PostgreSQL architecture.
Five ordered migrations add student metadata, awards, templates, generations,
and finally the nullable student-to-generation reference to avoid a circular
migration dependency.

### Student additions

| Proposed field | Purpose |
| --- | --- |
| `tts_spoken_name` | Optional, reviewed plain-text TTS override; never replaces the display or announcement name |
| `announcement_additional_text` | Optional, length-limited, institution-approved distinction; never arbitrary biography or SSML |
| `audio_source_type` | Nullable constrained value: `manual_upload` or `azure_tts` |
| `audio_generation_id` | Nullable reference to the generation that supplied the active audio |
| `audio_source_hash` | Nullable SHA-256 fingerprint for active generated audio only |

Existing rows with audio should be classified as `manual_upload`; rows without
audio remain null. Manual upload or browser recording must clear
`audio_generation_id` and `audio_source_hash`, set `audio_source_type` to
`manual_upload`, and continue resetting readiness to `uploaded`.

### `student_awards`

| Proposed field | Constraint or meaning |
| --- | --- |
| `id` | PostgreSQL bigint identity |
| `student_id` | Required FK to `students` |
| `award_name` | Required reviewed administrative label |
| `spoken_text` | Nullable polished noun phrase, not SSML and normally not the section lead-in |
| `display_order` | Non-negative integer; ties resolve by primary key |
| `is_announcement_enabled` | Required boolean, default true |
| timestamps | Time-zone-aware Laravel timestamps |

Awards should be normalized rather than stored as comma-separated text.
Exact duplicate award-name/spoken-text pairs for one student are rejected after
trimmed, case-insensitive comparison. Deterministic ordering is
`display_order`, then `id`. A generation stores its rendered text and hash, so
later award edits cannot rewrite history.

The plan's example puts `recipient of` inside each award's `spoken_text`. The
frozen recommendation is instead to store a list-ready noun phrase such as
`the Best in Research Award` and let the ceremony template contribute the one
`recipient of` lead-in. This avoids duplicated lead-ins in multi-award lists.

### `announcement_templates`

| Proposed field | Constraint or meaning |
| --- | --- |
| `id`, `ceremony_id`, `name` | Ceremony-scoped template identity |
| `introduction_text`, `awards_lead_in`, `closing_text` | Reviewed plain text only |
| `include_program`, `include_major`, `include_honors`, `include_awards`, `include_additional_text` | Explicit section switches |
| `default_voice`, `default_locale` | Values from server allowlists |
| `speaking_rate` | Bounded application value, not arbitrary SSML |
| `pause_after_name_ms`, `pause_between_sections_ms` | Bounded integer settings |
| `version` | Positive immutable version, unique per ceremony/template lineage |
| `is_active` | At most one active default per ceremony |
| `created_by`, `updated_by`, timestamps | Accountable configuration history |

Published versions should be immutable. Editing a published template creates a
new version; it must not mutate the source of an existing candidate. No user may
enter raw SSML. A dedicated builder safely escapes every dynamic value.

### `announcement_generations`

| Proposed field | Constraint or meaning |
| --- | --- |
| `id`, `student_id`, `ceremony_id`, `announcement_template_id` | Required, internally consistent references |
| `provider`, `provider_voice`, `locale`, `output_format` | Allowlisted synthesis configuration snapshot |
| `plain_text`, `ssml` | Exact generated snapshots; SSML is server-built only |
| `source_hash` | 64-character lowercase SHA-256 hex digest |
| `status` | Separate candidate lifecycle status |
| `character_count` | Count sent for local usage estimation |
| `candidate_audio_path` | Private local relative path; never returned in ordinary props or audit |
| `provider_request_id` | Optional bounded safe correlation value, not a credential |
| `attempt_count`, `generated_by`, `generated_at` | Request history |
| `reviewed_by`, `reviewed_at`, `review_notes` | Human decision history |
| `failure_code`, `failure_message` | Allowlisted code and sanitized bounded message |
| timestamps | Time-zone-aware Laravel timestamps |

Generation records should be retained as immutable evidence apart from legal
status transitions and bounded review/failure metadata. Foreign-key deletion
must not silently remove an active or historical generation. ENH-TTS-1 enforces
ceremony/student and ceremony/template consistency with composite PostgreSQL
foreign keys. The late student audio-generation link is also composite, so it
cannot target another graduate's generation. Generation history uses restrictive
deletion.

One active template per ceremony is enforced with a PostgreSQL partial unique
index and a ceremony-row-locking activation service. Template versions are
unique per ceremony. Candidate and approved paths remain nullable and are hidden
from default model serialization. Creator/updater/generator/reviewer references
are nullable and become null if an operator account is deleted, while the domain
record remains.

## Candidate lifecycle

The minimal generation status set is:

```text
pending -> generating -> needs_review -> approved
                    \-> failed
needs_review -> rejected
pending -> cancelled
needs_review|approved -> outdated
```

`draft` is unnecessary because preview text is derived before persistence.
`generated` is redundant because a successfully generated candidate must enter
`needs_review` immediately. `approved` on a generation records the review
decision; the student's existing `audio_readiness_status` remains the stage
playback gate. A failed or cancelled generation never changes active audio.

Candidates are stored outside public directories, for example:

```text
storage/app/private/announcement-candidates/{ceremony_uuid}/{generation_uuid}.mp3
```

Filenames must use collision-resistant identifiers and contain no name, student
number, provider endpoint, or credential. Preview requires a new authorized,
no-store controller in a later phase; never create a public storage link for
candidates. Partial/invalid files are deleted. Candidate retention after
approval/rejection/outdated status requires institutional approval.

## Preparation-time generation workflow

1. Authorize the user and resolve one student, ceremony, active template, and
   allowlisted voice.
2. Lock or consistently read the announcement source and build deterministic
   plain text.
3. Show text, character count, award count, approximate speaking time, budget
   impact, and any length warning before generation.
4. Build escaped, deterministic SSML server-side.
5. Calculate the source hash and idempotency key.
6. Reuse an identical successful candidate when policy permits; otherwise
   require explicit confirmation for forced regeneration.
7. Persist `pending`, transition to `generating`, and call Azure only from an
   explicit preparation action or bounded preparation job.
8. Validate the response and atomically store a private candidate.
9. Persist `needs_review`; do not change the student's active audio.
10. Require an authorized human to preview and compare content, pronunciation,
    pacing, beginning/end, noise, loudness, and venue compatibility.
11. Approve or reject in a distinct request. Never combine generation and
    approval.

## Source hash and stale-audio protection

`BuildAnnouncementSourceFingerprint` now uses a typed source DTO, versioned
canonical UTF-8 JSON, and SHA-256. Associative keys are sorted recursively;
missing optional values are JSON `null`; booleans and integers retain their
types; and awards remain an ordered list. Unicode and slashes are unescaped and
no insignificant whitespace is emitted.

The canonical source includes at least:

```json
{
  "schema": 1,
  "student": {
    "identifier": 42,
    "announcement_name": "...",
    "tts_spoken_name": null,
    "program": "...",
    "major": null,
    "honors": null,
    "awards": [
      {
        "display_order": 0,
        "award_name": "...",
        "spoken_text": "...",
        "is_announcement_enabled": true
      }
    ],
    "announcement_additional_text": null
  },
  "template": {
    "identifier": 1,
    "version": 1,
    "introduction_text": "...",
    "include_program": true,
    "include_major": true,
    "include_honors": true,
    "include_awards": true,
    "include_additional_text": false,
    "awards_lead_in": "recipient of",
    "closing_text": null
  },
  "synthesis": {
    "provider": "azure",
    "voice": "en-PH-JamesNeural",
    "locale": "en-PH",
    "speaking_rate_percent": -5,
    "pause_after_name_ms": 250,
    "pause_between_sections_ms": 160,
    "output_format": "audio-24khz-48kbitrate-mono-mp3"
  }
}
```

`source_hash = SHA256(canonical_json)`. The canonical object includes the
student identifier and output format; it excludes timestamps, credentials,
paths, request IDs, and review/failure metadata. Tests cover stability, Unicode,
award addition/removal/order/spoken wording/enablement, student and template
fields, voice, locale, rate, and pauses. These key names are hash schema version
1 and must not change without a schema increment and stale-generation handling.

At approval, lock the student, generation, applicable awards, and immutable
template row; recalculate the hash and reject approval if it differs. On edits
to a generated audio source, mark the generation `outdated`, move the active
student readiness from `approved` to `needs_review`, and retain the file for
authorized diagnosis until retention cleanup. The stage boundary must still
recalculate/verify the source hash, because event-driven invalidation alone
cannot protect against races or missed write paths.

This protects against name corrections, TTS-name changes, program/major/honors
changes, award edits/reordering/enablement, additional-text changes, template
versions, voice/locale/pacing changes, approval racing with an edit, and stale
generated files reaching the stage.

Manual audio has no source hash and is not automatically invalidated by these
data edits unless the institution later adopts that policy.

## Human approval and safe promotion

Generated approval is an administrator-only operation by default:

1. Reauthorize the reviewer and lock the student and generation rows.
2. Require `needs_review`, a readable private candidate, and matching student,
   ceremony, template, and current source hash.
3. Copy the candidate to a new generated name through an extension of
   `StudentMediaStorage`; never point active audio directly at private storage.
4. Set `audio_source_type=azure_tts`, link the generation and source hash, set
   the existing readiness to `approved`, and record reviewer/time/notes.
5. Write a safe audit event in the same PostgreSQL transaction.
6. On rollback, remove the abandoned promoted copy and preserve the previous
   active audio and metadata. After commit, remove only the superseded managed
   active file according to the existing compensation rule.

The student row lock serializes generated approval with manual replacement. A
manual upload may supersede approved generated audio at any later time and
returns to the current `uploaded` review workflow.

## Proposed authorization

| Role | Future capabilities | Explicit exclusions |
| --- | --- | --- |
| Administrator | Manage/version templates and allowlisted defaults; generate/compare; approve/reject; force regenerate; view usage; start bounded batches | No raw SSML or credential access in the browser |
| Data encoder | Manage awards and announcement fields; preview text/candidates; generate where approved; mark needs review | No final approval by default; no provider configuration |
| Stage operator | See safe active-audio availability, replay approved local audio, use live-announcer fallback | No Azure calls, candidates, templates, generation history, or private paths |
| Scanner operator | None | No TTS content or operations |
| Technical operator | Preparation service health may be visible only if later approved | No content management or credential visibility by default |

Every capability must be enforced by Laravel policies/gates. React abilities
are presentation hints only.

## Stage playback and offline guarantee

`AnnouncementAudio` remains the single playback decision boundary. For a manual
source, preserve the existing approved-and-readable check. For an Azure source,
also require a linked approved generation, matching active source hash, matching
freshly calculated current hash, and a readable managed active file.

```text
approved + readable + source valid -> existing same-origin audio URL
anything else                     -> null URL + Live announcer required
```

`StageStatePayload`, next-audio preload, presentation broadcasts, and replay
must all use that same decision. The audience receives no source type, generation
status, hash, failure, or provider detail. Missing/stale audio never blocks the
graduate's visual presentation.

Before the ceremony, approved files must exist on the server and pass the venue
rehearsal. Ceremony mode may set Azure disabled, disconnect internet access, and
stop any preparation worker. Health checks must classify PostgreSQL, Laravel,
Reverb, local media, and disk as ceremony-critical; Azure health is
preparation-only and must not fail ceremony readiness.

## Batch-generation boundary and cost control

Batch generation is deferred until single-student generation, review, and
approval are stable. It must use server-authorized, bounded, deterministic
selection; low or sequential concurrency; PostgreSQL progress; idempotent jobs;
bounded retries; cancellation of pending work; resumable status; and no bulk
approval. A browser loop must not be authoritative.

Generation never runs on page load, on each keystroke, or automatically after a
source edit. Show the character count first, track successful generated
characters locally by month, warn near a configurable budget, reuse identical
candidates where allowed, and require confirmation for forced regeneration.
Local usage is an estimate; Azure is the billing authority.

## Backup and restore integration

The existing PostgreSQL dump will naturally include future award, template,
generation, and active-source metadata after migrations. The media copy already
captures promoted active audio under `storage/app/public/graduates`. Later
backup work must also:

- include private candidates only according to the approved retention policy;
- preserve their relative private layout without exposing it in manifest UI;
- include candidate and promoted-file checksums;
- exclude all Azure credentials, authorization headers, environment files, and
  provider response bodies;
- verify award/template/generation counts, active generation references,
  source-hash shape, candidate policy, and active-media readability in a
  disposable restore; and
- define a consistent snapshot rule for a backup racing with generation or
  promotion.

An approved generated active file is never disposable merely because the
candidate copy is expired.

## Failure and fallback behavior

- Disabled/missing provider configuration: block generation with a safe
  preparation message; active local audio and ceremony remain unchanged.
- Authentication/invalid SSML/unsupported voice: do not retry automatically;
  record a safe failure code.
- Rate limit, timeout, temporary connection failure, or service error: apply
  bounded retries only as defined in `azure-speech-setup.md`.
- Empty, wrong-type, oversized, or unwritable response: remove partial files,
  mark failed safely, and preserve active audio.
- Rejection: keep the existing valid active audio and candidate history.
- Stale approval attempt: reject it and mark the candidate outdated.
- Stale or unreadable active generated audio at stage time: return no audio URL
  and show the existing live-announcer guidance.

## Verification and testing strategy

All database behavior is tested against PostgreSQL. ENH-TTS-1 covers
constraints, relationships, minimal enum values, role policies, source-hash
stability, and every invalidating field. ENH-TTS-2 covers no,
one, two, and multiple awards; missing optional sections; Unicode; long content;
spoken-name overrides; deterministic punctuation; XML escaping; and attempted
SSML injection without making a provider request.

ENH-TTS-3 uses Laravel HTTP fakes for configuration, authentication,
timeout, rate-limit, retry, wrong-content, size, partial-write, idempotency, and
secret-redaction cases. A real provider check, if separately approved, uses only
fictional content and does not replace mocked coverage. Approval integration
must exercise an edit racing approval, template/award changes, manual upload
racing promotion, file and transaction failures, rejection preserving active
audio, and stage refusal of stale or unreadable generated audio.

The final enhancement rehearsal must run the applicable original Laravel,
formatting, lint, build, browser, and concurrency regressions; create and verify
a local backup; restore it into a disposable PostgreSQL database/media area;
and prove scanning, presenting, replay, recovery, and live-announcer fallback
with Azure disabled and internet disconnected. Secret, browser-bundle, log,
audit, backup, private-path, and same-origin runtime scans are release gates.
ENH-TTS-3 tests create only fake private candidates and make no real provider
request. Controlled live verification remains an explicit preparation procedure.

## Proposed enhancement phases

1. **ENH-TTS-0:** requirements/content/security design freeze (approved).
2. **ENH-TTS-1:** PostgreSQL awards, templates, generations, source metadata,
   deterministic fingerprint foundation, models, policies, factories, and
   tests (implemented).
3. **ENH-TTS-2:** deterministic composer, safe SSML builder, current-source
   assembly, and preview serializer without Azure calls (implemented).
4. **ENH-TTS-3:** mocked and controlled Azure provider integration with private
   candidate storage (implemented).
5. **ENH-TTS-4:** awards and single-graduate generation UI (implemented).
6. **ENH-TTS-5:** review, promotion, rejection, invalidation, stage guard, and
   backup integration (implemented).
7. **ENH-TTS-6:** ceremony settings and fictional-data voice comparison
   (implemented).
8. **ENH-TTS-7:** bounded batch generation.
9. **ENH-TTS-8:** full regression, backup/restore, and disconnected ceremony
   rehearsal.

Each phase stops for approval. ENH-TTS-7 remains deferred until ENH-TTS-6 is
reviewed, committed, and approved.

## Risks and unresolved decisions

| Area | Risk | Decision still required |
| --- | --- | --- |
| Content | Institutionally incorrect or overlong announcement | Approve spoken fields, wording, thresholds, and override authority |
| Pronunciation | One voice will not pronounce every Filipino name correctly | Approve review panel/process and default voice/locale |
| Awards | Duplicate, subjective, or grammatically incompatible phrases | Approve award authority, noun-phrase rule, order, and additional distinctions |
| Privacy | Private candidates or paths exposed through routes/logs/backups | Approve candidate retention and protected preview policy |
| Integrity | Edit/approval or manual/generated replacement race | Approve row-lock/source-hash/promotion design |
| Storage | DB commit and filesystem promotion diverge | Approve compensation, orphan reconciliation, and backup-race behavior |
| Cost | Repeated or bulk requests exceed the intended budget | Approve monthly budget, warning point, batch size, and force-regeneration role |
| Operations | Preparation dependency is mistaken for ceremony-critical | Approve explicit preparation/ceremony deployment modes |
| Roles | Data encoder generation authority may be too broad | Confirm whether data encoders may call the provider |
| Authentication | Subscription key versus bearer-token flow | Select the initial server-side Azure authentication method |

## ENH-TTS-0 approval checklist

- [ ] Content policy and examples are institutionally approved.
- [ ] Award lead-in and `spoken_text` noun-phrase rule are approved.
- [ ] Proposed schemas, lifecycle statuses, and FK retention are approved.
- [ ] Role capabilities and administrator-only final approval are approved.
- [ ] Source canonicalization and stale-stage guard are approved.
- [ ] Manual upload precedence is approved.
- [ ] Voice allowlist, locale, pacing, format, and comparison process are approved.
- [ ] Character thresholds, monthly budget, and batch boundaries are approved.
- [ ] Candidate retention and backup/restore policy are approved.
- [ ] Preparation-only Azure and disconnected ceremony guarantee are approved.
