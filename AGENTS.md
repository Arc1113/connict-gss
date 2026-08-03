# Repository Guidelines

Whatever actions you can do yourself, please do yourself. This includes starting the apps and performing verification.


## Project Structure & Module Organization

This is one Laravel 13 application with a React 19 frontend delivered through Inertia and Vite. Backend code lives in `app/`: HTTP code in `app/Http`, transactional workflows in `app/Actions`, and domain helpers in `app/Services` or `app/Support`. React pages, components, and browser utilities are under `resources/js`; styles are in `resources/css`. Routes live in `routes/`, schema and seed data in `database/`, and operational guidance in `docs/`.

Tests are split between `tests/Feature` and `tests/Unit`. Browser rehearsal scripts and shared test helpers live in `tests/Support`.

## Architecture Boundaries

PostgreSQL is authoritative. Send commands and recovery reads over HTTP, then broadcast committed changes through Reverb/Echo. Keep controllers thin and state transitions transactional. The system must remain local-network-only: do not add CDNs, cloud media, browser speech, polling, or SQLite fallbacks.

## Build, Test, and Development , creates `.env`, migrates, and builds assets.
- `docker compose up -d --build` starts Laravel, Reverb, and PostgreSQL.
- `docker compose exec -T app php artisan test` runs the PHPUnit suite.
- `docker compose exec -T app vendor/bin/pint --test` checks PHP formatting.
- `npm run lint` checks React and browser-support JavaScript with ESLint.
- `npm run build` creates the production Vite bundle.
Commands

- `composer setup` installs dependencies- `node tests/Support/phase11-browser-rehearsal.mjs` runs the browser rehearsal against a prepared stack.

## Coding Style & Naming Conventions

Follow `.editorconfig`: UTF-8, LF endings, four-space indentation, and two spaces for ordinary YAML. Pint formats PHP; ESLint checks JavaScript and JSX. Use PSR-4 namespaces, `PascalCase` classes and React components, `camelCase` functions, and descriptive action names such as `CompleteAndPresentNext`. Test files end with `Test.php`.

## Testing Guidelines

Use PHPUnit 12 with PostgreSQL’s testing database; do not substitute SQLite. Add feature tests for authorization, validation, persistence, and concurrent state changes, plus unit tests for isolated services. There is no numeric coverage threshold, but every regression or behavior change needs a test. Run the full suite, formatting check, lint, and build before review.

## Commit & Pull Request Guidelines

Recent commits use imperative subjects, optionally prefixed with `feat:` or `refactor:`. Keep commits focused. Pull requests should explain behavior and architectural impact, link the issue or phase, list migration or configuration changes, and include exact verification commands. Attach screenshots for UI changes and note remaining hardware-only checks.
