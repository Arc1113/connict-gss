#!/usr/bin/env bash

set -euo pipefail

repository_path="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
storage_path="$repository_path/.browser-e2e-storage"
storage_path_in_container="/app/.browser-e2e-storage"
database_name="graduation_stage_system_testing"
port="${GSS_E2E_PORT:-8013}"
container_name="gss-browser-e2e-app"

cd "$repository_path"
set -a
source .env
set +a

cleanup() {
    docker rm -f "$container_name" >/dev/null 2>&1 || true
    rm -rf "$storage_path"
}

trap cleanup EXIT
cleanup
mkdir -p \
    "$storage_path/app/private" \
    "$storage_path/app/public" \
    "$storage_path/framework/cache/data" \
    "$storage_path/framework/sessions" \
    "$storage_path/framework/views" \
    "$storage_path/logs"

docker compose run --rm -T \
    -e "DB_DATABASE=$database_name" \
    -e "LARAVEL_STORAGE_PATH=$storage_path_in_container" \
    app php artisan migrate:fresh --seed --force

APP_FORWARD_PORT="$port" docker compose run -d --service-ports --name "$container_name" \
    -e "APP_URL=http://127.0.0.1:$port" \
    -e "DB_DATABASE=$database_name" \
    -e "LARAVEL_STORAGE_PATH=$storage_path_in_container" \
    app php artisan serve --host=0.0.0.0 --port=8000 --no-reload >/dev/null

for _ in {1..30}; do
    if curl --fail --silent "http://127.0.0.1:$port" >/dev/null; then
        break
    fi

    sleep 1
done

curl --fail --silent "http://127.0.0.1:$port" >/dev/null

GSS_E2E_BASE_URL="http://127.0.0.1:$port" \
GSS_E2E_DB_DATABASE="$database_name" \
GSS_E2E_PASSWORD="$GSS_SEED_PASSWORD" \
GSS_E2E_STORAGE_PATH="$storage_path_in_container" \
npx playwright test "$@"
