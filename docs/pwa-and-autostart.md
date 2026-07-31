# Installed workstation app and automatic startup

The Graduation Stage System is installable as a Progressive Web App on approved
ceremony workstations. The PWA is a launcher and application window; Docker
Compose still runs Laravel, PostgreSQL, Reverb, and the local HTTPS proxy on the
server.

## Safety boundary

The service worker caches only the offline warning page, manifest, icons, and
versioned frontend assets. It does not cache authenticated Inertia pages, queue
state, graduate data, or request responses. It never intercepts mutation
requests. Scanner and stage commands are not queued while offline.

If the local server is unavailable, the installed app displays a blocking
instruction to stop scanning and stage advancement.

## One-time server setup

1. Prepare and validate `.env.production` from `.env.deployment.example`.
2. Build the production release and apply reviewed migrations.
3. Start the deployment:

   ```bash
   bash scripts/gss-start.sh --env-file .env.production --build --migrate
   ```

4. Export Caddy's local certificate authority:

   ```bash
   bash scripts/gss-export-local-ca.sh --env-file .env.production
   ```

5. Install the exported certificate as a trusted root certificate on each
   approved scanner, stage-control, audience, and administration device.
6. Open the HTTPS application address and confirm the browser reports a trusted
   secure connection.
7. Install automatic server startup:

   ```bash
   sudo bash scripts/gss-install-service.sh --env-file .env.production
   ```

The service starts the deployment after Docker and the network are ready. It
does not run migrations at boot.

## Workstation installation

Use a Chromium-based browser for the assigned ceremony workstations:

1. Open the HTTPS application address.
2. Sign in with the device's assigned operator account.
3. Choose **Install** when the in-app installation prompt appears, or use the
   browser's **Install app** command.
4. Pin the installed application to the taskbar, dock, or start menu.
5. Confirm that scanner operators open directly into Arrival scanner and stage
   operators open directly into Stage control.

The installed app may expose shortcuts for Scanner, Stage control, and Ceremony
readiness. Server authorization still protects every route.

## Updates

Build and deploy updates only during a planned maintenance window. When the
service worker detects a new version, the application displays an update-ready
notice. Operators must not reload Stage control while a graduate is being
presented. Apply the update after live work is paused, then perform authoritative
HTTP reconciliation.

## Reboot rehearsal

Before event day:

1. Stop live ceremony activity and create a verified backup.
2. Power off the server and all workstations.
3. Start the server without opening a terminal.
4. Confirm the systemd unit, Docker services, database health, HTTPS proxy, and
   Reverb health.
5. Launch each installed workstation app from its operating-system icon.
6. Rehearse a scan, stage presentation, correction, reconnect, and audience
   display refresh.
