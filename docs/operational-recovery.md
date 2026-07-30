# Operational Recovery Runbook

Status: Phase 9 implemented

Use this runbook on the local ceremony network. Do not paste secrets into chat,
logs, screenshots, or reports.

## Service restart commands

From the server laptop repository root:

```bash
bash scripts/gss-health-check.sh --env-file .env.production
docker compose --env-file .env.production -f compose.yaml -f compose.deploy.yaml ps
docker compose --env-file .env.production -f compose.yaml -f compose.deploy.yaml restart app
docker compose --env-file .env.production -f compose.yaml -f compose.deploy.yaml restart reverb
docker compose --env-file .env.production -f compose.yaml -f compose.deploy.yaml restart database
```

Check application health:

```bash
curl http://SERVER_LAN_IP:8000/up
```

Use `docs/deployment.md` for normal startup/shutdown and
`docs/network-plan.md` for LAN addressing.

## Browser recovery

- Scanner laptop: refresh `/scanner`; HTTP scans remain authoritative even if
  live updates reconnect.
- Stage-control laptop: refresh `/stage-control`; queue state reloads from
  PostgreSQL.
- Audience stage laptop: refresh `/stage`; current presentation reloads through
  HTTP state recovery.

## Reverb outage

Commands still commit through HTTP and PostgreSQL. Restart Reverb:

```bash
docker compose restart reverb
```

Then refresh stage-control and confirm it says state recovered.

## PostgreSQL unavailable

Stop scan and stage commands until PostgreSQL is healthy. Restart:

```bash
docker compose restart database
docker compose up -d --wait
```

Run health checks and review `/admin/scan-logs` and `/admin/stage-events` before
resuming.

## Missing or corrupt media

- Missing photo: proceed if identity text remains approved.
- Missing/unapproved/rejected audio: use the human announcer fallback.
- Corrupt audio: mark rejected or replace from graduate administration; do not
  expose technical details on `/stage`.

## Wrong graduate presented

1. Do not rescan repeatedly.
2. Use stage-control to complete, skip, or return queue entries as appropriate.
3. Review `/admin/stage-events` and `/admin/scan-logs`.
4. If state is inconsistent and no graduate is presenting, an administrator may
   use the Phase 9 operational reset.

## Queue inconsistency

Refresh stage-control first. If the persisted state is still inconsistent:

- preserve logs;
- avoid database edits during the ceremony;
- complete or clear any live presentation first;
- use administrator reset only when the documented confirmation is satisfied.

## Backup and restore escalation

Create backup:

```bash
bash scripts/gss-backup-docker.sh storage/app/gss-backups
```

Verify backup:

```bash
bash scripts/gss-restore-verify-docker.sh storage/app/gss-backups/gss-backup-YYYYMMDD-HHMMSS
```

Escalate destructive restore only after administrator approval and a current
safety backup.

## Standby laptop handoff

Copy the latest verified backup directory to the standby laptop, restore through
the documented CLI procedure, build or load the deployment image, start with
`scripts/gss-start.sh`, run `scripts/gss-health-check.sh`, and verify scanner,
stage-control, stage, Reverb, and media playback before resuming operations.
Disconnect the primary server before assigning the standby the same LAN identity.

## Human announcer fallback

Keep the approved announcement names available outside the audio system. If
local audio is missing, corrupt, rejected, or unavailable, the stage may continue
visually while the human announcer reads the approved name.
