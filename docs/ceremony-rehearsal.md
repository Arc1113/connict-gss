# Ceremony Rehearsal

Status: Phase 12 software remediation verified; physical rehearsal pending

Release candidate: Phase 12 working-tree candidate based on
`e6f1888aea01923eded291d3bf2a65a0e2f5f1ab`

Git commit tested: `e6f1888aea01923eded291d3bf2a65a0e2f5f1ab` plus the
approved Phase 12 working-tree implementation and documentation changes.

## Scope

This rehearsal verifies the implemented Graduation Stage System as a local
software system. It does not certify the physical USB scanner, projector/LED
display, venue audio, router, cables, or the final three-laptop layout.

## Software rehearsal completed

The automated browser rehearsal exercised the full operational path:

```text
scan input → HTTP validation → PostgreSQL transaction
→ post-commit Reverb event → private Echo channel
→ stage-control/stage update → local audio handling
→ HTTP recovery after refresh or service interruption
```

The rehearsal used fictional data only and covered:

- valid scan;
- invalid QR;
- duplicate QR;
- ineligible graduate;
- scan while another graduate is presenting;
- controlled presentation;
- selected out-of-order presentation;
- assisted-automatic mode pause/resume;
- operator-confirmed complete-and-present-next;
- wrong-current correction into holding and verified replacement presentation;
- skipped and returned graduate;
- approved audio;
- missing audio and live-announcer fallback;
- long graduate name;
- long program name;
- holding state;
- Reverb restart;
- application restart;
- audience-stage HTTP recovery;
- administrator, encoder, and technical-operator access/navigation checks;
- administrator-managed local operator accounts;
- export privacy check;
- local asset check.

## Viewports inspected

- Scanner: 1440×900, 1366×768, 1024×768.
- Stage control: 1440×900, 1366×768, 1024×768.
- Stage: 1920×1080, 1366×768, reduced-motion 1366×768.

## Results

- Browser rehearsal status: passed, including Phase 12 combined advancement,
  wrong-current correction, account administration, and technical navigation.
- Reverb restart: recovered.
- Application restart: recovered after browser refresh.
- Audience safety: passed; the stage did not expose navigation, QR tokens,
  student numbers, queue details, Reverb/WebSocket terms, SQL errors, stack
  traces, or operator controls.
- Local assets: passed; no remote runtime requests were observed in the browser
  rehearsal.
- Horizontal overflow: one admin topbar overflow defect was found and corrected.

## Physical rehearsal still required

Before event certification, run the same workflow using the real:

- server laptop;
- scanner laptop;
- stage laptop;
- dedicated router or switch;
- USB 2D QR scanner;
- printed QR cards;
- projector/LED display;
- venue audio path;
- final operator accounts;
- final ceremony data.

The final physical rehearsal must confirm scanner suffix timing, scan distance,
paper contrast, browser focus recovery, stage fullscreen behavior, audio output
selection, venue loudness, audience-distance readability, power stability, and
operator handoff procedures. It must also rehearse **Complete and present next**,
**Complete and clear**, and **Correct wrong graduate** with the assigned stage
operator before audience admission.
