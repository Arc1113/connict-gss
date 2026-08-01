#!/usr/bin/env bash

set -Eeuo pipefail

source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/gss-env.sh"

usage() {
    printf 'Start Graduation Stage System services for local event deployment.\n\n'
    gss_print_usage_common
    printf '  --migrate           Run php artisan migrate --force after services start.\n'
    printf '  --skip-migrate      Start services without applying migrations.\n'
    printf '  --build             Build deployment images before startup.\n'
    printf '\nThis command never runs destructive database reset commands or seeders.\n'
}

env_file=".env"
migration_mode=""
build_images=0

while [[ $# -gt 0 ]]; do
    case "$1" in
        --env-file)
            env_file="$2"
            shift 2
            ;;
        --migrate)
            migration_mode="migrate"
            shift
            ;;
        --skip-migrate)
            migration_mode="skip"
            shift
            ;;
        --build)
            build_images=1
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

gss_validate_env_file "${env_file}"
gss_require_docker
gss_ensure_deployment_dirs

manifest_path="$(gss_asset_manifest_path)"
if [[ ! -f "${manifest_path}" ]]; then
    printf 'Production asset manifest missing: %s\n' "${manifest_path}" >&2
    printf 'Run npm ci and npm run build before deployment startup.\n' >&2
    exit 1
fi

if [[ -z "${migration_mode}" ]]; then
    if [[ -t 0 ]]; then
        printf 'Choose migration mode. Type "migrate" to apply pending migrations or "skip" to start without migrations: '
        read -r migration_mode
    else
        printf 'Non-interactive startup requires --migrate or --skip-migrate.\n' >&2
        exit 1
    fi
fi

if [[ "${migration_mode}" != "migrate" && "${migration_mode}" != "skip" ]]; then
    printf 'Invalid migration mode: %s\n' "${migration_mode}" >&2
    exit 1
fi

if [[ "${build_images}" -eq 1 ]]; then
    gss_compose "${env_file}" build
fi

printf 'Starting PostgreSQL...\n'
gss_compose "${env_file}" up -d database

printf 'Waiting for PostgreSQL health...\n'
for _ in $(seq 1 60); do
    if [[ "$(gss_compose "${env_file}" ps database --format json | php -r '$json = stream_get_contents(STDIN); $row = json_decode($json, true); echo $row["Health"] ?? "";')" == "healthy" ]]; then
        break
    fi
    sleep 2
done

if [[ "$(gss_compose "${env_file}" ps database --format json | php -r '$json = stream_get_contents(STDIN); $row = json_decode($json, true); echo $row["Health"] ?? "";')" != "healthy" ]]; then
    printf 'PostgreSQL did not become healthy.\n' >&2
    exit 1
fi

printf 'Starting application, Reverb, and HTTPS proxy...\n'
gss_compose "${env_file}" up -d app reverb proxy

if [[ "${migration_mode}" == "migrate" ]]; then
    printf 'Applying pending migrations with php artisan migrate --force.\n'
    printf 'Operator reminder: create or verify a recent backup before applying migrations.\n'
    gss_compose "${env_file}" exec -T app php artisan migrate --force
else
    printf 'Skipping migrations by operator request.\n'
fi

app_url="$(gss_required_env_value "${env_file}" APP_URL)"
app_port="$(gss_required_env_value "${env_file}" APP_FORWARD_PORT)"
https_port="$(gss_required_env_value "${env_file}" HTTPS_FORWARD_PORT)"

printf 'Checking application health...\n'
for _ in $(seq 1 30); do
    if php -r "exit(@file_get_contents('http://127.0.0.1:${app_port}/up') ? 0 : 1);" >/dev/null 2>&1; then
        break
    fi
    sleep 2
done

if ! php -r "exit(@file_get_contents('http://127.0.0.1:${app_port}/up') ? 0 : 1);" >/dev/null 2>&1; then
    printf 'Application health check failed on loopback port %s.\n' "${app_port}" >&2
    exit 1
fi

printf 'Checking HTTPS proxy port...\n'
if ! php -r "exit(@fsockopen('127.0.0.1', (int) '${https_port}') ? 0 : 1);" >/dev/null 2>&1; then
    printf 'HTTPS proxy port check failed: 127.0.0.1:%s\n' "${https_port}" >&2
    exit 1
fi

printf 'Publishing the local HTTPS certificate for phone enrollment...\n'
bash scripts/gss-export-local-ca.sh \
    --env-file "${env_file}" \
    --output storage/app/gss-certificates/gss-local-ca.crt

printf 'Graduation Stage System is ready.\n'
printf 'Application: %s\n' "${app_url}"
printf 'Scanner:     %s/scanner\n' "${app_url}"
printf 'Stage:       %s/stage\n' "${app_url}"
printf 'Control:     %s/stage-control\n' "${app_url}"
printf 'Reverb:      wss://%s:%s\n' "$(gss_required_env_value "${env_file}" GSS_HTTPS_HOST)" "${https_port}"
