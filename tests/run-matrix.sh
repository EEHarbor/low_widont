#!/usr/bin/env bash
set -uo pipefail
repo_root="$(cd "$(dirname "$0")/.." && pwd)"
cd "$repo_root"
mkdir -p tests/reports
run_dir="$(mktemp -d "$repo_root/tests/reports/matrix.XXXXXX")" || exit 1
result=0
for version in 74 80 81 82 83 84 85; do
    runtime="/Users/tomjaeger/Library/Application Support/Herd/bin/php$version"
    "$runtime" -v > "$run_dir/php$version.version.txt" 2>&1
    PHP_BINARY="$runtime" ./tests/.phpunit/run-phpunit.sh -c phpunit.xml --log-junit "$run_dir/php$version.xml" "$@" > "$run_dir/php$version.log" 2>&1
    code=$?
    if [[ $code -ne 0 || ! -s "$run_dir/php$version.xml" || "$(<"$run_dir/php$version.log")" == *'No tests executed!'* ]]; then
        result=1
    fi
    echo "PHP $version exit=$code"
    tail -n 3 "$run_dir/php$version.log"
done
echo "REPORT_DIR=$run_dir"
exit "$result"
