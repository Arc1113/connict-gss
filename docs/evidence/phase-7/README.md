# Phase 7 Browser Evidence

`tests/Support/phase7-browser-check.mjs` writes local PNG review evidence to this
directory after operating three authenticated Chromium profiles against a
freshly seeded development database.

Verified route/viewports:

- `/stage-control`: 1440×900, 1366×768, and 1024×768
- `/stage`: 1920×1080 and 1366×768
- `/scanner`: operational focus, queue feedback, automatic feedback, keyboard
  escape, and Reverb-independent HTTP scanning

The harness covers empty and ordered queues, realistic long identity/program
content, next and selected presentation, completion, skip/return, replay,
holding/restore, missing audio, disconnected/recovered control, automatic
initial presentation, persisted automatic pause/resume, refresh recovery,
role denial, reduced motion, overflow, and audience-content safety.

PNG files are review artifacts and match `/docs/evidence/**/*.png` in
`.gitignore`; they must not be committed. This index records the reproducible
checks without adding binary screenshots to source control.

