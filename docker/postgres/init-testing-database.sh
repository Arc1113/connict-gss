#!/bin/sh

set -eu

if [ -z "${POSTGRES_TEST_DB:-}" ]; then
    exit 0
fi

createdb \
    --maintenance-db="$POSTGRES_DB" \
    --owner="$POSTGRES_USER" \
    --username="$POSTGRES_USER" \
    "$POSTGRES_TEST_DB"
