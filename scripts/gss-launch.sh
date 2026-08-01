#!/usr/bin/env bash

set -Eeuo pipefail

source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/gss-env.sh"

env_file=".env.production"
unit_name="graduation-stage-system"

usage() {
    printf 'Open the Graduation Stage System from a desktop session.\n\n'
    gss_print_usage_common
    printf '  --unit-name NAME    systemd unit name. Default: graduation-stage-system\n'
}

while [[ $# -gt 0 ]]; do
    case "$1" in
        --env-file)
            env_file="$2"
            shift 2
            ;;
        --unit-name)
            unit_name="$2"
            shift 2
            ;;
        --help)
            usage
            exit 0
            ;;
        *)
            printf 'Unknown option: %s\n' "$1" >&2
            exit 2
            ;;
    esac
done

if [[ ! "${unit_name}" =~ ^[a-zA-Z0-9_.@-]+$ ]]; then
    printf 'Invalid systemd unit name: %s\n' "${unit_name}" >&2
    exit 1
fi

cd "${gss_project_dir}"

notify_failure() {
    local message="$1"

    if command -v notify-send >/dev/null 2>&1; then
        notify-send --urgency=critical 'Graduation Stage System' "${message}"
    else
        printf '%s\n' "${message}" >&2
    fi
}

if [[ ! -f "${env_file}" ]]; then
    notify_failure "The deployment configuration is missing: ${env_file}"
    exit 1
fi

app_url="$(gss_required_env_value "${env_file}" APP_URL)"
app_port="$(gss_required_env_value "${env_file}" APP_FORWARD_PORT)"

if ! systemctl is-active --quiet "${unit_name}.service"; then
    if command -v pkexec >/dev/null 2>&1; then
        pkexec systemctl start "${unit_name}.service" || {
            notify_failure 'GSS could not start. Ask the technical operator to check the server.'
            exit 1
        }
    else
        systemctl start "${unit_name}.service" || {
            notify_failure 'GSS could not start. Ask the technical operator to check the server.'
            exit 1
        }
    fi
fi

ready=0
for _ in $(seq 1 60); do
    if php -r "exit(@file_get_contents('http://127.0.0.1:${app_port}/up') ? 0 : 1);" >/dev/null 2>&1; then
        ready=1
        break
    fi
    sleep 1
done

if [[ "${ready}" -ne 1 ]]; then
    notify_failure 'GSS started but did not become ready. Ask the technical operator to check the server.'
    exit 1
fi

if ! command -v xdg-open >/dev/null 2>&1; then
    notify_failure "GSS is ready at ${app_url}, but no desktop browser launcher is available."
    exit 1
fi

xdg-open "${app_url}" >/dev/null 2>&1 &
