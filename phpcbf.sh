#!/usr/bin/env bash
set -euo pipefail
exec docker compose exec -T app vendor/bin/phpcbf "$@"
