# Phase 4 Browser Evidence

The Phase 4 browser harness opens the compiled local application in authenticated
Google Chrome sessions. It does not use a mocked component renderer.

Run:

```bash
node tests/Support/phase4-browser-check.mjs
```

Verified administration routes:

- `/admin`
- `/admin/ceremonies`
- `/admin/ceremonies/create`
- `/admin/ceremonies/{ceremony}`
- `/admin/ceremonies/{ceremony}/edit`
- `/admin/students`
- `/admin/students/create`
- `/admin/students/{student}/edit`
- `/admin/students/{student}`
- `/admin/audit-logs`
- administrator-only ceremony denial using a data-encoder session

Exact viewports represented:

- 1440×900
- 1366×768
- 1024×768

The harness checks same-origin assets, body overflow, table containment, form
labels, named buttons, visible focus, long graduate/program text, absent raw QR
tokens, no-results and validation states, generated local photo/audio delivery,
native audio playback, safe audit text, and data-encoder navigation/403
boundaries.

Screenshots:

- `admin-readiness-1440x900.png`
- `ceremonies-list-1366x768.png`
- `ceremonies-list-1024x768.png`
- `graduates-list-1440x900.png`
- `graduates-list-1366x768.png`
- `graduates-list-1024x768.png`
- `graduates-no-results-1024x768.png`
- `graduate-validation-errors-1024x768.png`
- `graduate-media-detail-1366x768.png`
- `audit-history-1440x900.png`
- `encoder-ceremony-denied-1024x768.png`

Physical USB scanning, projector/LED output, venue speakers, assistive-technology
certification, 200% zoom, and the final three-laptop rehearsal are not certified
by this automated browser run.
