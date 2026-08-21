#!/usr/bin/env bash
set -euo pipefail

COUNT="${COUNT:-1000}"
COMPANY="${COMPANY:-1}"

php artisan rodante:seed-scale "$COUNT" --company="$COMPANY"
php artisan rodante:bench-scale --company="$COMPANY"
