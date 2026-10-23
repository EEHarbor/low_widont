#!/usr/bin/env bash
set -euo pipefail
repo_root="$(cd "$(dirname "$0")/.." && pwd)"
runtime="${PHP_BINARY:-/Users/tomjaeger/Library/Application Support/Herd/bin/php83}"
mkdir -p "$repo_root/tests/reports"
report_dir="$(mktemp -d "$repo_root/tests/reports/warning-observations.XXXXXX")"
runtime_suffix="$("$runtime" -r 'echo PHP_MAJOR_VERSION, PHP_MINOR_VERSION;')"
extension="/Applications/Herd.app/Contents/Resources/xdebug/xdebug-${runtime_suffix}-arm64.so"
if [[ ! -f "$extension" ]]; then
  echo "Selected runtime Xdebug is unavailable: $extension" >&2
  exit 2
fi
ini="$report_dir/php.ini"
"$runtime" -r '$i=php_ini_loaded_file();$c=$i?file_get_contents($i):"";if(strpbrk($argv[2],"\r\n")!==false){throw new RuntimeException("Invalid extension path.");}$c.="\nzend_extension=\"".addcslashes($argv[2],"\\\"")."\"\nxdebug.mode=coverage\nerror_reporting=-1\n";if(file_put_contents($argv[1],$c)!==strlen($c)){throw new RuntimeException("Cannot write observation INI.");}' "$ini" "$extension"
"$runtime" "$repo_root/tests/tools/verify-tool.php"
echo "$report_dir"
set +e
LOW_WIDONT_WARNING_OBSERVATIONS_DIR="$report_dir" PHPRC="$ini" XDEBUG_MODE=coverage \
  "$runtime" -d error_reporting=-1 -d display_errors=1 -d log_errors=0 \
  "$repo_root/tests/.phpunit/phpunit-9.phar" -c "$repo_root/phpunit.xml" \
  "$repo_root/tests/tests/Unit/WarningContinuationTest.php" --log-junit "$report_dir/junit.xml" \
  > "$report_dir/phpunit.log" 2>&1
status=$?
set -e
printf '%s\n' "$status" > "$report_dir/exit-status.txt"
cat "$report_dir/phpunit.log"
python3 -B "$repo_root/tests/tools/warning-report.py" "$report_dir" > "$report_dir/summary.json"
exit "$status"
