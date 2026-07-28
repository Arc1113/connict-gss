# UI Content Rules

Status: Phase 3A foundation  
Updated: 2026-07-28

## Voice

Use calm, direct, event-ready language. Use sentence case, clear verbs, and
consistent terms: graduate, ceremony, scanner, stage, live updates.

Do not use “something went wrong,” raw status codes, debug detail, robotic
confirmations, or long operational paragraphs.

## Feedback structure

Operator feedback should answer:

1. What happened?
2. Was the action confirmed?
3. What should the operator do next?

Keep the result title short and put recovery in one concise sentence.

## Scanner result copy

| Result | Title | Recovery direction |
| --- | --- | --- |
| Ready | Ready to scan | Scan one graduate QR and wait for confirmation. |
| Processing | Processing scan | Keep the QR away from the scanner until this check finishes. |
| Accepted | Graduate confirmed | Name the graduate and confirm the stage update or saved recovery. |
| Invalid | QR code not recognized | Scan the assigned QR again; escalate if it still fails. |
| Duplicate active | Graduate already on stage | Do not scan again; wait for completion. |
| Completed | Graduate already completed | Ask the stage operator before any exception. |
| Ineligible | Graduate not eligible | Send the graduate to the authorized data/ceremony desk. |
| Busy | Stage is occupied | Wait for the current presentation to be completed. |
| Inactive | Ceremony not ready | Ask an administrator to activate/resume the correct ceremony. |
| Request failed | Scan not confirmed | Check server connection before deciding whether to rescan. |
| Reverb down | Live updates reconnecting | HTTP scanning remains available; saved state will recover. |

Do not echo raw scanned tokens in any message.

## Labels and actions

- Prefer `Process scan` over generic `Submit`.
- Prefer `Return to operations` over a product-style dashboard callout.
- Use `Last successful scan`, not analytics language such as “recent metric.”
- Label server and live-update state separately.

## Audience copy

Use only approved ceremony identity, graduate identity, program/major, and
honors. Do not mention loading technology, server, Reverb, HTTP recovery,
missing audio, or operator instructions.

Idle/loading copy must be short:

- Loading: `Preparing ceremony display`
- Idle: `Welcome, graduates` / `Awaiting the next presentation`

## Formatting realistic names

Preserve punctuation, initials, particles, suffixes, and approved capitalization.
Allow natural word wrapping; do not truncate a graduate name without explicit
institutional approval.

Examples:

- Ana Marie dela Cruz
- Christopher Emmanuel Villanueva-Santos
- Ma. Lourdes P. Reyes
