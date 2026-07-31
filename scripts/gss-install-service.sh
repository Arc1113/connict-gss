#!/usr/bin/env bash

set -Eeuo pipefail

source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/gss-env.sh"

usage() {
    printf 'Install Graduation Stage System as an automatic Linux boot service.\n\n'
    gss_print_usage_common
    printf '  --unit-name NAME    systemd unit name. Default: graduation-stage-system\n'
    printf '\nRun this once with sudo after the production deployment has been built and migrated.\n'
}

env_file=".env.production"
unit_name="graduation-stage-system"

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
            usage >&2
            exit 2
            ;;
    esac
done

if [[ "${EUID}" -ne 0 ]]; then
    printf 'Run this installer with sudo so it can create and enable a system service.\n' >&2
    exit 1
fi

if [[ ! "${unit_name}" =~ ^[a-zA-Z0-9_.@-]+$ ]]; then
    printf 'Invalid systemd unit name: %s\n' "${unit_name}" >&2
    exit 1
fi

cd "${gss_project_dir}"
gss_validate_env_file "${env_file}"
gss_require_docker

env_path="$(realpath "${env_file}")"
unit_path="/etc/systemd/system/${unit_name}.service"

tmp_unit="$(mktemp)"
trap 'rm -f "${tmp_unit}"' EXIT

{
    printf '[Unit]\n'
    printf 'Description=Graduation Stage System\n'
    printf 'Requires=docker.service\n'
    printf 'After=docker.service network-online.target\n'
    printf 'Wants=network-online.target\n\n'
    printf '[Service]\n'
    printf 'Type=oneshot\n'
    printf 'RemainAfterExit=yes\n'
    printf 'WorkingDirectory="%s"\n' "${gss_project_dir}"
    printf 'ExecStart=/usr/bin/env bash "%s/scripts/gss-start.sh" --env-file "%s" --skip-migrate\n' "${gss_project_dir}" "${env_path}"
    printf 'ExecStop=/usr/bin/env bash "%s/scripts/gss-stop.sh" --env-file "%s" --no-backup\n' "${gss_project_dir}" "${env_path}"
    printf 'TimeoutStartSec=240\n'
    printf 'TimeoutStopSec=120\n\n'
    printf '[Install]\n'
    printf 'WantedBy=multi-user.target\n'
} > "${tmp_unit}"

install -m 0644 "${tmp_unit}" "${unit_path}"
systemctl daemon-reload
systemctl enable --now "${unit_name}.service"

printf 'Automatic startup installed and started: %s.service\n' "${unit_name}"
printf 'Use systemctl status %s.service to review startup status.\n' "${unit_name}"
