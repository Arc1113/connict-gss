#!/usr/bin/env bash

set -Eeuo pipefail

gss_script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
gss_project_dir="$(cd "${gss_script_dir}/.." && pwd)"

gss_print_usage_common() {
    printf 'Common options:\n'
    printf '  --env-file PATH     Environment file to pass to Docker Compose. Default: .env\n'
    printf '  --help              Show command help.\n'
}

gss_env_value() {
    local env_file="$1"
    local key="$2"
    local value

    value="$(awk -v wanted="${key}" '
        /^[[:space:]]*#/ { next }
        index($0, "=") == 0 { next }
        {
            split($0, parts, "=")
            name = parts[1]
            gsub(/^[[:space:]]+|[[:space:]]+$/, "", name)
            if (name == wanted) {
                print substr($0, index($0, "=") + 1)
            }
        }
    ' "${env_file}" | tail -n 1)"

    value="${value%$'\r'}"
    value="${value#\"}"
    value="${value%\"}"
    value="${value#\'}"
    value="${value%\'}"

    printf '%s' "${value}"
}

gss_required_env_value() {
    local env_file="$1"
    local key="$2"
    local value

    value="$(gss_env_value "${env_file}" "${key}")"

    if [[ -z "${value}" ]]; then
        printf 'Missing required environment value: %s\n' "${key}" >&2
        return 1
    fi

    printf '%s' "${value}"
}

gss_compose() {
    local env_file="$1"
    shift

    GSS_ENV_FILE="${env_file}" docker compose \
        --env-file "${env_file}" \
        -f compose.yaml \
        -f compose.deploy.yaml \
        "$@"
}

gss_validate_env_file() {
    local env_file="$1"

    if [[ ! -f "${env_file}" ]]; then
        printf 'Environment file not found: %s\n' "${env_file}" >&2
        return 1
    fi

    local app_env app_debug app_key app_url db_password https_host reverb_key reverb_secret
    app_env="$(gss_required_env_value "${env_file}" APP_ENV)"
    app_debug="$(gss_required_env_value "${env_file}" APP_DEBUG)"
    app_key="$(gss_required_env_value "${env_file}" APP_KEY)"
    app_url="$(gss_required_env_value "${env_file}" APP_URL)"
    https_host="$(gss_required_env_value "${env_file}" GSS_HTTPS_HOST)"
    db_password="$(gss_required_env_value "${env_file}" DB_PASSWORD)"
    reverb_key="$(gss_required_env_value "${env_file}" REVERB_APP_KEY)"
    reverb_secret="$(gss_required_env_value "${env_file}" REVERB_APP_SECRET)"

    if [[ "${app_env}" != "production" ]]; then
        printf 'APP_ENV must be production for deployment startup; found: %s\n' "${app_env}" >&2
        return 1
    fi

    if [[ "${app_debug}" != "false" ]]; then
        printf 'APP_DEBUG must be false for deployment startup.\n' >&2
        return 1
    fi

    if [[ "${app_url}" != https://* || "${app_url}" == *"localhost"* || "${app_url}" == *"127.0.0.1"* ]]; then
        printf 'APP_URL must use the server LAN address for multi-device deployment.\n' >&2
        return 1
    fi

    if [[ -z "${https_host}" || "${app_url}" != "https://${https_host}"* ]]; then
        printf 'GSS_HTTPS_HOST must match the LAN host used by APP_URL.\n' >&2
        return 1
    fi

    if [[ "${app_key}" == "" || "${db_password}" == "change-me" || "${db_password}" == "replace-with-a-strong-local-password" ]]; then
        printf 'Deployment secrets must be set to real local values before startup.\n' >&2
        return 1
    fi

    if [[ "${reverb_key}" == "replace-with-a-random-local-key" || "${reverb_secret}" == "replace-with-a-random-local-secret" ]]; then
        printf 'Reverb deployment secrets must be replaced before startup.\n' >&2
        return 1
    fi
}

gss_require_docker() {
    if ! command -v docker >/dev/null 2>&1; then
        printf 'Docker is required but was not found in PATH.\n' >&2
        return 1
    fi

    if ! docker compose version >/dev/null 2>&1; then
        printf 'Docker Compose is required but is not available.\n' >&2
        return 1
    fi
}

gss_ensure_deployment_dirs() {
    mkdir -p \
        "${gss_project_dir}/storage/app/public" \
        "${gss_project_dir}/storage/app/gss-certificates" \
        "${gss_project_dir}/storage/app/gss-backups" \
        "${gss_project_dir}/storage/framework/cache/data" \
        "${gss_project_dir}/storage/framework/sessions" \
        "${gss_project_dir}/storage/framework/views" \
        "${gss_project_dir}/storage/logs"
}

gss_asset_manifest_path() {
    printf '%s/public/build/manifest.json' "${gss_project_dir}"
}
