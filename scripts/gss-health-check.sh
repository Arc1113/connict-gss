#!/usr/bin/env bash

set -Eeuo pipefail

source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/gss-env.sh"

usage() {
    printf 'Check Graduation Stage System local deployment readiness.\n\n'
    gss_print_usage_common
    printf '  --min-free-gb N     Warn when available project-disk space is below N GiB. Default: 20\n'
}

env_file=".env"
min_free_gb=20

while [[ $# -gt 0 ]]; do
    case "$1" in
        --env-file)
            env_file="$2"
            shift 2
            ;;
        --min-free-gb)
            min_free_gb="$2"
            shift 2
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

failures=0
warnings=0

pass() { printf '[PASS] %s\n' "$1"; }
warn() { printf '[WARN] %s\n' "$1"; warnings=$((warnings + 1)); }
fail() { printf '[FAIL] %s\n' "$1"; failures=$((failures + 1)); }

check_file_writable_dir() {
    local dir="$1"
    local label="$2"
    mkdir -p "${dir}" 2>/dev/null || true
    if [[ -d "${dir}" && -w "${dir}" ]]; then
        pass "${label} is writable"
    else
        fail "${label} is not writable"
    fi
}

if gss_require_docker; then
    pass 'Docker and Docker Compose are available'
else
    fail 'Docker or Docker Compose is unavailable'
fi

if [[ -f "${env_file}" ]]; then
    pass "Environment file exists: ${env_file}"
else
    fail "Environment file missing: ${env_file}"
fi

if [[ -f "$(gss_asset_manifest_path)" ]]; then
    pass 'Production asset manifest exists'
else
    fail 'Production asset manifest is missing'
fi

if [[ -f public/hot ]]; then
    fail 'Vite hot file exists; deployment must not depend on the Vite dev server'
else
    pass 'No Vite hot file present'
fi

app_url="$(gss_env_value "${env_file}" APP_URL || true)"
app_port="$(gss_env_value "${env_file}" APP_FORWARD_PORT || true)"
reverb_port="$(gss_env_value "${env_file}" REVERB_FORWARD_PORT || true)"
https_port="$(gss_env_value "${env_file}" HTTPS_FORWARD_PORT || true)"
backup_path="$(gss_env_value "${env_file}" GSS_BACKUP_PATH || true)"
[[ -n "${backup_path}" ]] || backup_path="storage/app/gss-backups"

if [[ "$(gss_env_value "${env_file}" APP_DEBUG || true)" == "false" ]]; then
    pass 'APP_DEBUG is false'
else
    fail 'APP_DEBUG is not false'
fi

if [[ -n "${app_url}" && "${app_url}" != *"localhost"* && "${app_url}" != *"127.0.0.1"* ]]; then
    pass 'APP_URL uses a LAN-style host'
else
    warn 'APP_URL is not a LAN address; physical scanner/stage devices may not connect'
fi

if gss_compose "${env_file}" ps database --format json >/dev/null 2>&1; then
    database_health="$(gss_compose "${env_file}" ps database --format json | php -r '$json = stream_get_contents(STDIN); $row = json_decode($json, true); echo $row["Health"] ?? "";')"
    if [[ "${database_health}" == "healthy" ]]; then
        pass 'PostgreSQL container is healthy'
    else
        fail "PostgreSQL is not healthy: ${database_health:-unknown}"
    fi
else
    fail 'PostgreSQL service is not available through Compose'
fi

if [[ -n "${app_port}" ]] && php -r "exit(@file_get_contents('http://127.0.0.1:${app_port}/up') ? 0 : 1);" >/dev/null 2>&1; then
    pass 'Laravel /up endpoint is reachable'
else
    fail 'Laravel /up endpoint is not reachable'
fi

if [[ -n "${reverb_port}" ]]; then
    if php -r "exit(@fsockopen('127.0.0.1', (int) '${reverb_port}') ? 0 : 1);" >/dev/null 2>&1; then
        pass 'Reverb port is reachable'
    else
        fail 'Reverb port is not reachable'
    fi
else
    fail 'REVERB_FORWARD_PORT is missing'
fi

if [[ -n "${https_port}" ]] && php -r "exit(@fsockopen('127.0.0.1', (int) '${https_port}') ? 0 : 1);" >/dev/null 2>&1; then
    pass 'Local HTTPS proxy is reachable'
else
    fail 'Local HTTPS proxy is not reachable'
fi

if gss_compose "${env_file}" exec -T proxy test -f /data/caddy/pki/authorities/local/root.crt >/dev/null 2>&1; then
    pass 'Local HTTPS certificate authority is available for workstation enrollment'
else
    fail 'Local HTTPS certificate authority is unavailable'
fi

if gss_compose "${env_file}" exec -T app php artisan migrate:status >/dev/null 2>&1; then
    pass 'Application can read migration status'
else
    fail 'Application cannot read migration status'
fi

if gss_compose "${env_file}" exec -T app php artisan tinker --execute='DB::connection()->getPdo(); echo "ok\n";' >/dev/null 2>&1; then
    pass 'Application database connection works'
else
    fail 'Application database connection failed'
fi

check_file_writable_dir "storage/app/public" 'Media directory'
check_file_writable_dir "${backup_path}" 'Backup directory'

free_kb="$(df -Pk . | awk 'NR == 2 { print $4 }')"
free_gb=$((free_kb / 1024 / 1024))
if [[ "${free_gb}" -lt "${min_free_gb}" ]]; then
    warn "Available disk space is ${free_gb} GiB; recommended minimum is ${min_free_gb} GiB"
else
    pass "Available disk space is ${free_gb} GiB"
fi

if gss_compose "${env_file}" port database 5432 2>/dev/null | grep -q '^127\.0\.0\.1:'; then
    pass 'PostgreSQL host port is bound to loopback only'
else
    fail 'PostgreSQL host port is not confirmed as loopback-only'
fi

if [[ -d "${backup_path}" ]]; then
    latest_backup="$(find "${backup_path}" -maxdepth 1 -type d -name 'gss-backup-*' -printf '%T@ %p\n' 2>/dev/null | sort -nr | head -n 1 | cut -d' ' -f2-)"
    if [[ -n "${latest_backup}" ]]; then
        pass "Latest backup found: $(basename "${latest_backup}")"
    else
        warn 'No backup directory found yet'
    fi
fi

if [[ "${failures}" -gt 0 ]]; then
    printf 'Health check completed with %d failure(s) and %d warning(s).\n' "${failures}" "${warnings}" >&2
    exit 1
fi

printf 'Health check completed with %d warning(s).\n' "${warnings}"
