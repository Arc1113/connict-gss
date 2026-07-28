# Component Guidelines

Status: Phase 3A foundation  
Updated: 2026-07-28

Create a component only when the current phase needs repeated behavior or a
clear domain boundary. Do not scaffold every component suggested by the
playbook.

## Current primitives

### Action

- Use a native button for commands and a link for navigation.
- Use a specific verb.
- Preserve visible focus, disabled state, and at least 44px target height.
- One visual primary action per region.

### Field

- Use a visible label and associated help/error text.
- Keep validation and request state close to the field.
- Do not hide the scanner input solely for visual cleanliness.

### Status line

- Pair a short label with plain-language state.
- Use a small semantic marker only as supporting information.
- Do not turn every status into a pill or card.
- Separate HTTP server state from live-update transport.

### Operational alert

- Show a short result title and one recovery sentence.
- Use `role="status"` or an appropriate live region.
- Semantic border/background may reinforce, never replace, copy.

### Graduate identity

- Keep display name dominant.
- Render optional program, major, honors, and photo only when present.
- Do not expose a whole student model.
- Preserve natural wrapping and official text.

## Composition rules

- Prefer semantic page sections and CSS layout over wrapper components.
- Keep server contracts in page/domain logic, not generic visual primitives.
- Keep Echo configuration centralized.
- Do not add icon packages for a handful of status dots.
- Do not add a UI library during Phase 3A.
- Avoid abstractions that would require unrelated screen refactors.

## Future components

Queue items, dialogs, audio controls, data tables, import errors, and admin form
systems belong to their approved phases. Document a future need without creating
an unused placeholder.
