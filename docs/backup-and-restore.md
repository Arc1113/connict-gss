# Backup and Restore

Status: ENH-TTS-8 announcement-aware backup and disposable restore verified

The Graduation Stage System backup workflow is local-only. It does not upload to
cloud storage, email, or any internet service.

ENH-TTS-8 verified backup
`storage/app/gss-backups/gss-backup-20260804-122018` and restored it into a
disposable database/media directory. Verification reported 3 ceremonies, 17
graduates, 34 media files, 2 awards, 1 template, 1 generation, 1 batch, 1 batch
item, 1 private candidate, and 1 active generated-audio file. Temporary voice
comparison row data and its file were excluded.

## Backup contents

Included:

- PostgreSQL production database dump;
- managed graduate photos and announcement audio under local media storage;
- private generated announcement candidates under a protected backup media subtree;
- Phase 3 development media fixtures when present;
- `manifest.json`;
- `SHA256SUMS`;
- safe application version metadata.

Excluded:

- `.env` secrets;
- application key, Reverb secret, database password, and user passwords outside
  the database dump;
- `vendor/`, `node_modules/`, cache files, screenshots, and temporary imports;
- public web output directories; and
- expiring fictional voice-comparison sample row data and temporary MP3 files.

## Primary Docker backup command

Run from the repository root on the server laptop:

```bash
bash scripts/gss-backup-docker.sh storage/app/gss-backups
```

The script uses the Docker Compose `database` service and does not assume host
`pg_dump` exists. The destination must not be inside `public/`.

Use a quiet preparation window: stop `tts-worker`, wait for any in-flight
synthesis or promotion to finish, and prevent award/template/generation changes
until the snapshot completes. The backup fails safely if a generation or batch
item is still processing, or if referenced private candidates and the managed
candidate directory do not match. `gss-stop.sh --backup` stops the worker before
creating its final snapshot.

During Phase 10 deployment shutdown, the safe wrapper is:

```bash
bash scripts/gss-stop.sh --env-file .env.production --backup
```

It runs the backup command before stopping services and preserves Docker
volumes. Normal shutdown must not use `docker compose down -v`.

Backups are named:

```text
gss-backup-YYYYMMDD-HHMMSS
```

Each backup contains:

```text
database.sql
media/
SHA256SUMS
manifest.json
```

The `media/private-announcement-candidates/` subtree preserves candidate files
for generation-history recovery. It is checksum-covered and must be restored to
the configured private candidate disk/directory (`storage/app/private` for the
default local disk), never under `public/`. The
database dump supplies the corresponding private paths; the manifest and UI do
not enumerate them.

## Manifest and checksums

`manifest.json` records the backup identifier, creation time, environment label,
Git commit, PostgreSQL version, dump filename, media directory, result status,
and restore instructions reference.

`SHA256SUMS` is verified before restore testing. Paths are rejected if they are
absolute or contain `..`.

## Restore verification

Run a non-destructive verification before any restore escalation:

```bash
bash scripts/gss-restore-verify-docker.sh storage/app/gss-backups/gss-backup-YYYYMMDD-HHMMSS
```

The script:

1. validates the manifest and checksum file;
2. creates a disposable PostgreSQL database;
3. restores `database.sql` into that disposable database;
4. copies media into a temporary local directory;
5. checks ceremony, graduate, award, template, generation, batch, and batch-item
   counts;
6. validates source-hash shape and active generated-audio references;
7. verifies each referenced private candidate and approved generated file is
   present, readable, non-empty, and inside its approved subtree;
8. rejects orphan private candidates; and
9. removes the disposable database and temporary media.

It does not overwrite the primary database.

## Destructive restore escalation

Do not restore over active ceremony data from a browser route.

A destructive restore requires:

- administrator approval;
- technical operator present;
- active ceremony operation stopped;
- current database safety backup;
- manifest validation;
- checksum validation;
- explicit target database confirmation;
- application maintenance mode where practical;
- post-restore migration, health, media, scanner, stage-control, and stage
  checks.

## Copying backups

After verification, copy the completed backup directory to an approved external
drive, standby laptop, or secure local storage. Do not automatically upload it.

For cold-standby preparation, copy the latest verified backup directory and this
repository release to the standby laptop, then run the restore verification
script before any destructive restore.
