# Automated Voice Announcements

Status: **ENH-TTS-0 requirements and design freeze; no runtime implementation**

Last reviewed: 2026-08-03

This document defines the proposed architecture for preparation-time automated
graduate announcements. It does not authorize migrations, routes, provider
requests, generated files, or ceremony-runtime changes. Implementation begins
only after human approval of this design freeze.

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

The current requirements and `docs/audio-preparation.md` prohibit cloud TTS
because no approved cloud workflow exists today. This enhancement is a proposed
narrow exception for preparation mode only. Those current prohibitions remain
effective until the applicable enhancement phases are implemented, tested, and
approved; the prohibition remains permanent for ceremony-runtime paths.

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

## Proposed domain model

The structures below are appropriate for the current normalized PostgreSQL
architecture. Exact Laravel migrations and indexes belong to ENH-TTS-1.

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
| `spoken_text` | Required polished noun phrase, not SSML and normally not the section lead-in |
| `display_order` | Positive integer, unique per student |
| `is_announcement_enabled` | Required boolean, default true |
| timestamps | Time-zone-aware Laravel timestamps |

Awards should be normalized rather than stored as comma-separated text.
Duplicate effective spoken phrases for one student should be rejected after
trimmed, case-insensitive comparison. Deterministic ordering is
`display_order`, then `id` only as a corruption-safe tie-breaker. A generation
stores its rendered text and hash, so later award edits cannot rewrite history.

The plan's example puts `recipient of` inside each award's `spoken_text`. The
frozen recommendation is instead to store a list-ready noun phrase such as
`the Best in Research Award` and let the ceremony template contribute the one
`recipient of` lead-in. This avoids duplicated lead-ins in multi-award lists.

### `ceremony_announcement_templates`

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
must not silently remove an active or historical generation. Student deletion
is already history-protected; future constraints should preserve that behavior.

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

Use a versioned canonical UTF-8 JSON representation and SHA-256. Keys are fixed
and emitted in the documented order; missing optional values are JSON `null`;
booleans and integers retain their types; strings use the validated persisted
text without locale-dependent case conversion; awards are an ordered array.
Use unescaped Unicode/slashes and no insignificant whitespace.

The canonical source includes at least:

```json
{
  "schema": 1,
  "student": {
    "announcement_name": "...",
    "tts_spoken_name": null,
    "program": "...",
    "major": null,
    "honors": null,
    "enabled_awards": [
      {"order": 1, "award_name": "...", "spoken_text": "..."}
    ],
    "announcement_additional_text": null
  },
  "template": {
    "id": 1,
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
    "provider": "azure_speech",
    "voice": "en-PH-JamesNeural",
    "locale": "en-PH",
    "speaking_rate": "-5%",
    "pause_after_name_ms": 250,
    "pause_between_sections_ms": 160,
    "output_format": "audio-24khz-48kbitrate-mono-mp3"
  }
}
```

`source_hash = SHA256(canonical_json)`. The idempotency key additionally binds
the student ID and output format. Hashing must be unit-tested for stability,
Unicode, null/empty distinctions, award order, and every invalidating field.
The canonical key names above are part of hash schema version 1; implementations
must not silently rename a key or change value normalization without incrementing
that schema version and making earlier generated audio outdated.

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

All future database behavior must be tested against PostgreSQL. ENH-TTS-1 must
cover constraints, relationships, legal status transitions, role policies,
source-hash stability, and every invalidating field. ENH-TTS-2 must cover no,
one, two, and multiple awards; missing optional sections; Unicode; long content;
spoken-name overrides; deterministic punctuation; XML escaping; and attempted
SSML injection without making a provider request.

ENH-TTS-3 must use Laravel HTTP fakes for configuration, authentication,
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
ENH-TTS-0 changes documentation only, so it does not claim those future runtime
checks have passed.

## Proposed enhancement phases

1. **ENH-TTS-0:** requirements/content/security design freeze (this phase).
2. **ENH-TTS-1:** PostgreSQL awards, templates, generations, source metadata,
   models, policies, factories, and tests.
3. **ENH-TTS-2:** deterministic composer, safe SSML builder, source hash, and
   preview serializer without Azure calls.
4. **ENH-TTS-3:** mocked and controlled Azure provider integration with private
   candidate storage.
5. **ENH-TTS-4:** awards and single-graduate generation UI.
6. **ENH-TTS-5:** review, promotion, rejection, invalidation, stage guard, and
   backup integration.
7. **ENH-TTS-6:** ceremony settings and fictional-data voice comparison.
8. **ENH-TTS-7:** bounded batch generation.
9. **ENH-TTS-8:** full regression, backup/restore, and disconnected ceremony
   rehearsal.

Each phase stops for approval. ENH-TTS-1 must not begin until this design and
the content policy receive human sign-off.

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
