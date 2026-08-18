# Connict GSS — Client Deployment Refactor Plan

## Purpose

Transform the existing Connict Graduation Stage System from a developer-oriented Laravel/Docker deployment into a client-ready Windows application that can be installed and operated by non-technical school staff.

Target client experience:

```text
ConnictGSS-Setup.exe
        ↓
Install
        ↓
Desktop shortcut: Connict GSS
        ↓
Double-click
        ↓
System starts automatically
        ↓
Connict GSS opens
```

The client must not need to manually install or configure PHP, Composer, Node.js, npm, Python, PostgreSQL, Docker, Docker Compose, Laravel, Reverb, Caddy, TTS dependencies, environment variables, or database migrations.

Only the main/server laptop requires Connict GSS installation. Scanner, stage, stage-control, and other operator devices continue accessing the application through browsers on the local network.

---

## Architecture Decision

### Do not rewrite Laravel to Python

Preserve the existing Laravel + React + PostgreSQL + Reverb architecture and the current scanner, queue, stage-control, CSV import, QR generation, TTS, backup, auditing, authentication, and authorization behavior.

The deployment refactor changes **how Connict GSS is distributed and started**, not how the application works.

The target Windows architecture is:

```text
                  WINDOWS SERVER LAPTOP

┌──────────────────────────────────────────────────────┐
│                    Connict GSS                       │
│                                                      │
│  ConnictGSS.exe          Launcher / System Manager   │
│          │                                           │
│          ├── PostgreSQL                              │
│          ├── Laravel / FrankenPHP                    │
│          ├── Laravel Reverb                          │
│          ├── Laravel Queue Worker                    │
│          ├── Local TTS Service                       │
│          └── Local HTTPS / WebSocket Proxy           │
│                                                      │
└──────────────────────┬───────────────────────────────┘
                       │
                  Local Network
                       │
           ┌───────────┼────────────┐
           │           │            │
           ▼           ▼            ▼
       Scanner      Stage       Stage Control
       Browser      Browser       Browser
```

---

## Primary Deliverables

The completed distribution work should produce:

```text
ConnictGSS-Setup-x.y.z.exe
```

The installer should place application binaries under:

```text
C:\Program Files\Connict GSS\
```

Persistent institutional data must be stored separately under:

```text
C:\ProgramData\ConnictGSS\
├── database\
├── storage\
│   ├── graduates\
│   ├── photos\
│   ├── audio\
│   └── announcements\
├── backups\
├── certificates\
├── configuration\
└── logs\
```

Application upgrades must never overwrite or remove institutional data.

---

## Supported Platform — Version 1

- Windows 10/11
- x86-64
- Machine-wide installation
- Fully local/offline-capable ceremony runtime
- Dedicated LAN deployment
- Internet not required during ceremonies

Linux and macOS packaging are outside the first milestone.

---

# Implementation Phases

## Phase 0 — Stabilize the Repository

1. Use `main` as the canonical application branch.
2. Perform all packaging work on `feature/windows-client-distribution`.
3. Record the baseline commit SHA.
4. Run the full Laravel test suite.
5. Run frontend lint.
6. Run Playwright/browser tests.
7. Run the Vite production build.
8. Verify the current Docker deployment.
9. Verify scanner → queue → stage behavior.
10. Verify Reverb reconnect and HTTP state recovery.
11. Verify backup/restore.
12. Verify TTS preparation.

**Exit criteria:** the current application is healthy before deployment changes begin.

---

## Phase 1 — Separate Application Code From Persistent Data

Introduce a configurable persistent root, for example:

```text
GSS_DATA_DIR=C:\ProgramData\ConnictGSS
```

Move or remap all production-writable data to this location:

- Laravel storage
- graduate media
- generated announcement audio
- logs
- backups
- certificates
- persistent configuration
- temporary operational data that must survive application upgrades

Do not keep mutable production data inside `C:\Program Files\Connict GSS`.

**Exit criteria:** replacing the application runtime does not remove or reset institutional data.

---

## Phase 2 — Create a Dedicated Windows Distribution Structure

Add:

```text
distribution/
└── windows/
    ├── config/
    ├── installer/
    ├── launcher/
    ├── runtime/
    ├── services/
    └── scripts/
```

Keep Docker, Composer, npm, Vite development tooling, and developer `.env` workflows for development only.

The production distribution must not require source-code development tools.

---

## Phase 3 — Precompile the React Frontend

The client must never run:

```bash
npm install
npm run build
```

The release pipeline must run:

```bash
npm ci
npm run build
```

before packaging.

Ship only the compiled frontend assets required by Laravel.

**Exit criteria:** the installed application starts with no Node.js/npm dependency.

---

## Phase 4 — Preinstall Laravel Production Dependencies

The release pipeline should run:

```bash
composer install \
  --no-dev \
  --prefer-dist \
  --optimize-autoloader \
  --no-interaction
```

Composer must not exist on the client device.

Exclude unnecessary development files from the installer payload, including tests, `.git`, `node_modules`, screenshots, temporary build files, and development caches.

---

## Phase 5 — Remove Docker as a Client Requirement

Keep Docker for development and internal QA.

Do not require Docker Desktop or Docker Compose on the client machine.

Replace the current production container orchestration with packaged Windows runtime components managed directly by Connict GSS.

---

## Phase 6 — Package Laravel With FrankenPHP

Use FrankenPHP as the bundled PHP/web runtime for the Windows distribution.

For the first release, prefer a bundled FrankenPHP runtime plus the prebuilt Laravel application instead of immediately forcing the complete system into one monolithic executable.

Preserve:

- Laravel HTTP routing
- HTTPS
- static asset serving
- reverse proxy behavior
- local certificate support
- Reverb WebSocket proxying

Keep Artisan available internally for controlled migrations, diagnostics, and maintenance.

---

## Phase 7 — Preserve Laravel Reverb

Do not replace the realtime architecture during packaging.

Continue using:

- Laravel Reverb
- Laravel Echo
- WebSockets

Run Reverb as a managed background process, preferably on loopback only, for example:

```text
127.0.0.1:8080
```

External ceremony devices should connect through the public HTTPS/WSS endpoint rather than the internal Reverb port.

---

## Phase 8 — Preserve the Queue Worker

Run the TTS preparation queue worker as a supervised background process:

```bash
php artisan queue:work database --queue=tts-preparation
```

The process manager must restart it if it unexpectedly exits.

---

## Phase 9 — Bundle PostgreSQL

Do not replace PostgreSQL with SQLite.

Preserve PostgreSQL because the system relies on transactional correctness, row/advisory locking, queue ordering, and ceremony state consistency.

Bundle PostgreSQL binaries with Connict GSS.

Store the database cluster under:

```text
C:\ProgramData\ConnictGSS\database
```

### First-install initialization

1. Initialize the PostgreSQL data directory.
2. Generate a strong random local database password.
3. Create the Connict GSS database.
4. Create the application database user.
5. Restrict PostgreSQL to loopback.
6. Run Laravel migrations.
7. Verify database connectivity.

Register it using an application-specific service name such as:

```text
ConnictGSS-PostgreSQL
```

PostgreSQL must never be exposed to the ceremony LAN.

---

## Phase 10 — Bundle the Local TTS Runtime

Package the existing offline TTS runtime ahead of time, including all required runtime dependencies and models.

The client installation must not perform:

```text
pip install
model download
HuggingFace download
```

The TTS service should listen only on loopback, for example:

```text
127.0.0.1:8880
```

A TTS startup failure must not prevent core ceremony operations. The system must retain its live-announcer/manual-audio fallback.

---

## Phase 11 — Add Windows Service Supervision

Run background components without visible terminal windows.

Suggested services/process identities:

```text
ConnictGSS-PostgreSQL
ConnictGSS-Web
ConnictGSS-Reverb
ConnictGSS-Queue
ConnictGSS-TTS
```

Use reliable Windows service/process supervision so components:

- start predictably;
- restart after crashes;
- stop cleanly;
- write diagnostics;
- do not show console windows to the user.

---

## Phase 12 — Build `ConnictGSS.exe`

Create a lightweight Windows launcher/system manager.

Responsibilities:

```text
ConnictGSS.exe
    ├── check installed version
    ├── check PostgreSQL
    ├── check Laravel/web runtime
    ├── check Reverb
    ├── check queue worker
    ├── check TTS
    ├── detect LAN address
    ├── verify certificates
    ├── display health
    ├── start/stop/restart managed components
    └── open Connict GSS
```

### Suggested UI

```text
┌─────────────────────────────────────────┐
│              CONNICT GSS                │
│         Graduation Stage System         │
│                                         │
│  System Status                          │
│                                         │
│  ● Database                Ready        │
│  ● Application             Ready        │
│  ● Realtime                Ready        │
│  ● Announcement Engine     Ready        │
│                                         │
│  Server Address                         │
│  https://192.168.50.10                  │
│                                         │
│       [ Open Connict GSS ]              │
│                                         │
│  [ Backup ]               [ Settings ]  │
└─────────────────────────────────────────┘
```

User-facing failures must use plain language. Detailed technical errors should be available only under diagnostics.

---

## Phase 13 — Automatic LAN Address Detection

Detect the active private LAN IPv4 address automatically.

Display generated links for:

- Admin
- Scanner
- Stage
- Stage Control
- Device onboarding

Do not require staff to manually edit `.env` when the network changes.

Implement a controlled network reconfiguration flow that:

1. detects the new LAN address;
2. regenerates runtime configuration;
3. updates Reverb allowed origins;
4. updates HTTPS/certificate configuration if needed;
5. restarts affected processes;
6. runs health checks.

---

## Phase 14 — Preserve Secure Local HTTPS

Public ceremony network exposure should be limited to the approved HTTPS/device-onboarding endpoints.

Internal components must remain loopback-only:

- PostgreSQL
- TTS
- internal Laravel runtime port
- internal Reverb port

Preserve the existing controlled certificate/device onboarding workflow.

---

## Phase 15 — First-Run Setup Wizard

On an uninitialized installation, show a setup wizard instead of exposing configuration files.

Collect only user-friendly information such as:

- institution name;
- first administrator name;
- administrator username;
- administrator password.

The wizard should automatically:

1. generate application secrets;
2. generate database credentials;
3. generate Reverb credentials;
4. initialize PostgreSQL;
5. configure persistent storage;
6. execute migrations;
7. create the first administrator;
8. configure LAN settings;
9. initialize HTTPS certificates;
10. run health checks;
11. mark the installation initialized.

Never log plaintext secrets or passwords.

---

## Phase 16 — Remove Manual `.env` Editing From Client Workflow

Generate runtime configuration automatically and store it under:

```text
C:\ProgramData\ConnictGSS\configuration\
```

Protect sensitive configuration with appropriate Windows filesystem permissions.

Expose configuration changes through the launcher or application UI rather than asking users to edit environment files.

---

## Phase 17 — Automate Safe Database Migrations

Replace the current manual migration decision with a controlled upgrade sequence:

```text
Stop application writes
        ↓
Create verified backup
        ↓
Install new runtime
        ↓
Run pending migrations
        ↓
Run health checks
        ↓
Start services
```

Never run destructive migration/reset commands automatically.

If migration fails, stop the upgrade and preserve the database, verified backup, old release, and logs.

---

## Phase 18 — Integrate Backup/Restore Into the Launcher

Reuse the existing Connict GSS backup/restore mechanisms.

Before upgrades, migrations, or high-risk configuration changes, create a verified backup.

Expose administrator actions such as:

```text
Backup Now
Verify Latest Backup
Open Backup Folder
```

---

## Phase 19 — Build the Windows Installer

Use Inno Setup for the initial installer.

Target artifact:

```text
ConnictGSS-Setup-1.0.0.exe
```

Suggested source:

```text
distribution/windows/installer/connict-gss.iss
```

Installer responsibilities:

1. verify supported Windows architecture;
2. request administrator privileges;
3. create application directories;
4. create persistent data directories;
5. install the web/PHP runtime;
6. install the Laravel application;
7. install PostgreSQL binaries;
8. install the TTS runtime;
9. install service/process wrappers;
10. register background services;
11. initialize first-install configuration;
12. configure required Windows firewall rules;
13. create Start Menu shortcut;
14. create desktop shortcut;
15. install an uninstaller;
16. launch Connict GSS after setup.

---

## Phase 20 — Safe Uninstall Behavior

Normal uninstall should remove:

- application runtime;
- Windows services;
- shortcuts.

By default it must retain:

- database data;
- graduate media;
- ceremony data;
- backups;
- persistent configuration.

Provide complete institutional-data deletion only as a separate, explicit, strongly confirmed action.

---

## Phase 21 — Add Application Versioning

Use semantic versions:

```text
1.0.0
1.0.1
1.1.0
2.0.0
```

Expose version information in:

- launcher;
- admin dashboard;
- diagnostics;
- backup manifest.

Each release must record the source Git commit used to build it.

---

## Phase 22 — Support In-Place Upgrades

Allow a newer installer to upgrade an existing Connict GSS installation.

Example:

```text
Installed: 1.0.0
Installer: 1.1.0
```

Upgrade must use the controlled backup → migration → health-check flow and preserve institutional data.

Cloud auto-update is not required for Version 1.

---

## Phase 23 — Add Diagnostics

Add a launcher diagnostics screen with:

- application version;
- Windows version;
- server LAN address;
- database status/version;
- web runtime status;
- Reverb status;
- queue worker status;
- TTS status;
- storage availability;
- available disk space;
- certificate status;
- latest backup;
- latest health-check result.

Provide an **Export Diagnostics** function that creates a support ZIP without passwords, raw QR tokens, encryption keys, unnecessary student personal data, or raw database contents.

---

## Phase 24 — Improve Failure Recovery

Handle common failures through the launcher.

### Database failure

- attempt restart;
- health-check;
- expose diagnostics if recovery fails.

### Reverb failure

- restart Reverb;
- preserve HTTP-authoritative state recovery.

### TTS failure

- report degraded announcement functionality;
- keep ceremony operations available.

### Port conflict

- identify the conflicting port/process;
- present a user-readable corrective action.

### Network/IP change

- regenerate runtime configuration;
- restart affected services;
- refresh displayed connection URLs.

---

## Phase 25 — Create a Repeatable Release Pipeline

Recommended build pipeline:

```text
Checkout release commit
        ↓
Run PHP tests
        ↓
Run frontend lint
        ↓
Run browser tests
        ↓
Composer production install
        ↓
Vite production build
        ↓
Prepare Laravel runtime
        ↓
Prepare PostgreSQL runtime
        ↓
Prepare TTS runtime
        ↓
Build launcher
        ↓
Build installer
        ↓
Install on clean Windows VM
        ↓
Run smoke tests
        ↓
Generate SHA-256 checksum
        ↓
Publish release artifact
```

Do not build client releases by manually copying files from a developer machine.

---

## Phase 26 — Add Installer CI

GitHub Actions should eventually create tagged release installers.

Example:

```text
v1.0.0 tag
   ↓
test
   ↓
build
   ↓
package
   ↓
verify
   ↓
release
```

The installer must map to an exact source commit and reproducible build process.

---

## Phase 27 — Code Signing

Before broad institutional/commercial distribution, sign:

- `ConnictGSS.exe`;
- `ConnictGSS-Setup.exe`;
- other user-facing executable components where applicable.

Treat code signing as a release-readiness requirement.

---

## Phase 28 — Clean-Machine Testing

Test the installer on clean Windows virtual machines that do not contain:

- PHP;
- Composer;
- Node.js;
- npm;
- Python;
- PostgreSQL;
- Docker;
- Git;
- the source repository.

Install only `ConnictGSS-Setup.exe`.

---

## Phase 29 — Deployment Acceptance Testing

Test the full installed application, including:

### Installation

- fresh install;
- upgrade install;
- reinstall/repair behavior;
- uninstall with data preserved.

### Core application

- login and permissions;
- ceremony management;
- student management;
- CSV import;
- media handling;
- QR generation;
- scanner;
- queue;
- present next;
- complete-and-present-next;
- skip;
- return;
- holding screen;
- replay;
- stage design;
- audit history;
- reports;
- backups.

### Realtime

- scanner → queue;
- queue → stage control;
- stage control → stage;
- WebSocket interruption;
- reconnect;
- HTTP state recovery.

### TTS

- generation;
- review;
- approval;
- playback;
- TTS service failure fallback.

### Networking

- server laptop;
- scanner laptop;
- stage laptop;
- stage-control laptop;
- phone onboarding;
- no-internet operation;
- router restart;
- server IP change.

---

## Phase 30 — Ceremony Reliability Test

Run a simulated ceremony with at least 500 sample graduates.

Exercise repeated and concurrent:

- scan;
- queue;
- present;
- complete;
- skip;
- return;
- replay;
- holding;
- reconnect operations.

Test:

- duplicate scans;
- rapid scans;
- simultaneous operator actions;
- network interruptions;
- Reverb restart;
- browser refresh;
- server restart;
- TTS failure;
- unavailable media;
- temporary database interruption.

The packaging work must not weaken existing PostgreSQL transaction guarantees.

---

## Phase 31 — Upgrade Reliability Test

Create realistic ceremony data in one release, then upgrade to the next release.

Verify preservation of:

- users;
- ceremonies;
- students;
- QR identities;
- queue history;
- stage history;
- audit records;
- photos;
- audio;
- TTS approvals;
- stage designs;
- backups.

An application update must never behave like a fresh installation.

---

## Phase 32 — Security Review

Verify that:

- PostgreSQL is loopback-only;
- TTS is loopback-only;
- internal web/Reverb ports are not exposed directly;
- HTTPS is used across ceremony devices;
- secrets are randomly generated;
- passwords are not logged;
- QR tokens remain protected;
- backups remain access-controlled;
- production debug mode is disabled;
- test/development routes are unavailable;
- development credentials are not shipped.

---

## Phase 33 — Client UX Review

Ask non-technical users to perform these tasks without a developer guiding them:

- install Connict GSS;
- launch Connict GSS;
- complete first-run setup;
- import graduates;
- connect scanner laptop;
- connect stage laptop;
- start a ceremony;
- create a backup;
- restart the application;
- upgrade the application.

If normal installation or operation requires PowerShell, Command Prompt, Composer, Docker, a database client, manual `.env` editing, or developer intervention, the deployment UX is not complete.

---

## Phase 34 — Documentation

Ship client-facing documentation for:

- Quick Start;
- Network Setup;
- Scanner Setup;
- Stage Setup;
- Backup/Restore;
- Troubleshooting.

The primary Quick Start must be written for registrar/graduation committee personnel rather than software developers.

---

# Recommended Repository Structure

```text
connict_gss/
│
├── app/
├── bootstrap/
├── config/
├── database/
├── resources/
├── routes/
├── tests/
│
├── distribution/
│   └── windows/
│       ├── installer/
│       │   └── connict-gss.iss
│       ├── launcher/
│       ├── config/
│       ├── services/
│       └── scripts/
│
├── docs/
│   ├── client-deployment-refactor-plan.md
│   ├── client-installation.md
│   ├── client-network-setup.md
│   └── client-troubleshooting.md
│
└── ...
```

Generated installer/runtime binaries should normally be release artifacts rather than committed source files.

---

# Implementation Milestones

Implement in this order:

```text
MILESTONE 1
Repository baseline + distribution skeleton

MILESTONE 2
External persistent storage architecture

MILESTONE 3
Native Windows Laravel runtime

MILESTONE 4
Bundled PostgreSQL

MILESTONE 5
Reverb + queue worker supervision

MILESTONE 6
Bundled offline TTS

MILESTONE 7
ConnictGSS.exe launcher

MILESTONE 8
First-run setup wizard

MILESTONE 9
ConnictGSS-Setup.exe

MILESTONE 10
Upgrade + backup protection

MILESTONE 11
Clean-machine QA

MILESTONE 12
Client release candidate
```

Do not implement every phase simultaneously. Each milestone must leave the previous working system recoverable.

---

# Definition of Done

The deployment refactor is complete when a clean Windows laptop can receive:

```text
ConnictGSS-Setup.exe
```

and a non-technical user can:

```text
Install
   ↓
Open Connict GSS
   ↓
Complete first-run setup
   ↓
Connect ceremony devices
   ↓
Operate a graduation ceremony
```

without manually installing or configuring:

```text
PHP
Composer
Node.js
npm
Python
PostgreSQL
Docker
Docker Compose
```

and without:

```text
terminal commands
manual .env editing
manual migration commands
```

while preserving the existing:

- Laravel backend;
- React frontend;
- PostgreSQL authoritative state;
- Reverb/Echo realtime flow;
- queue worker;
- offline TTS;
- QR workflow;
- stage queue and stage control;
- backups;
- audit history;
- role-based access;
- local-network operation;
- offline ceremony capability.

---

# Engineering Principle

The goal is not to turn Connict GSS into a different application.

The goal is to turn the already-working application into a product that hides its infrastructure from the client.

Internal engineering can remain sophisticated:

```text
Laravel
React
PostgreSQL
WebSockets
Python TTS
```

while the client experience becomes:

```text
Install Connict GSS
Open Connict GSS
Run the ceremony
```

That separation between **internal engineering complexity** and **external operational simplicity** should guide every deployment decision.
