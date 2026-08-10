# Opening the Graduation Stage System

This is the starting guide for opening the Graduation Stage System in
development, during preparation, and on ceremony day. It covers the server,
browser workstations, installed Progressive Web App (PWA), and phone scanner.

The Graduation Stage System is a web application. It cannot be opened by
double-clicking an HTML file. PostgreSQL, Laravel, and Reverb must be running,
and the frontend must be available from either a production build or the Vite
development server.

The application is designed for a private local network. Do not expose it to
the public internet, expose PostgreSQL to client devices, replace PostgreSQL
with SQLite, or add a cloud runtime dependency.

## Choose your situation

| Situation | Start here |
| --- | --- |
| Setting up a development copy for the first time | [First-time development setup](#first-time-development-setup) |
| Reopening an existing development copy | [Normal development startup](#normal-development-startup) |
| Editing React or CSS with live refresh | [Frontend development with Vite](#frontend-development-with-vite) |
| Testing from several laptops before deployment | [Temporary multi-laptop development test](#temporary-multi-laptop-development-test) |
| Preparing the actual ceremony server for the first time | [First production or event deployment](#first-production-or-event-deployment) |
| Starting an already-installed event server normally | [Normal event-day startup](#normal-event-day-startup) |
| The desktop launcher is unavailable or failed | [Manual production startup](#manual-production-startup) |
| Opening a scanner, control, audience, or admin workstation | [Open each workstation](#open-each-workstation) |
| Connecting a phone as a camera scanner | [Open the phone camera scanner](#open-the-phone-camera-scanner) |
| Installing or reopening the application as a PWA | [Install or reopen the PWA](#install-or-reopen-the-pwa) |
| Recovering after a reboot or connection failure | [Reopen after a reboot](#reopen-after-a-reboot) or [Troubleshooting](#troubleshooting) |
| Closing the event server safely | [Safe event shutdown](#safe-event-shutdown) |

## Before you begin

### Know which environment you are using

This repository has two distinct operating modes:

| Mode | Environment file | Browser address | Intended use |
| --- | --- | --- | --- |
| Development | `.env` | `http://localhost:8000` by default | Coding, automated checks, and disposable sample data |
| Event deployment | `.env.production` | `https://SERVER_LAN_IP` | Rehearsal and ceremony operation on the private LAN |

Do not use the development configuration for a real ceremony. Production
startup validates `APP_ENV=production`, `APP_DEBUG=false`, HTTPS, a LAN address,
and non-placeholder secrets.

### Required software

For initial development setup, install:

- Docker with the Docker Compose plugin;
- PHP 8.3 or later and Composer;
- Node.js and npm;
- a current Chromium-based browser such as Google Chrome or Chromium.

The deployment server also uses Linux and systemd for automatic startup and the
desktop launcher. Package downloads require internet access during initial
preparation, but ceremony runtime does not require internet access.

### Always run commands from the repository root

Open a terminal in the directory containing `artisan`, `compose.yaml`,
`composer.json`, and `package.json`. Confirm the location before continuing:

```bash
pwd
test -f artisan
test -f compose.yaml
```

Commands in this guide use placeholder addresses and secrets. Replace
`SERVER_LAN_IP` with the server's fixed or reserved private-network address.
Never copy real passwords, application keys, Reverb secrets, or backup contents
into documentation, screenshots, or chat.

## Development

### First-time development setup

Use this workflow for a new local checkout or when the development database is
intentionally disposable.

1. Create the untracked development environment file:

   ```bash
   cp .env.example .env
   ```

2. Edit `.env` and replace these values:

   - `DB_PASSWORD` with a local database password, for example
     ``openssl rand -base64 18``;
   - `GSS_SEED_PASSWORD` with a development login password of at least 12
     characters, for example ``openssl rand -base64 18``;
   - `REVERB_APP_KEY` with a random local key, for example
     ``openssl rand -hex 16``;
   - `REVERB_APP_SECRET` with a random local secret, for example
     ``openssl rand -hex 32``.

   Keep the normal single-laptop settings:

   ```dotenv
   APP_ENV=local
   APP_DEBUG=true
   APP_URL=http://localhost:8000
   APP_BIND_HOST=127.0.0.1
   REVERB_ALLOWED_ORIGINS=localhost,127.0.0.1
   VITE_REVERB_HOST=
   ```

3. Install the locked PHP and JavaScript dependencies:

   ```bash
   composer install
   npm ci
   ```

4. Generate the Laravel application key:

   ```bash
   php artisan key:generate
   ```

5. Build the development containers:

   ```bash
   docker compose build
   ```

6. Start PostgreSQL and Laravel:

   ```bash
   docker compose up -d database app
   ```

7. Create a clean development database, seed the sample accounts and ceremony,
   and expose managed media through Laravel's public storage link:

   ```bash
   docker compose exec -T app php artisan migrate:fresh --seed
   docker compose exec -T app php artisan storage:link
   ```

   `migrate:fresh --seed` deletes all data in the selected database. Use it only
   for a disposable development database. Never run it against rehearsal or
   ceremony data.

8. Build the frontend and start Reverb:

   ```bash
   npm run build
   docker compose up -d reverb
   docker compose ps
   ```

9. Open the application:

   ```text
   http://localhost:8000
   ```

10. Sign in with one of the seeded email addresses and the password stored in
    your untracked `GSS_SEED_PASSWORD` value:

    | Email | Role | Landing page |
    | --- | --- | --- |
    | `admin@example.test` | Administrator | `/admin` |
    | `encoder@example.test` | Data encoder | `/admin/students` |
    | `scanner@example.test` | Scanner operator | `/scanner` |
    | `stage@example.test` | Stage operator | `/stage-control` |
    | `technical@example.test` | Technical operator | `/admin/scan-logs` |

### Normal development startup

Use this workflow when `.env`, dependencies, the database, and built assets
already exist.

1. Start the required containers:

   ```bash
   docker compose up -d database app reverb
   ```

2. Check their state:

   ```bash
   docker compose ps
   ```

3. Open `http://localhost:8000` in the browser.

4. If the page reports missing frontend assets after source changes, rebuild
   them and refresh:

   ```bash
   npm run build
   ```

5. When finished, stop the development containers without deleting the
   database volume:

   ```bash
   docker compose stop
   ```

Do not add `-v` to a normal stop or shutdown command. Removing the Compose
volume deletes the PostgreSQL data stored in it.

### Frontend development with Vite

Use Vite only while actively developing the React or CSS frontend. Ceremony
operation must use `public/build` and must not depend on Vite.

1. Start PostgreSQL, Laravel, and Reverb:

   ```bash
   docker compose up -d database app reverb
   ```

2. In a separate terminal at the repository root, start Vite:

   ```bash
   npm run dev
   ```

3. Keep that terminal running and open:

   ```text
   http://localhost:8000
   ```

4. Stop Vite with `Ctrl+C` when finished. To return to compiled assets, run:

   ```bash
   rm -f public/hot
   npm run build
   test ! -f public/hot
   ```

Only remove `public/hot` after stopping Vite. Never deploy with that file
present.

### Open several roles on one development computer

Authentication is session-based. Use a separate browser profile or private
window for each simultaneously active role so one login does not replace
another.

1. Open one profile as `scanner@example.test` at `/scanner`.
2. Open a second profile as `stage@example.test` at `/stage-control`.
3. Open a third profile as `stage@example.test` at `/stage`.
4. On `/stage`, choose **Enable ceremony audio** once for that browser tab.
5. Keep `/stage-control` out of the audience-facing window.

### Temporary multi-laptop development test

This HTTP workflow is suitable for controlled development testing on a private
LAN. Use the HTTPS deployment workflow for actual rehearsal and ceremony use.

1. Give the server laptop a fixed or DHCP-reserved address. The following
   example uses `192.168.50.10`.

2. Update `.env`:

   ```dotenv
   APP_URL=http://192.168.50.10:8000
   APP_BIND_HOST=0.0.0.0
   REVERB_ALLOWED_ORIGINS=localhost,127.0.0.1,192.168.50.10
   VITE_REVERB_HOST=
   VITE_REVERB_PORT=8080
   VITE_REVERB_SCHEME=http
   ```

3. Rebuild frontend assets because the `VITE_*` settings changed:

   ```bash
   npm run build
   ```

4. Recreate the browser-facing services:

   ```bash
   docker compose up -d --force-recreate app reverb
   docker compose ps
   ```

5. Allow inbound TCP ports `8000` and `8080` only from the dedicated test
   network. Do not expose PostgreSQL or port `54329` to client laptops.

6. From each client laptop, open these checks:

   ```text
   http://192.168.50.10:8000/up
   http://192.168.50.10:8000/login
   ```

7. Sign in from separate browser profiles and open the appropriate workstation
   route.

Camera and microphone browser permissions require a secure context. Use the
production HTTPS workflow for phone camera scanning or LAN microphone capture;
an insecure LAN HTTP address is not sufficient.

## Production and event deployment

### First production or event deployment

Use this workflow on the Linux server laptop before the final physical
rehearsal. Review the complete [deployment runbook](deployment.md) before
changing a live event server.

1. Connect the server to the dedicated event router, preferably by Ethernet,
   and reserve a stable private address such as `192.168.50.10`.

2. Create the untracked production configuration:

   ```bash
   cp .env.deployment.example .env.production
   php artisan key:generate --show
   ```

3. Edit `.env.production`:

   - paste the generated value into `APP_KEY`;
   - replace the database password, Reverb key, and Reverb secret;
   - set `APP_URL=https://SERVER_LAN_IP`;
   - set `GSS_HTTPS_HOST=SERVER_LAN_IP`;
   - set `REVERB_ALLOWED_ORIGINS=SERVER_LAN_IP` without a scheme or port;
   - keep `APP_ENV=production` and `APP_DEBUG=false`;
   - normally keep `VITE_REVERB_HOST` empty so browsers use the hostname from
     the page URL;
   - keep `VITE_REVERB_PORT=443` and `VITE_REVERB_SCHEME=https` when using the
     default deployment ports.

4. Before disconnecting from the internet, install dependencies and build the
   production frontend:

   ```bash
   composer install
   npm ci
   npm run build
   test -f public/build/manifest.json
   test ! -f public/hot
   ```

5. Prepare and validate the release:

   ```bash
   bash scripts/gss-build-release.sh --env-file .env.production
   ```

   During reviewed active development only, the release script also supports
   `--allow-dirty`. Do not use it merely to bypass an unexplained working-tree
   change.

6. Before applying migrations to an existing event database, create or verify
   a current backup and confirm that no graduate is being presented.

7. Build the deployment images, start the services, and apply reviewed pending
   migrations:

   ```bash
   bash scripts/gss-start.sh --env-file .env.production --build --migrate
   ```

   This command does not seed data and never runs `migrate:fresh`.

8. Run the deployment health check:

   ```bash
   bash scripts/gss-health-check.sh --env-file .env.production
   ```

9. Open the HTTPS application address printed by the startup script. Also test:

   ```text
   https://SERVER_LAN_IP/up
   https://SERVER_LAN_IP/login
   ```

10. Install and trust the exported public local certificate authority on every
    approved client device. Confirm the browser shows a trusted HTTPS
    connection before entering credentials.

11. Create named local accounts for the actual operators under `/admin/users`.
    Give each account only the required role and use passwords of at least 12
    characters. Do not use development sample credentials for the ceremony.

12. After the deployment is built and migrated, install automatic startup and
    the desktop launcher once:

    ```bash
    sudo bash scripts/gss-install-service.sh --env-file .env.production
    ```

13. Install the PWA on the approved workstations and complete the physical
    rehearsal before declaring the event ready.

### Normal event-day startup

Use this path after the one-time production setup and service installation are
complete.

1. Power on the dedicated router or switch.
2. Connect the server laptop to its approved network and reliable power.
3. Confirm that it received the fixed/reserved address used by
   `.env.production`.
4. Power on the server. The `graduation-stage-system` systemd service starts the
   deployment after Docker and the network are ready. It does not run migrations
   at boot.
5. Open **Graduation Stage System** from the Linux desktop application menu.
   The launcher checks the service, waits for Laravel's `/up` endpoint, and opens
   the configured HTTPS address.
6. If operating as the technical operator, run:

   ```bash
   bash scripts/gss-health-check.sh --env-file .env.production
   ```

7. Power on and connect the scanner, control, and stage devices.
8. Open each installed PWA or browser bookmark and complete the pre-event checks
   in [the event-day checklist](event-day-checklist.md).

Normal event startup does not require `npm run dev`, a Vite terminal, migrations,
or seeders.

### Manual production startup

Use this only when automatic startup has not been installed or the desktop
launcher cannot start the deployment.

1. Open a terminal on the server laptop at the repository root.
2. Start the already-built, already-migrated deployment:

   ```bash
   bash scripts/gss-start.sh --env-file .env.production --skip-migrate
   ```

3. Run the health check:

   ```bash
   bash scripts/gss-health-check.sh --env-file .env.production
   ```

4. Open the application URL printed by the startup command.

Use `--migrate` only during an approved maintenance window after verifying a
backup and reviewing the pending migrations. Use `--build` only when the
deployment image must be rebuilt from the prepared release.

## Open each workstation

For the examples below, the server address is `https://192.168.50.10`. Replace
it with the actual `APP_URL`. Every device must be connected to the dedicated
ceremony LAN, trust the local certificate, and show a secure HTTPS connection.

Use a separate browser profile or PWA installation for each operational role.
Do not place administrator credentials on the audience display, and do not
reuse a profile that is signed in as an incompatible role.

### Routes and roles

| Purpose | Example URL | Authorized role |
| --- | --- | --- |
| Login | `https://192.168.50.10/login` | Signed-out users |
| Administration | `https://192.168.50.10/admin` | Administrator |
| Graduate preparation | `https://192.168.50.10/admin/students` | Data encoder or administrator |
| Scanner | `https://192.168.50.10/scanner` | Scanner operator or administrator |
| Stage control | `https://192.168.50.10/stage-control` | Stage operator or administrator |
| Audience stage | `https://192.168.50.10/stage` | Stage operator or administrator |
| Operational scan logs | `https://192.168.50.10/admin/scan-logs` | Administrator, technical operator, or stage operator with the limited operational view |
| Device connections | `https://192.168.50.10/admin/device-connections` | Administrator or technical operator |
| Health endpoint | `https://192.168.50.10/up` | Network readiness check |

Opening `/` while signed out redirects to `/login`. After login, each named
role is sent to its authorized landing page. A `403 Forbidden` response usually
means the account does not have permission for that route; it does not mean the
server is offline.

### Scanner laptop

1. Connect the laptop to the dedicated LAN.
2. Open the installed PWA or `https://SERVER_LAN_IP/scanner`.
3. Sign in with the named scanner-operator account.
4. Connect the USB 2D scanner in HID keyboard mode.
5. Confirm the QR field is focused and the scanner sends an Enter or carriage-
   return suffix.
6. Test one valid, invalid, and duplicate rehearsal card.
7. Keep browser zoom at 100% and disable sleep, notifications, password-manager
   popups, and extensions that alter keyboard or form behavior.
8. Keep manual entry or controlled lookup available as the documented fallback.

If the page is refreshed, confirm input focus before resuming scans.

### Stage-control workstation

1. Use a protected, non-audience device. Prefer a fourth workstation or the
   server laptop away from the projector.
2. Open the installed PWA or `https://SERVER_LAN_IP/stage-control`.
3. Sign in with the named stage-operator account.
4. Confirm the correct active ceremony and ordered queue are visible.
5. Confirm the operator understands **Present next**, **Present selected**,
   **Complete and present next**, **Complete and clear**, **Correct wrong
   graduate**, holding, skip/return, and replay behavior.
6. Never project this route. It contains operational controls and queue details.

### Audience stage laptop

1. Connect the laptop to the dedicated LAN, preferably by Ethernet.
2. Connect the projector or LED display and the wired venue audio output.
3. Open a separate stage-operator browser profile or installed PWA at
   `https://SERVER_LAN_IP/stage`.
4. Choose **Enable ceremony audio** once. Browsers require this user action
   before automatic playback can be armed for the tab.
5. Select the correct operating-system audio output and test approved audio.
6. Enter browser fullscreen and hide browser chrome, the taskbar or dock, the
   pointer, notifications, and unrelated windows.
7. Prefer a 1920x1080 output and keep browser zoom at 100%.
8. Refresh `/stage` once during rehearsal and confirm it restores the current
   state from the server.
9. Keep a human announcer ready for missing, rejected, unreadable, or otherwise
   unavailable audio.

Only `/stage` belongs on the audience display. Never project `/stage-control`,
administration, logs, a terminal, or environment files.

### Administrator, data encoder, and technical operator

1. Open the HTTPS application URL on a protected non-audience device.
2. Sign in with a named account rather than a shared rehearsal account.
3. Let `/` redirect to the role's normal landing page, or use the appropriate
   route from the table above.
4. Administrators enter through `/admin` and can manage accounts under
   `/admin/users`.
5. Data encoders normally enter through `/admin/students` for graduate
   preparation.
6. Technical operators normally enter through `/admin/scan-logs` and use their
   visible authorized Logs, Backups, or device-connection navigation.
7. Keep administrative pages, personal information, QR data, and logs away from
   the audience display.

## Open the phone camera scanner

The phone is a complete authenticated scanner workstation. It decodes video
locally and sends only the decoded QR value through the normal scan request. It
does not pair with the scanner laptop or upload camera frames.

### One-time enrollment

1. Connect the phone to the dedicated ceremony LAN.
2. On the server or another protected device, sign in as an administrator or
   technical operator.
3. Open **System -> Connect devices** or
   `https://SERVER_LAN_IP/admin/device-connections`.
4. Confirm the page reports **Ready for enrollment**.
5. Scan the displayed setup QR with the phone. This opens the restricted HTTP
   onboarding route at `http://SERVER_LAN_IP/connect-phone`.
6. Choose **Download GSS certificate**.
7. Install the downloaded public certificate as a trusted root certificate on
   the approved phone and enable full trust when the operating system requires
   it. Compare the short certificate code with the trusted server screen.
8. Choose **Continue to secure scanner**.
9. Stop if HTTPS still shows any certificate warning. Resolve trust before
   entering credentials or requesting camera access.
10. Sign in with the phone's named scanner-operator account.
11. Install the GSS PWA from the browser and open it from the home screen.
12. Open `/scanner`, choose **Camera**, and choose **Start camera**.
13. Allow camera access and confirm the rear camera and intended lens are active.
14. Test valid, invalid, duplicate, and ineligible rehearsal cards.
15. Switch to **USB / keyboard** once and confirm the camera privacy indicator
    turns off immediately.

The HTTP onboarding page accepts no credentials and exposes no ceremony data.
All sign-in and scanning must occur on the trusted HTTPS address.

### Normal phone reopening

1. Connect to the same dedicated LAN.
2. Open the installed GSS icon from the home screen.
3. Sign in again if the session expired.
4. Open Scanner, select **Camera**, and choose **Start camera**.
5. Grant or restore camera permission if prompted.
6. Wait for **Camera ready** before presenting a QR card.

If a scan request was not confirmed, restore the server connection and use
**Retry same QR** once. Do not assume the original request was saved.

## Install or reopen the PWA

The PWA is an application window and launcher. It does not run Laravel,
PostgreSQL, or Reverb and cannot continue ceremony commands while the server is
offline.

### Install on an approved workstation

1. Connect the device to the ceremony LAN.
2. Install and trust the public GSS certificate authority.
3. Open the HTTPS application address and confirm there is no warning.
4. Sign in with the account assigned to that workstation.
5. Choose **Install** in the application prompt or use the browser's **Install
   app** command.
6. Pin the installed app to the taskbar, dock, start menu, or home screen.
7. Close the browser and reopen the installed application once to verify the
   launcher.
8. Confirm the account lands on the correct authorized route.

### Reopen an installed PWA

1. Confirm the local server and ceremony network are running.
2. Open the GSS icon from the operating system.
3. Sign in if the session has expired.
4. Confirm the correct route and live-connection status.
5. On the audience stage, enable ceremony audio again if a new tab or window was
   created.

The service worker caches only the offline warning, manifest, icons, and
versioned frontend assets. It does not cache authenticated pages or queue data,
and it does not queue scan or stage commands while offline.

## Reopen after a reboot

### Server laptop reboot

1. Start the router or switch first.
2. Start the server and wait for Docker and the network.
3. Open **Graduation Stage System** from the desktop application menu.
4. If the launcher fails, follow [Manual production startup](#manual-production-startup).
5. Run the health check:

   ```bash
   bash scripts/gss-health-check.sh --env-file .env.production
   ```

6. Confirm `https://SERVER_LAN_IP/up` and `/login` open from a client device.
7. Refresh scanner, stage-control, and stage. Each page reloads authoritative
   state from Laravel and PostgreSQL.
8. On the audience stage, re-enable ceremony audio if the browser opened a new
   tab or session.
9. Perform a controlled rehearsal scan before resuming live operation.

### Client workstation reboot

1. Reconnect to the dedicated LAN.
2. Open the installed PWA or the saved HTTPS bookmark.
3. Sign in if required.
4. Return to the workstation's assigned route.
5. Restore scanner focus, stage audio activation, or audience fullscreen as
   appropriate.

## Safe event shutdown

Do not shut down while a graduate is still active on stage.

1. Complete or clear the current presentation and stop live scanning.
2. Close or pause any announcement-preparation work.
3. From the server repository root, create a final backup and stop the services:

   ```bash
   bash scripts/gss-stop.sh --env-file .env.production --backup
   ```

4. Confirm the script reports that shutdown completed.
5. Power down client devices, then the server and network equipment according to
   the event plan.

If a verified backup was already completed or an emergency requires immediate
shutdown, the technical operator may use:

```bash
bash scripts/gss-stop.sh --env-file .env.production --no-backup
```

Both commands preserve containers, images, named volumes, media, logs, and
backups. Do not use `docker compose down -v` for normal shutdown.

## Troubleshooting

Start with the smallest safe check. During a ceremony, stop scanning and stage
commands whenever PostgreSQL health or request acceptance is uncertain.

### The page does not open

1. Confirm the device is connected to the dedicated LAN.
2. Confirm the saved address matches the server's current reserved IP.
3. Open `https://SERVER_LAN_IP/up`.
4. On the server, run:

   ```bash
   bash scripts/gss-health-check.sh --env-file .env.production
   docker compose --env-file .env.production -f compose.yaml -f compose.deploy.yaml ps
   ```

5. If the deployment is stopped, use the desktop launcher or the manual
   `gss-start.sh --skip-migrate` workflow.
6. Check that the firewall allows ceremony-subnet access to TCP `80` and `443`.
   Do not expose PostgreSQL as a workaround.

In development, use `docker compose ps` and confirm `http://localhost:8000/up`
instead.

### The browser shows a certificate warning

1. Do not enter credentials or bypass the warning for ceremony use.
2. Confirm the URL uses the exact host in `.env.production` and
   `GSS_HTTPS_HOST`.
3. Reopen **System -> Connect devices** from a trusted administrator or technical
   operator device.
4. Download and install the current public GSS certificate authority on the
   affected device.
5. Enable full trust if required by the operating system, restart the browser or
   PWA, and reopen the HTTPS address.
6. If the server IP or certificate authority changed, repeat enrollment on every
   approved device.

### Login fails or the page returns 403

1. Confirm the account email and locally assigned password.
2. Ask an administrator to confirm that the account is active and has the
   intended role under `/admin/users`.
3. Open `/` and allow the application to choose the account's authorized landing
   page.
4. If changing roles on a shared test device, log out first or use the correct
   separate browser profile.
5. Do not solve a `403` by sharing administrator credentials.

### Frontend assets are missing or the app works only while Vite runs

For development, stop Vite if necessary and rebuild:

```bash
npm run build
test -f public/build/manifest.json
test ! -f public/hot
```

For deployment, do not start Vite. Rebuild the reviewed release assets before
startup and rerun the deployment health check.

### PostgreSQL is unhealthy

1. Stop scans and stage commands.
2. On the event server, inspect the deployment state:

   ```bash
   docker compose --env-file .env.production -f compose.yaml -f compose.deploy.yaml ps
   ```

3. Restart PostgreSQL only under the technical operator's recovery procedure:

   ```bash
   docker compose --env-file .env.production -f compose.yaml -f compose.deploy.yaml restart database
   ```

4. Run the health check again.
5. Refresh `/scanner`, `/stage-control`, and `/stage`, then review operational
   logs before resuming.

Do not switch to SQLite, edit database rows manually, or reset the database.

### Reverb is disconnected but pages still open

HTTP commands and PostgreSQL remain authoritative, but live browser updates may
be delayed until Reverb recovers.

1. Restart Reverb on the event server:

   ```bash
   docker compose --env-file .env.production -f compose.yaml -f compose.deploy.yaml restart reverb
   ```

2. Run the health check.
3. Refresh stage-control and stage so they retrieve authoritative state over
   HTTP.
4. Confirm the connection status has recovered before continuing normal flow.

### The PWA or browser looks stale after an update

1. Pause live work; never reload stage control while a graduate is being
   presented.
2. Apply updates only in an approved maintenance window.
3. When the application reports that an update is ready, reload after live work
   is paused.
4. Reopen the installed PWA and verify its route and connection state.
5. Refresh scanner, stage-control, and stage to reconcile with PostgreSQL.

Do not clear application storage during live operation unless the technical
operator has confirmed the consequences and saved state is not being mistaken
for authoritative server data.

### The phone camera will not start

| Symptom | Action |
| --- | --- |
| HTTPS is untrusted | Install and trust the current GSS public certificate, then reopen the HTTPS PWA. |
| Permission is blocked | Allow Camera in browser site settings, then choose **Try camera again**. |
| Camera is already in use | Close other camera applications and browser tabs, then retry. |
| No camera is found | Check the device camera or switch to **USB / keyboard**. |
| Camera paused after backgrounding | Return to the PWA and wait for **Camera ready**. |
| Server request failed | Restore the server connection and choose **Retry same QR** once. |

If camera scanning cannot be restored safely, use the approved USB scanner or
manual fallback rather than an untrusted HTTP page.

### The desktop launcher or system service fails

1. Run the health check and review the service state:

   ```bash
   bash scripts/gss-health-check.sh --env-file .env.production
   systemctl status graduation-stage-system.service
   ```

2. Confirm Docker is running and `.env.production` still exists at the path used
   during service installation.
3. Try [Manual production startup](#manual-production-startup).
4. If a single service is unhealthy, follow the targeted restart instructions
   in the [operational recovery runbook](operational-recovery.md).
5. After recovery, refresh all workstations and verify scanner, control, stage,
   Reverb, and approved audio before resuming.

## Related operating guides

- [Local deployment runbook](deployment.md)
- [Device setup checklist](device-setup.md)
- [Local network plan](network-plan.md)
- [Installed PWA and automatic startup](pwa-and-autostart.md)
- [Operational recovery runbook](operational-recovery.md)
- [Backup and restore](backup-and-restore.md)
- [Ceremony rehearsal](ceremony-rehearsal.md)
- [Event-day checklist](event-day-checklist.md)

Use those documents for detailed deployment, networking, recovery, and event
sign-off. This guide is the canonical entry point for deciding how to start and
open the application.
