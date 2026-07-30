# Operational Recovery Runbook

Status: Phase 11 recovery paths rehearsed in software

Use this runbook on the local ceremony network. Do not paste secrets into chat,
logs, screenshots, or reports.

Phase 11 rehearsed Reverb restart, application restart, HTTP browser recovery,
and PostgreSQL interruption/recovery in software. Physical recovery drills on the
final three-device network remain required before event certification.

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

1. Tell the announcer to pause and do not mark the graduate completed.
2. On `/stage-control`, verify the displayed name, then choose **Correct wrong
   graduate** and confirm. This returns the current item to its preserved queue
   position and puts the audience into the neutral holding state.
3. Verify the correct graduate in the waiting queue and use **Present selected**,
   or use **Present next** only when the earliest row is the correct person.
4. Confirm the audience display before resuming the announcement.
5. Review `/admin/stage-events` if the correction needs an audit trail.

If the correction reports stale state, refresh stage-control and verify the
current graduate before retrying. Do not repeatedly scan, delete queue rows, edit
PostgreSQL directly, or use operational reset while a graduate is presenting.

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
