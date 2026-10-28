#!/usr/bin/env bash
set -euo pipefail
repo_root="$(cd "$(dirname "$0")/../.." && pwd)"
runtime="${PHP_BINARY:-php}"
"$runtime" "$repo_root/tests/tools/verify-tool.php"
exec "$runtime" -d error_reporting=-1 "$repo_root/tests/.phpunit/phpunit-9.phar" "$@"
