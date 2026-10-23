#!/usr/bin/env bash
set -uo pipefail
repo_root="$(cd "$(dirname "$0")/.." && pwd)"
cd "$repo_root"
runtime="${PHP_BINARY:-/Users/tomjaeger/Library/Application Support/Herd/bin/php82}"
mkdir -p tests/reports
export LOW_WIDONT_REPORT_DIR
LOW_WIDONT_REPORT_DIR="$(mktemp -d "$repo_root/tests/reports/coverage.XXXXXX")" || exit 1
version="$("$runtime" -r 'echo PHP_MAJOR_VERSION . PHP_MINOR_VERSION;')"
extension="${LOW_WIDONT_XDEBUG_EXTENSION:-/Applications/Herd.app/Contents/Resources/xdebug/xdebug-$version-arm64.so}"
export XDEBUG_MODE=coverage
ini="$LOW_WIDONT_REPORT_DIR/php.ini"
"$runtime" -r '$i=php_ini_loaded_file(); $c=$i?file_get_contents($i):""; file_put_contents($argv[1], $c."\nzend_extension=\"".$argv[2]."\"\nxdebug.mode=coverage\nerror_reporting=-1\n");' "$ini" "$extension" || exit 1
export PHPRC="$ini"
"$runtime" tests/tools/verify-tool.php || exit 1
"$runtime" tests/tools/native-coverage-probe.php "$LOW_WIDONT_REPORT_DIR/native-instrumentation.json" || exit 1
"$runtime" tests/.phpunit/phpunit-9.phar -c tests/tooling/phpunit.xml --coverage-php "$LOW_WIDONT_REPORT_DIR/instrumentation.php" > "$LOW_WIDONT_REPORT_DIR/instrumentation.log" 2>&1 || exit 1
"$runtime" tests/tools/validate-coverage.php "$LOW_WIDONT_REPORT_DIR" || exit 1
config="$("$runtime" tests/tools/prepare-coverage.php)" || exit 1
"$runtime" tests/.phpunit/phpunit-9.phar -c "$config" --coverage-clover "$LOW_WIDONT_REPORT_DIR/clover.xml" --coverage-php "$LOW_WIDONT_REPORT_DIR/coverage.php" "$@" > "$LOW_WIDONT_REPORT_DIR/run.log" 2>&1
result=$?
cat "$LOW_WIDONT_REPORT_DIR/run.log"
if [[ "$(<"$LOW_WIDONT_REPORT_DIR/run.log")" == *'No tests executed!'* || ! -f "$LOW_WIDONT_REPORT_DIR/coverage.php" ]]; then
    echo "Invalid coverage run: no tests or no coverage report." >&2
    exit 1
fi
"$runtime" tests/tools/summarize-coverage.php "$LOW_WIDONT_REPORT_DIR/coverage.php" > "$LOW_WIDONT_REPORT_DIR/branches.json" || exit 1
echo "REPORT_DIR=$LOW_WIDONT_REPORT_DIR"
exit "$result"
