# Phase 6 Browser and Print Evidence

Captured: 2026-07-30

Command:

```bash
GSS_APP_URL=http://127.0.0.1:8001 node tests/Support/phase6-browser-check.mjs
```

The harness uses authenticated local Chrome sessions against the compiled
Laravel/Inertia application. It creates realistic temporary graduates, exercises
the protected QR and scanner workflows, restores authoritative stage state, and
removes its temporary records. It rejects external browser resources and never
logs or stores a raw QR token in this directory.

## Route, state, and viewport matrix

| Evidence | Route/state | Viewport |
| --- | --- | --- |
| `qr-workspace-1440x900.png` | `/admin/students/qr-codes`, readiness index | 1440×900 |
| `qr-workspace-1366x768.png` | Readiness index, laptop fit | 1366×768 |
| `qr-workspace-1024x768.png` | Readiness index, minimum admin width | 1024×768 |
| `qr-workspace-selected-1366x768.png` | Valid batch selection and action state | 1366×768 |
| `qr-individual-long-name-1440x900.png` | Individual review with realistic long name/program | 1440×900 |
| `qr-individual-long-name-1024x768.png` | Individual review wrapping | 1024×768 |
| `qr-regenerated-warning-1366x768.png` | Administrator replacement/reprint warning | 1366×768 |
| `qr-print-individual-screen-1366x768.png` | Individual print route in screen media | 1366×768 |
| `qr-print-batch-screen-1366x768.png` | Batch print route in screen media | 1366×768 |
| `qr-print-individual-a4.png` | Individual A4 portrait print-media raster | 794×1123 |
| `qr-print-batch-a4.png` | Batch A4 portrait print-media raster | 794×1123 |
| `scanner-phase6-ready-1440x900.png` | `/scanner`, primary input ready | 1440×900 |
| `scanner-generated-qr-success-1440x900.png` | Successful generated-token scan | 1440×900 |
| `scanner-controlled-lookup-1024x768.png` | Controlled fallback results and verification | 1024×768 |

`qr-print-individual-a4.pdf` and `qr-print-batch-a4.pdf` are Chromium
print-to-PDF artifacts used to inspect A4 page count and physical-layout rules.

The run also checks visible keyboard focus, refocus after processing,
single-request repeated-Enter locking, old-card rejection, replacement-card
acceptance, successful controlled lookup/processing, long-content containment,
reduced-motion behavior, same-origin assets, data-encoder restrictions, and
absence of audience-facing changes. One temporary graduate is created through
the actual Phase 5 confirmation action and its protected QR output is opened in
the browser before cleanup.

`pdfinfo` confirmed both PDFs are one-page, unrotated A4 documents with no
JavaScript or external metadata dependency. A separate ephemeral OpenCV decode
of `QrCodeRenderer` output matched the exact expected 52-character token; the
decoder and temporary raster were removed after the check and were not added as
project/runtime dependencies.

## Physical limitation

These files demonstrate browser rendering and Chromium print output only. They
do not certify a physical printer, paper scale, grayscale contrast, scanner
decode distance, USB suffix timing, scanner beep, projector, speakers, or venue
network. Follow the exact hardware checklist in `README.md` before ceremony use.
