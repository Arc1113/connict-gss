#!/usr/bin/env bash

set -Eeuo pipefail

source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/gss-env.sh"

usage() {
    printf 'Prepare and validate a local event release.\n\n'
    gss_print_usage_common
    printf '  --allow-dirty       Continue when the Git working tree has local changes.\n'
    printf '  --skip-audits       Skip Composer and npm security audits.\n'
    printf '  --skip-tests        Skip Laravel tests. Use only for emergency rebuilds.\n'
}

env_file=".env"
allow_dirty=0
skip_audits=0
skip_tests=0

while [[ $# -gt 0 ]]; do
    case "$1" in
        --env-file)
            env_file="$2"
            shift 2
            ;;
        --allow-dirty)
            allow_dirty=1
            shift
            ;;
        --skip-audits)
            skip_audits=1
            shift
            ;;
        --skip-tests)
            skip_tests=1
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

if [[ "${allow_dirty}" -ne 1 && -n "$(git status --short)" ]]; then
    printf 'Git working tree is dirty. Commit or review changes, or use --allow-dirty intentionally.\n' >&2
    exit 1
fi

if [[ -f .env ]]; then
    printf 'Verified .env exists locally; it is ignored and must not be committed.\n'
fi

composer validate --strict

if [[ "${skip_tests}" -ne 1 ]]; then
    php artisan test --compact
fi

vendor/bin/pint --test
npm ci
npm run lint
npm run build

if [[ "${skip_audits}" -ne 1 ]]; then
    composer audit
    npm audit --audit-level=moderate
fi

if [[ ! -f public/build/manifest.json ]]; then
    printf 'Production asset manifest was not generated.\n' >&2
    exit 1
fi

GSS_ENV_FILE="${env_file}" docker compose --env-file "${env_file}" -f compose.yaml -f compose.deploy.yaml config --quiet

if [[ -f public/hot ]]; then
    printf 'public/hot exists. Remove Vite dev-server marker before release.\n' >&2
    exit 1
fi

if git ls-files --error-unmatch .env >/dev/null 2>&1; then
    printf '.env is tracked by Git; remove it before release.\n' >&2
    exit 1
fi

if git ls-files 'storage/app/gss-backups/*' | grep -q .; then
    printf 'Backup artifacts are tracked by Git; remove them before release.\n' >&2
    exit 1
fi

if git ls-files 'docs/evidence/**/*.png' | grep -q .; then
    printf 'Visual evidence PNG files are tracked by Git; remove them before release.\n' >&2
    exit 1
fi

printf 'Release checks completed successfully.\n'
