# Graduation Stage System

Status: **Phase 2 — Authentication, Roles, and Core Data Model awaiting human
approval**

The Graduation Stage System is a local-network-only Laravel application that will
support reliable QR-driven graduation stage operations. Phases 1 and 2 establish
the single-application foundation, session authentication, role authorization,
and constrained PostgreSQL domain model.

A public foundation page, login page, and minimal protected dashboard exist.
Operational ceremony administration, scanning, queue commands, Reverb/Echo, stage
interfaces, audio, reports, and backup workflows remain intentionally
unimplemented.

## Approved architecture

- One Laravel application containing React through Inertia.js
- Vite for local frontend development and production asset compilation
- PostgreSQL as the only supported authoritative database
- Session-based web application structure
- HTTP for later commands and authoritative reads
- Reverb and Echo reserved for a later approved phase
- No runtime CDN, hosted font, cloud service, or internet dependency
- Dedicated local-network deployment across server, scanner, and stage laptops

SQLite is not an application or test fallback. The standard Laravel SQLite
connection remains available in framework configuration, but PostgreSQL is the
configured default and all executed database checks use PostgreSQL.

## Implemented foundation

- Laravel 13 application scaffolded directly in this repository
- Inertia Laravel adapter and middleware
- React entry point and one minimal Inertia page
- Vite React compilation with local CSS and no externally hosted assets
- PostgreSQL development and testing databases
- Session-based login/logout with login throttling and inactive-account rejection
- Five backed-enum roles with server-enforced baseline gates
- Nine core domain tables with PostgreSQL checks, foreign keys, indexes, and
  partial unique indexes
- Eloquent relationships, enum/date/JSON casts, factories, and development seed
  data
- PHPUnit tests, including explicit PostgreSQL test-database verification
- Laravel Pint and ESLint configuration
- Project-scoped Docker development services for PHP and PostgreSQL
- Native PHP/PostgreSQL setup instructions

## Verified versions

Verified on 2026-07-28:

| Component | Verified version |
| --- | --- |
| Host operating system | Ubuntu 24.04.4 LTS |
| Host PHP | 8.3.6 |
| Host Composer | 2.7.1 |
| Docker PHP | 8.3.32 |
| Docker Composer | 2.10.2 |
| Laravel framework | 13.23.0 |
| PostgreSQL | 16.14 |
| Inertia Laravel adapter | 3.1.1 |
| Inertia React adapter | 3.6.1 |
| React / React DOM | 19.2.8 |
| Node.js | 24.14.0 |
| npm | 11.9.0 |
| Vite | 8.1.5 |
| Laravel Vite plugin | 3.1.3 |
| ESLint | 10.8.0 |
| Docker Engine / Compose | 29.6.1 / 2.40.3 |

Laravel 13 requires PHP 8.3 or newer. Node.js must satisfy Vite's supported range;
the verified Node 24 release does.

## Prerequisites

### Docker development path

- Git
- PHP 8.3+ and Composer 2 for installing PHP dependencies on the host
- Node.js 24+ and npm 11+ for the verified frontend workflow
- Docker Engine with the Compose plugin

The PHP application container includes `pdo_pgsql` and the other required PHP
extensions. PostgreSQL is bound to `127.0.0.1:54329` by default and is not exposed
to other network devices.

### Native development path

Install PostgreSQL 16+, its client tools, and PHP 8.3 with these extensions:

- BCMath
- Ctype
- cURL
- DOM
- Fileinfo
- Filter
- Hash
- Intl
- Mbstring
- OpenSSL
- PCRE
- PDO
- `pdo_pgsql`
- Session
- Tokenizer
- XML
- Zip

On Ubuntu 24.04, the missing PostgreSQL pieces are normally installed with:

```bash
sudo apt-get update
sudo apt-get install postgresql postgresql-client php8.3-pgsql
```

System package installation requires administrator access. Do not substitute
SQLite if PostgreSQL or `pdo_pgsql` is unavailable.

## First-time setup with Docker

1. Create the ignored local environment file:

   ```bash
   cp .env.example .env
   ```

2. Replace `DB_PASSWORD=change-me` in `.env` with a local development password.
   Set `GSS_SEED_PASSWORD` to a different local-only password of at least 12
   characters. Keep `DB_HOST=database` and `DB_PORT=5432` for Docker.

3. Install locked PHP and frontend dependencies:

   ```bash
   composer install
   npm install
   ```

4. Build and start the local services:

   ```bash
   docker compose up --detach --build
   ```

   On the first start, PostgreSQL creates both:

   - `graduation_stage_system`
   - `graduation_stage_system_testing`

5. Generate the local application key:

   ```bash
   docker compose exec app php artisan key:generate
   ```

6. Run all migrations and create the documented development sample data:

   ```bash
   docker compose exec app php artisan migrate --seed
   docker compose exec --env DB_DATABASE=graduation_stage_system_testing app php artisan migrate
   ```

7. Compile the production frontend assets:

   ```bash
   npm run build
   ```

8. Open <http://127.0.0.1:8000>.

Inspect service health with:

```bash
docker compose ps
docker compose logs --tail=100 app database
```

Stop the services without deleting PostgreSQL data:

```bash
docker compose down
```

The named PostgreSQL volume is intentionally retained.

## Native PostgreSQL setup

Create a least-privilege local role and separate databases. Replace the sample
password before running the SQL:

```sql
CREATE ROLE gss_app LOGIN PASSWORD '<strong-local-password>';
CREATE DATABASE graduation_stage_system OWNER gss_app;
CREATE DATABASE graduation_stage_system_testing OWNER gss_app;
```

For native execution, change these `.env` values:

```dotenv
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=graduation_stage_system
DB_TEST_DATABASE=graduation_stage_system_testing
DB_USERNAME=gss_app
DB_PASSWORD=<strong-local-password>
GSS_SEED_PASSWORD=<different-local-development-password>
```

Then run:

```bash
php artisan key:generate
php artisan migrate --seed
DB_DATABASE=graduation_stage_system_testing php artisan migrate
```

PostgreSQL should listen only on the server/local interfaces required by the
approved deployment. Do not configure it for public access or expose credentials
to React.

## Environment configuration

Important environment values:

| Variable | Purpose | Docker default |
| --- | --- | --- |
| `APP_KEY` | Laravel encryption key; generated locally and never committed | Empty in example |
| `APP_URL` | Development application URL | `http://localhost:8000` |
| `APP_TIMEZONE` | Application date/time zone | `Asia/Manila` |
| `DB_CONNECTION` | Laravel database driver | `pgsql` |
| `DB_HOST` | Database host from the PHP runtime | `database` |
| `DB_PORT` | PostgreSQL port inside the Compose network | `5432` |
| `DB_FORWARD_PORT` | Localhost-only forwarded database port | `54329` |
| `DB_DATABASE` | Development database | `graduation_stage_system` |
| `DB_TEST_DATABASE` | Testing database initialized by Docker | `graduation_stage_system_testing` |
| `DB_USERNAME` | Local application database role | `gss_app` |
| `DB_PASSWORD` | Local secret; must be changed | `change-me` placeholder |
| `GSS_SEED_PASSWORD` | Password assigned to development-only seeded operators | Empty; required only when seeding |

`.env` and other non-example environment files are ignored by Git. Never commit a
real application key, database password, or production credential.

## Authentication and roles

Laravel's built-in `web` guard provides cookie and database-session
authentication. Login regenerates the session ID; logout invalidates the session
and regenerates the CSRF token. Login attempts are limited to five per
email/address key before Laravel temporarily throttles further attempts.

Public registration is disabled. Accounts are created administratively or through
the development seeder. An inactive account cannot log in; if an authenticated
account is later deactivated, active-user middleware ends its session on the next
protected request.

The approved roles and initial gates are:

| Role | Baseline gate |
| --- | --- |
| `administrator` | All defined baseline abilities, including `manage-users` |
| `data_encoder` | `manage-ceremony-data` |
| `scanner_operator` | `operate-scanner` |
| `stage_operator` | `operate-stage` |
| `technical_operator` | `manage-technical-operations` |

These gates are a Phase 2 authorization foundation, not the final permission
matrix. Laravel enforces them server-side. React receives only the authenticated
user's ID, name, email, role value/label, and active flag.

## Core database model

Phase 2 adds `ceremonies`, `students`, `devices`, `stage_queue_items`,
`stage_states`, `scan_logs`, `stage_events`, and `audit_logs`, and extends
`users` with `role` and `is_active`.

Important relationships include ceremony-to-students, ceremony-to-queue-items,
one stage state per ceremony, student-to-queue/scan records, optional operator and
device references on operational records, and preserved audit/event actors through
nullable foreign keys.

PostgreSQL enforces:

- globally unique QR tokens;
- ceremony-scoped unique student and sequence numbers;
- valid roles, ceremony statuses, presentation modes, eligibility values, queue
  statuses, stage display/audio states, scan results, and device states;
- at most one active queue row per ceremony/student, where active means `waiting`,
  `ready`, `presenting`, or `announced`;
- at most one `presenting` row per ceremony;
- one stage-state row per ceremony; and
- ceremony-scoped queue, stage-state, event, and scan references, plus positive
  sequence/queue-position checks.

Eligibility values (`pending`, `eligible`, `ineligible`) and the minimal technical
stage display/audio values are provisional until the institution approves their
final workflow vocabularies.

## Development seed data

Set `GSS_SEED_PASSWORD` in the ignored `.env` before seeding. The seeder rejects
missing values or values shorter than 12 characters. It creates or updates these
local-only accounts, all using that environment-controlled password:

| Email | Role |
| --- | --- |
| `admin@example.test` | Administrator |
| `encoder@example.test` | Data encoder |
| `scanner@example.test` | Scanner operator |
| `stage@example.test` | Stage operator |
| `technical@example.test` | Technical operator |

It also creates one prepared sample ceremony, five fictional sample students, and
one idle stage state. It intentionally creates no queue items.

Run or rerun the seeder:

```bash
docker compose exec app php artisan db:seed
```

Reset the development database and seed from scratch only when discarding its
current local data is intended:

```bash
docker compose exec app php artisan migrate:fresh --seed
```

Refresh the isolated testing database without seed data:

```bash
docker compose exec --env DB_DATABASE=graduation_stage_system_testing app php artisan migrate:fresh
```

## Application and frontend commands

Docker application server:

```bash
docker compose up --detach app database
```

Vite development server:

```bash
npm run dev
```

The browser remains at <http://127.0.0.1:8000>; Laravel detects Vite's development
server while it is running.

Native combined development server, after native PostgreSQL and `pdo_pgsql` are
configured:

```bash
composer run dev
```

Production asset build:

```bash
npm run build
```

The Vite development server is for development only and must not run during a
ceremony.

## Tests and quality checks

Run backend tests inside the PHP container so the verified PostgreSQL driver and
testing database are used:

```bash
docker compose exec app php artisan test
```

The PHPUnit configuration forces `DB_CONNECTION=pgsql` and
`DB_DATABASE=graduation_stage_system_testing`. A feature test also queries
`current_database()` so SQLite cannot silently satisfy the suite.

Run formatting and linting:

```bash
vendor/bin/pint --test
npm run lint
```

Apply automatic formatting when intended:

```bash
vendor/bin/pint
npm run lint:fix
```

Useful verification commands:

```bash
composer validate --strict --no-check-publish
composer audit
npm audit --audit-level=high
docker compose exec app php artisan about
docker compose exec app php artisan route:list
docker compose exec app php artisan db:show --database=pgsql
```

## Common setup problems

### `could not find driver`

The PHP runtime lacks `pdo_pgsql`. Use the provided PHP container or install the
native PHP PostgreSQL extension. Confirm with:

```bash
php -m | grep -i pgsql
```

### PostgreSQL connection refused

- Docker: use `DB_HOST=database` and `DB_PORT=5432`.
- Native PHP: use `DB_HOST=127.0.0.1` and the native PostgreSQL port.
- Confirm Docker health with `docker compose ps`.
- Confirm native readiness with `pg_isready`.

### Testing database does not exist

The Docker init script creates it only when the PostgreSQL volume is first
initialized. For an existing native/older development cluster, create
`graduation_stage_system_testing` explicitly and make `gss_app` its owner.

### Seeder reports that `GSS_SEED_PASSWORD` is missing

Add a local-only password of at least 12 characters to the ignored `.env`:

```dotenv
GSS_SEED_PASSWORD=<strong-local-development-password>
```

Do not place a production password in `.env.example`, the seeder, documentation,
or source control.

### `No application encryption key has been specified`

Run:

```bash
docker compose exec app php artisan key:generate
```

Use `php artisan key:generate` for native execution.

### Vite manifest not found

Install frontend dependencies and either build assets or run the development
server:

```bash
npm install
npm run build
```

### Local port conflict

Set `APP_FORWARD_PORT` or `DB_FORWARD_PORT` in `.env` to unused localhost ports,
then recreate the affected Compose service.

## Project documentation

- [Requirements baseline](docs/requirements.md)
- [Architecture baseline](docs/architecture.md)
- [AI-agent development playbook](Graduation_Stage_System_AI_Agent_Development_Playbook.md)

## Phase boundary

Phase 2 provides authentication, role authorization, and the constrained domain
foundation only. It does not provide ceremony/student CRUD, scanner endpoints,
queue commands, QR generation, CSV import, Reverb, Echo, stage interfaces, audio,
reports, backup automation, heartbeat networking, or deployment configuration.
Phase 3 may begin only after explicit human approval.
