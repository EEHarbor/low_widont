#!/usr/bin/env bash
set -euo pipefail
repo_root="$(cd "$(dirname "$0")/../.." && pwd)"
exec python3 -B "$repo_root/tests/phpunit_runner.py" "$@"
