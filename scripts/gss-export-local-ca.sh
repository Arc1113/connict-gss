#!/usr/bin/env bash

set -Eeuo pipefail

source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/gss-env.sh"

usage() {
    printf 'Export the local HTTPS certificate authority for ceremony workstations.\n\n'
    gss_print_usage_common
    printf '  --output PATH       Certificate output path. Default: storage/app/gss-certificates/gss-local-ca.crt\n'
}

env_file=".env"
output_path="storage/app/gss-certificates/gss-local-ca.crt"

while [[ $# -gt 0 ]]; do
    case "$1" in
        --env-file)
            env_file="$2"
            shift 2
            ;;
        --output)
            output_path="$2"
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
gss_require_docker

mkdir -p "$(dirname "${output_path}")"
gss_compose "${env_file}" cp \
    proxy:/data/caddy/pki/authorities/local/root.crt \
    "${output_path}"
chmod 0644 "${output_path}"

printf 'Local CA exported to %s\n' "${output_path}"
printf 'Install this certificate as a trusted root only on approved ceremony workstations.\n'
