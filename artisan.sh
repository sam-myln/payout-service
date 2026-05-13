#!/usr/bin/env bash
set -euo pipefail
if [[ "${1:-}" == "tinker" ]]; then
    exec docker compose exec app php artisan "$@"
else
    exec docker compose exec -T app php artisan "$@"
fi
