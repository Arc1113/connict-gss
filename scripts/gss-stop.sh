#!/usr/bin/env bash

set -Eeuo pipefail

source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/gss-env.sh"

usage() {
    printf 'Stop Graduation Stage System deployment services safely.\n\n'
    gss_print_usage_common
    printf '  --backup            Run the Docker backup script before stopping services.\n'
    printf '  --no-backup         Stop without running a final backup.\n'
    printf '\nThis command preserves containers, images, volumes, media, and backups.\n'
}

env_file=".env"
backup_mode=""

while [[ $# -gt 0 ]]; do
    case "$1" in
        --env-file)
            env_file="$2"
            shift 2
            ;;
        --backup)
            backup_mode="backup"
            shift
            ;;
        --no-backup)
            backup_mode="skip"
            shift
            ;;
        --help)
            usage
            exit 0
            ;;
        *)
            printf 'Unknown option: %s\n' "$1" >&2
            usage >&2
            exit 2
            ;;
    esac
done

cd "${gss_project_dir}"

gss_require_docker

if [[ ! -f "${env_file}" ]]; then
    printf 'Environment file not found: %s\n' "${env_file}" >&2
    exit 1
fi

if [[ -z "${backup_mode}" ]]; then
    if [[ -t 0 ]]; then
        printf 'Type "backup" to create a final backup before shutdown or "skip" to stop without backup: '
        read -r backup_mode
    else
        printf 'Non-interactive shutdown requires --backup or --no-backup.\n' >&2
        exit 1
    fi
fi

printf 'Checking current stage state where possible...\n'
if gss_compose "${env_file}" ps app --format json >/dev/null 2>&1; then
    gss_compose "${env_file}" exec -T app php artisan tinker --execute='
        $state = App\Models\StageState::query()->latest("id")->first();
        if ($state && $state->current_queue_item_id) {
            fwrite(STDERR, "Warning: a graduate may still be active on stage. Confirm the ceremony is stopped before shutdown.\n");
        } else {
            echo "No active stage presentation detected.\n";
        }
    ' || true
fi

if [[ "${backup_mode}" == "backup" ]]; then
    printf 'Stopping the preparation-only TTS worker before the final snapshot.\n'
    gss_compose "${env_file}" stop tts-worker
    backup_path="$(gss_env_value "${env_file}" GSS_BACKUP_PATH)"
    if [[ -z "${backup_path}" ]]; then
        backup_path="storage/app/gss-backups"
    fi
    bash scripts/gss-backup-docker.sh "${backup_path}"
elif [[ "${backup_mode}" != "skip" ]]; then
    printf 'Invalid shutdown backup mode: %s\n' "${backup_mode}" >&2
    exit 1
else
    printf 'Skipping final backup by operator request.\n'
fi

printf 'Stopping HTTPS proxy, application, Reverb, TTS worker, and PostgreSQL without removing volumes...\n'
gss_compose "${env_file}" stop proxy app reverb tts-worker database
printf 'Shutdown complete. Volumes, media, backups, containers, and images were preserved.\n'
