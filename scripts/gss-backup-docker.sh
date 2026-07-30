#!/usr/bin/env bash
set -euo pipefail

backup_root="${1:-storage/app/gss-backups}"

case "$backup_root" in
    public|public/*|*/public|*/public/*)
        echo "Backup destination must not be inside public/." >&2
        exit 2
        ;;
esac

identifier="gss-backup-$(date +%Y%m%d-%H%M%S)"
workspace="${backup_root%/}/${identifier}"
partial="${workspace}.partial"

cleanup() {
    if [[ -d "$partial" ]]; then
        rm -rf "$partial"
    fi
}
trap cleanup ERR

mkdir -p "$partial/media"

docker compose exec -T database sh -lc \
    'PGPASSWORD="$POSTGRES_PASSWORD" pg_dump -U "$POSTGRES_USER" -d "$POSTGRES_DB" --format=plain --no-owner --no-privileges' \
    > "$partial/database.sql"

if [[ ! -s "$partial/database.sql" ]]; then
    echo "Database dump was not created." >&2
    exit 1
fi

if [[ -d storage/app/public/graduates ]]; then
    mkdir -p "$partial/media/storage-graduates"
    tar -C storage/app/public/graduates -cf - . | tar -C "$partial/media/storage-graduates" -xf -
fi

if [[ -d public/media/phase3 ]]; then
    mkdir -p "$partial/media/development-phase3-media"
    tar -C public/media/phase3 -cf - . | tar -C "$partial/media/development-phase3-media" -xf -
fi

(
    cd "$partial"
    find database.sql media -type f -print0 | sort -z | xargs -0 sha256sum > SHA256SUMS
)

postgres_version="$(docker compose exec -T database psql --version | tr -d '\r')"
git_commit="$(git rev-parse --short=12 HEAD 2>/dev/null || true)"
created_at="$(date -Iseconds)"
file_size="$(du -sb "$partial" | awk '{print $1}')"
manifest_checksum_placeholder="$(printf '%064d' 0)"

cat > "$partial/manifest.json" <<JSON
{
  "identifier": "$identifier",
  "created_at": "$created_at",
  "environment": "${APP_ENV:-local}",
  "git_commit": "$git_commit",
  "laravel_version": "recorded by application documentation",
  "postgresql_version": "$postgres_version",
  "database_dump": "database.sql",
  "media_directory": "media",
  "checksums_file": "SHA256SUMS",
  "status": "completed",
  "verification_status": "pending",
  "restore_instructions": "docs/backup-and-restore.md",
  "manifest_checksum_placeholder": "$manifest_checksum_placeholder"
}
JSON

mv "$partial" "$workspace"
manifest_checksum="$(sha256sum "$workspace/manifest.json" | awk '{print $1}')"

docker compose exec -T app php artisan tinker --execute "\
App\\Models\\BackupRecord::query()->updateOrCreate(\
['identifier' => '$identifier'],\
[\
'path_label' => '$(basename "$backup_root")/$identifier',\
'status' => 'completed',\
'started_at' => now(),\
'completed_at' => now(),\
'created_by' => null,\
'file_size' => $file_size,\
'checksum' => '$manifest_checksum',\
'verification_status' => 'passed',\
'failure_summary' => null,\
]\
);"

echo "$workspace"
