#!/usr/bin/env bash
set -euo pipefail
exec docker compose exec -T app php artisan "$@"
