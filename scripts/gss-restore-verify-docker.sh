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
awards="$(docker compose exec -T database sh -lc "psql -tAc 'select count(*) from student_awards' -U \"\$POSTGRES_USER\" -d $restore_db" | tr -d '[:space:]')"
templates="$(docker compose exec -T database sh -lc "psql -tAc 'select count(*) from announcement_templates' -U \"\$POSTGRES_USER\" -d $restore_db" | tr -d '[:space:]')"
generations="$(docker compose exec -T database sh -lc "psql -tAc 'select count(*) from announcement_generations' -U \"\$POSTGRES_USER\" -d $restore_db" | tr -d '[:space:]')"
batches="$(docker compose exec -T database sh -lc "psql -tAc 'select count(*) from announcement_generation_batches' -U \"\$POSTGRES_USER\" -d $restore_db" | tr -d '[:space:]')"
batch_items="$(docker compose exec -T database sh -lc "psql -tAc 'select count(*) from announcement_generation_batch_items' -U \"\$POSTGRES_USER\" -d $restore_db" | tr -d '[:space:]')"

invalid_hashes="$(docker compose exec -T database sh -lc "psql -tAc \"select (select count(*) from announcement_generations where source_hash !~ '^[0-9a-f]{64}$') + (select count(*) from students where audio_source_hash is not null and audio_source_hash !~ '^[0-9a-f]{64}$')\" -U \"\$POSTGRES_USER\" -d $restore_db" | tr -d '[:space:]')"
if [[ "$invalid_hashes" != "0" ]]; then
    echo "Restored announcement source hashes are invalid." >&2
    exit 1
fi

invalid_active_references="$(docker compose exec -T database sh -lc "psql -tAc \"select count(*) from students s left join announcement_generations g on g.id = s.audio_generation_id and g.student_id = s.id and g.ceremony_id = s.ceremony_id where s.audio_source_type = 'azure_tts' and (s.audio_generation_id is null or s.audio_source_hash is null or s.audio_path is null or g.id is null or g.status != 'approved' or g.source_hash != s.audio_source_hash or g.approved_audio_path != s.audio_path)\" -U \"\$POSTGRES_USER\" -d $restore_db" | tr -d '[:space:]')"
if [[ "$invalid_active_references" != "0" ]]; then
    echo "Restored active generated-audio references are inconsistent." >&2
    exit 1
fi

candidate_count=0
while IFS= read -r candidate_path; do
    [[ -n "$candidate_path" ]] || continue
    case "$candidate_path" in
        announcement-candidates/*.mp3) ;;
        *)
            echo "Restored announcement candidate policy contains an unsafe path." >&2
            exit 1
            ;;
    esac
    if [[ "$candidate_path" == *..* || ! -s "$restore_media/private-announcement-candidates/${candidate_path#announcement-candidates/}" ]]; then
        echo "Restored private announcement candidate is missing or unreadable." >&2
        exit 1
    fi
    candidate_count=$((candidate_count + 1))
done < <(docker compose exec -T database sh -lc "psql -tA -c 'select candidate_audio_path from announcement_generations where candidate_audio_path is not null order by id' -U \"\$POSTGRES_USER\" -d $restore_db" | tr -d '\r')

restored_candidate_files=0
if [[ -d "$restore_media/private-announcement-candidates" ]]; then
    restored_candidate_files="$(find "$restore_media/private-announcement-candidates" -type f | wc -l | tr -d '[:space:]')"
fi
if [[ "$restored_candidate_files" != "$candidate_count" ]]; then
    echo "Restored private candidate media does not match generation metadata." >&2
    exit 1
fi

active_generated_count=0
while IFS= read -r active_path; do
    [[ -n "$active_path" ]] || continue
    case "$active_path" in
        storage/graduates/audio/*.mp3) ;;
        *)
            echo "Restored generated active audio has an unsafe path." >&2
            exit 1
            ;;
    esac
    if [[ "$active_path" == *..* || ! -s "$restore_media/storage-graduates/${active_path#storage/graduates/}" ]]; then
        echo "Restored approved generated audio is missing or unreadable." >&2
        exit 1
    fi
    active_generated_count=$((active_generated_count + 1))
done < <(docker compose exec -T database sh -lc "psql -tA -c \"select audio_path from students where audio_source_type = 'azure_tts' order by id\" -U \"\$POSTGRES_USER\" -d $restore_db" | tr -d '\r')

echo "Disposable restore verification passed."
echo "Ceremonies: $ceremonies"
echo "Graduates: $students"
echo "Media files: $media_files"
echo "Announcement awards: $awards"
echo "Announcement templates: $templates"
echo "Announcement generations: $generations"
echo "Announcement batches: $batches"
echo "Announcement batch items: $batch_items"
echo "Private candidates verified: $candidate_count"
echo "Active generated audio verified: $active_generated_count"
