# Graduation Stage System Agent Guide

Read `GRADUATION_STAGE_SYSTEM_AI_AGENT_DEVELOPMENT_PLAYBOOK.md`,
`docs/requirements.md`, `docs/architecture.md`, and the relevant files under
`docs/ui/` before changing an affected workflow.

## Permanent boundaries

- Work only in the explicitly approved phase. Do not partially build a future
  route or feature.
- Preserve the single Laravel application with React through Inertia, Vite,
  PostgreSQL, Reverb, and Echo.
- PostgreSQL is authoritative. HTTP performs commands and recovery reads;
  broadcasts announce committed changes.
- Keep runtime operation local. Do not add CDN, hosted font, cloud media,
  polling, or other internet runtime dependencies.
- Inspect before editing and preserve unrelated user changes.

## UI rules

- Follow `docs/ui/design-system.md`, `ux-rules.md`,
  `screen-specifications.md`, `content-rules.md`, `accessibility.md`, and
  `responsive-rules.md`.
- Give every screen one dominant purpose and use realistic data and failure
  states.
- Avoid generic AI/SaaS patterns: decorative dashboards, fake metrics, equal
  card grids, cards inside cards, gradients, glass effects, excessive pills,
  excessive radius/shadow, icons beside every heading, and marketing copy.
- Keep `/stage` free of navigation, operator controls, technical warnings,
  credentials, QR tokens, student numbers, and debug data.
- Do not add a UI library or parallel token system without approval.

## Verification

Run relevant Laravel tests, Pint, ESLint, and the Vite production build. For
frontend work, open the real route in a browser, exercise required states, and
inspect the viewports in `docs/ui/responsive-rules.md`. Use
`docs/ui/ui-testing-checklist.md` and `visual-review-checklist.md`; update
`CHANGELOG_UI.md`.

Common commands:

```bash
docker compose exec -T app php artisan test
docker compose exec -T app vendor/bin/pint --test
npm run lint
npm run build
node tests/Support/phase3-browser-check.mjs
```

Never claim a test, route, state, or viewport was inspected unless it actually
was. Stop after the assigned phase and wait for human approval.
