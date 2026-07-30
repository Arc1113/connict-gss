# Phase 10 Deployment Evidence

Status: completed

This directory records Phase 10 local deployment checks. Do not commit PNG,
video, backup, database dump, or secret evidence files.

Verified in this environment on 2026-07-30:

- deployment Compose validation for `compose.yaml` plus `compose.deploy.yaml`;
- production image build with no source-tree bind mount in the deployment
  override;
- temporary LAN-style deployment URL `http://192.168.1.11:8011`;
- startup script with `--build --migrate`;
- health check with zero warnings;
- shutdown script with `--no-backup`;
- PostgreSQL host port confirmed as loopback-only;
- production app startup without the Vite development server;
- app `/up`, Reverb socket, database connection, migration status, media, backup,
  and disk-space checks;
- browser verification of scanner, stage-control, and stage through separate
  Chromium profiles;
- browser refresh recovery, Reverb restart recovery, application restart
  recovery, and same-origin production asset loading.

Notes:

- The temporary verification env files and `gss-phase10` Docker volumes were
  removed after verification.
- Composer online audit initially timed out against Packagist; rerun with
  `--ignore-unreachable` completed and reported no advisories from available
  advisory data.
- Vite still reports one JavaScript chunk above 500 kB; this is documented and
  deferred because safe route-level code splitting is not required for Phase 10.

Physical three-laptop, router, projector, USB scanner, and venue audio checks
must be completed by a human with the actual devices before claiming event
certification.
