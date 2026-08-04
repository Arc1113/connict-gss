# Graduation Announcement Content Policy

Status: **ENH-TTS-4 preparation preview and private candidate playback implemented; approval deferred**

Last reviewed: 2026-08-03

This policy governs the future deterministic plain text used for automated
candidates and their human review. ENH-TTS-2 implements deterministic plain-text
composition, readiness assessment, safe SSML, and a side-effect-free backend
preview service. ENH-TTS-3 may create one private preparation candidate through
the approved provider action. ENH-TTS-4 exposes the deterministic plain text and
protected private candidate audio to authorized preparation users, but does not
authorize generated audio for stage playback. Every candidate still requires a
future explicit administrator approval workflow.

## Permitted source fields

Only these reviewed fields may contribute spoken words:

1. ceremony-template introduction and optional closing;
2. the student's `announcement_name`, or optional approved
   `tts_spoken_name` for synthesis only;
3. program/degree;
4. major when the ceremony template enables it;
5. academic honors;
6. enabled structured awards in approved order; and
7. one short, institution-approved additional distinction when enabled.

The student number, QR token, email, phone/address, birth date/age, eligibility
or queue status, sequence number, college unless separately approved as a
template field, pronunciation notes, internal IDs, filenames/paths, review
notes, disability/health data, disciplinary/financial data, credentials,
provider details, and any unapproved free-form biography must never be spoken.

An absent optional field is omitted cleanly. It must never produce the words
`null`, `none`, `N/A`, an empty pause, or dangling punctuation.

## Section order

The fixed composition order is:

```text
Introduction
Graduate spoken name
Program or degree
Major, if approved and enabled
Academic honors, if present and enabled
Selected awards, if any and enabled
Additional approved distinction, if present and enabled
Closing, if configured
```

The composer, not the browser or Azure, owns punctuation and conjunctions.
Plain text is the review authority; SSML adds only escaped voice/pacing markup
and must not change the words.

The implemented composer first canonicalizes whitespace and orders awards,
then creates only non-empty sections in the sequence above. It joins semantic
sections with commas and one space. A final `?` or `!` is preserved; otherwise
trailing clause punctuation is replaced by one period. There is no automatic
truncation. Literal absence markers (`null`, `undefined`, `none`, `N/A`) and
raw placeholder-only values are blocking and omitted from speech.

## Initial ceremony-template defaults

The approved starting domain defaults are:

| Setting | Default value |
| --- | --- |
| Introduction | `We proudly present` |
| Include program | Yes |
| Include major | Yes, only when reviewed and non-empty |
| Include honors | Yes |
| Include awards | Yes |
| Awards lead-in | `recipient of` |
| Include additional distinction | No by default |
| Closing | None |

Voice, locale, speaking rate, and pauses are synthesis settings documented in
`azure-speech-setup.md`; they do not alter section wording. Changing any
published template value creates a new template version rather than rewriting a
candidate's historical source.

## Graduate-name rules

- `announcement_name` is required, manually reviewed, and remains the default
  spoken name. It is distinct from `display_name` and is never recomputed from
  first/middle/last fields during ordinary edits.
- Preserve reviewed capitalization, diacritics, apostrophes, hyphens, suffixes,
  and name order. Do not title-case, Anglicize, translate, expand initials, or
  infer a preferred name.
- Trim surrounding whitespace and reject unsupported control characters.
- Do not speak the student number or use it to disambiguate two similar names.
- If pronunciation needs different plain text, use `tts_spoken_name`; do not
  overwrite the approved announcement or display name.
- `pronunciation_guide` remains a visible human note. It is not spoken and must
  not be assumed to be IPA, SSML, or provider-ready phoneme data.

## TTS spoken-name override

`tts_spoken_name` is optional plain text, maximum 255 characters unless a later
approved migration chooses a stricter limit. It affects synthesis only; the
stage display and human-announcer reference continue using
`announcement_name`.

Example:

```text
Announcement/display name: Robert Jhon Aracena
TTS spoken name: Robert John Ara-seh-na
Human pronunciation guide: ROH-bert JOHN ah-rah-SEH-nah
```

The override must be clearly labeled beside the announcement name and human
guide during review. It may use simple institution-approved syllable hints but
not raw SSML. Adding, removing, or editing it invalidates generated candidates
and active generated audio.

## Program, degree, and major rules

- `program` is the current required reviewed source. Speak it exactly as stored
  unless the institution later adds a separate structured degree/program spoken
  override.
- Do not guess or expand abbreviations such as `BS`, `BA`, or `PhD`; the reviewed
  stored wording must already be ceremony-ready.
- Do not speak both a degree and program when they are alternative labels for
  the same credential. A future structured split needs an explicit precedence
  rule before implementation.
- Major is optional and spoken only when non-empty and the ceremony template
  enables it.
- The future default form is `major in {major}` only if the stored major does
  not already begin with an institution-approved connector. Exact connector
  wording requires institutional approval; until then, preview must expose the
  complete resulting sentence.
- College is not part of the initial spoken policy. Adding it requires a new
  approved template field and source-hash version.

## Academic honors

- Speak only the reviewed `honors` value when present and enabled.
- Preserve approved Latin capitalization and diacritics; do not infer honors
  from grades, eligibility, college, awards, or other fields.
- The field currently permits up to 1,000 characters, but ceremony content must
  remain concise. Multiple honors must be normalized into institution-approved
  wording before generation, not treated as an uncontrolled list.
- Honors are distinct from awards. Do not repeat the same distinction in both
  sections.
- Editing honors invalidates generated candidates and active generated audio.

## Structured award rules

Each award has a reviewed administrative `award_name`, an optional polished
plain-text `spoken_text`, a non-negative `display_order`, and
`is_announcement_enabled`.

- Only enabled awards will be spoken. The source fingerprint retains the ordered
  award records and their enablement flag so enabling or disabling one changes
  the hash.
- Disabled awards remain stored for records/UI but are omitted entirely from
  plain text and SSML.
- Award order is ascending `display_order`, then primary key. Accessible move-up/move-down
  actions should control that order; creation time and alphabetical sorting do
  not silently change it.
- Exact duplicate award-name/spoken-text pairs for one student are prohibited
  after trim and case-insensitive comparison.
- `spoken_text` is a list-ready noun phrase, for example
  `the Best in Research Award`. It must not contain raw SSML, a leading comma,
  a trailing period, or the awards section's lead-in/conjunction.
- The template supplies one `awards_lead_in`, initially proposed as
  `recipient of`. This avoids outputs such as `recipient of recipient of...`.
- An override may polish pronunciation or institutional naming but may not add
  personal commentary, undisclosed awards, credentials, or another graduate's
  information.
- Changing award name, effective spoken text, order, enablement, or template
  lead-in invalidates generated candidates and active generated audio.

Example record:

```text
award_name: Best in Research
spoken_text: the Best in Research Award
display_order: 1
is_announcement_enabled: true
```

## Additional approved distinction

`announcement_additional_text` is optional plain text for one short distinction
that cannot be represented as an honor or structured award. It is disabled by
default at template level, has a database maximum of 700 characters, and should
normally remain within the 200-character content recommendation. It must be
approved by the institution before generation.

It must not contain a biography, dedication, quotation, joke, sponsor message,
political/religious statement, contact or sensitive information, raw SSML, or
unverified claim. If the content is an award, store it as a structured award.
Editing it or its template inclusion switch invalidates generated audio.

## Punctuation and list formatting

The composer builds non-empty sections first, then joins them deterministically.
Use a single space after punctuation, Unicode-preserving text, no doubled spaces,
no duplicated commas/conjunctions, and exactly one terminal punctuation mark.

For awards, the template lead-in precedes the list once:

- zero: omit the entire award section;
- one: `{lead-in} {award}`;
- two: `{lead-in} {award 1} and {award 2}`;
- three or more: `{lead-in} {award 1}, {award 2}, and {final award}`.

The Oxford comma is the implemented standard for three or more awards. Award
`spoken_text` values do not include separators or a final period. Empty or
duplicate awards are rejected rather than silently producing malformed speech.

The implemented award comparison uses Unicode normalization, case folding,
punctuation removal, and exact normalized equality. The major/honors duplicate
check additionally uses padded whole-phrase containment in either direction.
It is deliberately conservative: no fuzzy matching, abbreviation expansion,
translation, or AI semantic service is used.

## Approved-form examples

These examples use the proposed introduction `We proudly present`, the
program immediately after the name, and no closing text.

### No awards

> We proudly present Robert John Lirasan, Bachelor of Science in Computer Science.

### One award

> We proudly present Robert John Lirasan, Bachelor of Science in Computer Science, recipient of the Best in Research Award.

### Two awards

> We proudly present Robert John Lirasan, Bachelor of Science in Computer Science, Magna Cum Laude, recipient of the Best in Research Award and the Leadership Excellence Award.

### Multiple awards

> We proudly present Robert John Lirasan, Bachelor of Science in Computer Science, Magna Cum Laude, recipient of the Best in Research Award, the Leadership Excellence Award, and the Outstanding Capstone Award.

The examples define grammar, not permission to announce those fictional facts.
Real content must come from reviewed institutional records.

## Announcement-length policy

Never silently truncate content. The provisional character thresholds are:

| Length | Result |
| ---: | --- |
| 80–450 characters | Recommended review range |
| 451–700 characters | Warning; shorten or explicitly continue to review |
| More than 700 characters | Block generation unless an administrator records an approved override |

Announcements under 80 characters are allowed but should be reviewed for
missing required content. Character count uses the exact reviewed plain text.
The UI should also show enabled-award count and a clearly labeled approximate
speaking time. Character count cannot predict exact duration; voice, language,
punctuation, pauses, and speaking rate affect it. Final thresholds and override
authority require institutional approval.

The preview duration is a deterministic range, not a measurement. It counts
Unicode words, uses 145–165 words per minute, adjusts by the configured integer
speaking rate, and adds section breaks. Whole seconds are rounded upward to
avoid floating-point-dependent output. Actual Azure duration may differ.

## Implemented validation codes

The preview result exposes stable codes and human-readable messages:

- required content: `missing_spoken_name`, `missing_program`;
- safe deduplication: `major_duplicates_program`,
  `honors_duplicates_section`, `duplicate_award_phrase`;
- award contract: `empty_enabled_award`, `award_contains_lead_in`,
  `award_not_list_ready`;
- source safety: `raw_markup_detected`, `raw_placeholder_detected`;
- synthesis settings: `unsupported_voice`, `unsupported_locale`,
  `speaking_rate_out_of_range`, `pause_out_of_range`;
- limits: `invalid_character_limits`,
  `announcement_above_recommended_limit`,
  `announcement_above_maximum_limit`; and
- preview resolution: `missing_active_template`,
  `template_ceremony_mismatch`.

Warnings never hide or rewrite source content. Blocking errors prevent SSML
output. A future provider action must refuse a blocked preview.

## Pronunciation review

1. The data encoder compares `announcement_name`, optional `tts_spoken_name`,
   human `pronunciation_guide`, program, major, honors, awards, and additional
   text with the approved record.
2. Generate only after the plain-text preview is correct.
3. Listen on ordinary preparation headphones/speakers for name, Filipino words,
   suffixes, abbreviations, pauses, stress, list grammar, clipped boundaries,
   volume, and artifacts.
4. If pronunciation is wrong, correct the reviewed plain-text override or choose
   another allowlisted voice; do not place arbitrary SSML in student fields.
5. Have an authorized reviewer who knows the intended pronunciation confirm the
   result. For uncertain names, ask the graduate or institution-approved source
   rather than guessing.
6. Record concise safe review notes. Do not put sensitive biography, credentials,
   private paths, or provider responses in notes.
7. Rehearse representative approved clips on the actual stage browser, mixer,
   speakers, and venue before ceremony sign-off.

The existing manual audio guidance remains applicable: clear speech, correct
name order, no clipped beginning/end, minimal noise, consistent pacing/silence,
approximately -16 LUFS integrated loudness, and true peak at or below about
-1 dBFS. The application does not currently measure or certify those values.

## Voice-comparison process

- Administrators initiate comparison explicitly in preparation mode.
- Use fictional or specifically institution-approved test names and content,
  never a live student's private record merely for voice shopping.
- Show character usage before generating each sample.
- Compare only the server allowlist documented in `azure-speech-setup.md`.
- Keep rate, pauses, content, output format, and playback equipment identical so
  the voice is the variable being evaluated.
- Score intelligibility, name pronunciation, Filipino/English handling,
  ceremonial tone, pacing, and venue clarity. A pleasant voice does not prove
  pronunciation correctness.
- Samples remain temporary/private and can never become active graduate audio.
- Selecting a ceremony default versions the template and makes earlier hashes
  stale; each graduate still requires candidate-specific human review.

## Human approval requirement

Generation and approval are always separate actions. Successful synthesis means
only that a candidate exists. It does not certify accurate content,
pronunciation, audio quality, privacy, or stage fitness.

Administrator approval must confirm:

- plain text matches the approved record and section order;
- spoken output matches the plain text without omission/addition;
- name and all distinctions are pronounced acceptably;
- grammar, pauses, rate, beginning/end, and volume are acceptable;
- candidate source hash still matches current data/template/voice settings;
- the local candidate is readable and uses the approved output format; and
- a human-announcer fallback remains available.

Data encoders may prepare and preview but cannot approve by default. No batch,
provider success, import, template default, or prior approval may auto-approve a
new candidate.

## Conditions that invalidate generated audio

Any change to the following makes the affected candidate/active generated audio
outdated:

- `announcement_name` or `tts_spoken_name`;
- program, major, or honors;
- award name, effective spoken text, display order, enablement, addition, or
  removal;
- additional approved text;
- any field-inclusion switch, introduction, award lead-in, closing, or template
  version;
- provider, provider voice, locale, speaking rate, pause settings, or output
  format; or
- canonicalization/hash schema version.

Approval must recalculate the source hash under row locks. Stage playback must
verify it again and refuse stale generated audio even if an invalidation event
was missed. Rejection, missing/unreadable files, or a broken generation link also
blocks generated playback. The operator then sees the existing **Live announcer
required** fallback; the audience presentation remains visually valid.

Manual uploaded/recorded audio remains supported and is not automatically
invalidated by these source-field changes unless the institution later approves
that separate policy. Replacing generated audio manually clears its generation
link/hash and returns to `uploaded` for ordinary human review.

## Decisions requiring institutional approval

- Exact introduction, closing, and whether college may ever be spoken.
- Degree/program precedence and major connector wording.
- Approved honors vocabulary and whether multiple honors are permitted.
- Award authority, lead-in, noun-phrase override rule, ordering, and duplicate
  comparison.
- Whether additional distinction text is permitted and its final limit.
- Final character thresholds and administrator override procedure.
- Who may provide/confirm pronunciation and what evidence is retained.
- Default voice/locale and voice-comparison scoring/sign-off.
- Whether manual audio should ever be invalidated after source-data edits.
