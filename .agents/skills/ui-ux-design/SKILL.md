---
name: ui-ux-design
description: Plan deliberate, accessible Graduation Stage System interfaces before implementation. Use for new or changed screens, layout decisions, textual wireframes, information hierarchy, UI states, responsive behavior, realistic content, or acceptance criteria in this repository.
---

# UI/UX Design

Design within the approved phase and project architecture.

## Workflow

1. Read `AGENTS.md`, the current phase requirements, and the relevant files in
   `docs/ui/`.
2. Confirm route, primary user, device, time pressure, goal, dominant
   information, actions, failure risks, and prohibited content.
3. List loading, empty, success, error, disconnected, recovered, long-content,
   and missing-content states that apply.
4. If no layout is approved, produce two or three structurally different textual
   options. Compare hierarchy, advantages, risks, and best use.
5. Select or obtain approval for a textual wireframe before implementation.
6. Use realistic ceremony data, including long names/programs and missing
   optional content.
7. Define responsive, keyboard, accessibility, recovery, and measurable
   acceptance criteria.
8. Implement only the current phase using documented semantic tokens and the
   smallest necessary component set.

## Guardrails

- Preserve Laravel, React/Inertia, Vite, PostgreSQL, Reverb/Echo, and local-only
  runtime.
- Do not invent future actions to make a screen look complete.
- Avoid generic dashboards, equal card grids, nested cards, gradients, glass,
  fake analytics, excessive pills/shadows/radius, marketing copy, and
  unnecessary animation.
- Keep audience screens free of controls and technical information.
- Do not add a UI package or token system without explicit approval.

## Handoff

Record the selected hierarchy, states, viewports, and acceptance criteria in the
appropriate `docs/ui/` file. Require `$ui-ux-review` and `$visual-qa` after
implementation.
