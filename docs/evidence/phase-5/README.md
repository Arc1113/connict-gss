# Phase 5 Browser Evidence

The Phase 5 browser harness opens the compiled local application in an
authenticated Google Chrome session. It exercises the real HTTP, Inertia,
PostgreSQL, and browser-rendered workflow rather than a mocked component
renderer.

Run against the default application port:

```bash
node tests/Support/phase5-browser-check.mjs
```

Or supply a local port override:

```bash
GSS_APP_URL=http://127.0.0.1:8001 node tests/Support/phase5-browser-check.mjs
```

Verified routes and states:

- `/admin/students/import` upload, local template download, and visible focus
- mapping with deterministic suggestions and realistic sample values
- warning preview with long names and programs
- atomic confirmation and completion summary
- imported graduate retrieval through `/admin/students`
- blocked preview for duplicate identifiers and invalid eligibility
- safe import audit through `/admin/audit-logs`
- scanner-role denial

Exact viewports represented:

- 1440×900
- 1366×768
- 1024×768

The harness checks same-origin assets, body overflow, contained tables, labels,
named buttons, visible focus, reduced-motion recognition, explicit row states,
long-content wrapping, absent QR tokens, audit privacy, CSV template safety, and
role boundaries.

Physical assistive-technology certification, 200% zoom, venue-network
throughput, and final operator rehearsal remain manual checks.
