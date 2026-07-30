#!/usr/bin/env bash
set -euo pipefail

backup_path="${1:?Provide backup path}"
manifest="$backup_path/manifest.json"
checksums="$backup_path/SHA256SUMS"

if [[ ! -d "$backup_path" ]]; then
    echo "Backup path is not a directory." >&2
    exit 2
fi

if [[ ! -f "$manifest" || ! -f "$checksums" ]]; then
    echo "Backup manifest or checksum file is missing." >&2
    exit 2
fi

if grep -Eq '(^/|(^|/)\.\.(/|$))' "$checksums"; then
    echo "Checksum file contains an unsafe path." >&2
    exit 2
fi

(
    cd "$backup_path"
    sha256sum -c SHA256SUMS >/dev/null
)

restore_db="gss_restore_verify_$(date +%Y%m%d%H%M%S)_$RANDOM"
restore_media="storage/framework/gss-restore-verify/$restore_db"

cleanup() {
    docker compose exec -T database sh -lc "dropdb -U \"\$POSTGRES_USER\" --if-exists $restore_db" >/dev/null 2>&1 || true
    rm -rf "$restore_media"
}
trap cleanup EXIT

docker compose exec -T database sh -lc "createdb -U \"\$POSTGRES_USER\" $restore_db"
docker compose exec -T database sh -lc "psql -v ON_ERROR_STOP=1 -U \"\$POSTGRES_USER\" -d $restore_db" < "$backup_path/database.sql" >/dev/null

mkdir -p "$restore_media"
if [[ -d "$backup_path/media" ]]; then
    tar -C "$backup_path/media" -cf - . | tar -C "$restore_media" -xf -
fi

ceremonies="$(docker compose exec -T database sh -lc "psql -tAc 'select count(*) from ceremonies' -U \"\$POSTGRES_USER\" -d $restore_db" | tr -d '[:space:]')"
students="$(docker compose exec -T database sh -lc "psql -tAc 'select count(*) from students' -U \"\$POSTGRES_USER\" -d $restore_db" | tr -d '[:space:]')"
media_files="$(find "$restore_media" -type f | wc -l | tr -d '[:space:]')"

echo "Disposable restore verification passed."
echo "Ceremonies: $ceremonies"
echo "Graduates: $students"
echo "Media files: $media_files"
