# Offline Voice Generation

The preparation workflow can generate announcement candidates with the pinned
Kokoro-82M model inside the `local-tts` container. Model inference and voice
selection remain local after the deployment image is built. The stage browser
still plays only reviewed and approved local files and never calls the synthesis
service during a ceremony.

## Professional MC delivery

Local synthesis uses the fixed `professional_mc` delivery profile. The service
renders the complete punctuated announcement as one contextual utterance rather
than rendering every database section as an isolated clip. It does not insert
the template's raw millisecond pauses. It preserves ordinary short speech gaps,
trims excessive edge silence, and reduces internal near-silence of at least 500
milliseconds to 250 milliseconds.

The profile controls pacing continuity only. It is not an emotion-conditioned
model, does not clone a person, and does not add words or pronunciation markup.
Operators must still listen to every candidate and use the reviewed TTS spoken
name field when a name needs correction.

## Introduction cadence

The announcement template defaults to `first_per_program`: the eligible
graduate with the lowest sequence number in each normalized program receives
the introduction, and later graduates in that program do not. The settings
page also supports `every_student` and `never`. Changing this setting creates a
new template version and requires affected candidates to be regenerated and
reviewed.

## Presentation modes

Ceremonies support controlled, assisted automatic, and fully automatic modes.
Fully automatic mode accepts QR scans in FIFO order, presents the first idle
scan, starts its approved audio on the audience stage, and advances only after
the browser reports natural audio completion. Playback errors pause the flow
and leave the current graduate available for operator recovery.

## Packaged voices

| Voice ID | Display name | Profile |
| --- | --- | --- |
| `am_michael` | Michael | Male, American English, local default |
| `am_fenrir` | Fenrir | Male, American English, comparison alternative |
| `af_heart` | Heart | Female, American English |
| `af_bella` | Bella | Female, American English, expressive alternative |

All four voice files and the model are downloaded from the pinned upstream
revision during the image build and verified with committed SHA-256 checksums.
Adding or replacing a voice requires an allowlist update, a pinned checksum,
provider validation tests, an offline image rebuild, and a human pronunciation
review.

## Timing settings and compatibility

Azure continues to use `pause_after_name_ms` and
`pause_between_sections_ms`. Published templates retain those fields for source
history and provider compatibility. The settings screen hides the millisecond
controls when Local offline is selected because the professional MC profile
manages local gaps automatically.

Kokoro engine revision 3 identifies recordings made by this renderer. The
associated migration marks reviewable revision-2 Kokoro candidates outdated and
moves linked approved student audio back to `needs_review`. Regenerate, listen,
and approve those candidates before ceremony use. Azure recordings and manual
uploads are not changed.

## Release verification

Run the normal full release gate plus the local service build test:

```bash
docker compose build local-tts
docker compose exec -T app php artisan test
docker compose exec -T app vendor/bin/pint --test
npm run lint
npm run build
docker compose -f compose.yaml -f compose.deploy.yaml config --quiet
```

During the hardware rehearsal, compare all four voices with representative
Filipino names, long programs, honors, and awards. Michael remains the default
only after authorized reviewers confirm clear, professional delivery on the
actual venue sound system.
