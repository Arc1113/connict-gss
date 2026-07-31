# Graduation Stage System Agent Guide

Read `GRADUATION_STAGE_SYSTEM_AI_AGENT_DEVELOPMENT_PLAYBOOK.md`,
`docs/requirements.md`, `docs/architecture.md`

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
