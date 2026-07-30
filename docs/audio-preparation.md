# Announcement Audio Preparation

Status: Phase 8 implemented

This document defines the local production workflow for graduate announcement
audio. It does not authorize online text-to-speech, cloud speech generation,
browser speech synthesis, or full audio editing inside the application.

## Supported formats and limits

- Formats accepted by upload validation: MP3, WAV, and OGG.
- Maximum file size: 15 MB.
- Storage: local Laravel `public` disk under generated
  `storage/graduates/audio/...` paths.
- Stage playback: same-origin local media only.
- Browser compatibility: MP3 and WAV are the safest cross-browser targets for
  the approved stage browser. OGG is accepted, but must be checked on the final
  target browser before ceremony use.

## Readiness states

- `missing`: no usable announcement audio exists.
- `uploaded`: a file exists but has not been reviewed.
- `needs_review`: pronunciation, content, volume, or quality needs review.
- `approved`: an administrator approved the readable local file for playback.
- `rejected`: the current file must not be used and needs replacement.

Upload and replacement never grant approval automatically. Replacement clears
approval timestamp and approver.

## Review checklist

Reviewers should compare the spoken clip with:

- stage display name;
- approved announcement name;
- pronunciation guide;
- program and honors context when relevant.

Manual quality checks:

- approximately 2-10 seconds;
- clear speech and correct name order;
- no clipped beginning or ending;
- minimal background noise;
- consistent silence padding;
- consistent voice and pacing;
- target integrated loudness near -16 LUFS;
- true peak at or below about -1 dBFS.

The application stores optional duration metadata but does not currently measure
duration, loudness, true peak, noise, or clipping. Do not treat upload success as
audio quality certification.

## Stage fallback

If approved audio is missing, rejected, unreviewed, or unreadable:

```text
Stage operator sees safe fallback guidance.
Audience screen remains visually correct.
The graduate may still be presented.
A human announcer reads the approved announcement name.
```

The ceremony should not be blocked solely because audio is unavailable unless an
institutional policy requires it.

## Test fixtures

Files under `public/media/phase3/audio` are development/test tones. They prove
local playback plumbing only and are not production announcements. Browser
regression scripts may explicitly approve them for automated testing. Production
graduate audio should be uploaded through graduate administration and approved
after human review.

## Venue checklist

- Use the exact stage laptop/browser planned for the event.
- Enable ceremony audio once before the first presentation.
- Preview several approved clips through the venue mixer/speakers.
- Verify replay, stop-on-complete, holding behavior, and missing-audio fallback.
- Keep a human announcer prepared with the approved announcement names.
