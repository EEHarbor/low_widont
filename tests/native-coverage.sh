#!/usr/bin/env bash
set -uo pipefail
repo_root="$(cd "$(dirname "$0")/.." && pwd)"
cd "$repo_root"
runtime="${PHP_BINARY:-/Users/tomjaeger/Library/Application Support/Herd/bin/php82}"
version="$("$runtime" -r 'echo PHP_MAJOR_VERSION . PHP_MINOR_VERSION;')" || exit 1
extension="${LOW_WIDONT_XDEBUG_EXTENSION:-/Applications/Herd.app/Contents/Resources/xdebug/xdebug-$version-arm64.so}"
mkdir -p tests/reports
run="$(mktemp -d "$repo_root/tests/reports/native.XXXXXX")" || exit 1
ini="$run/php.ini"
"$runtime" -r '$i=php_ini_loaded_file();$c=$i?file_get_contents($i):"";if(strpbrk($argv[2],"\r\n")!==false){throw new RuntimeException("Invalid extension path.");}$c.="\nzend_extension=\"".addcslashes($argv[2],"\\\"")."\"\nxdebug.mode=coverage\nerror_reporting=-1\n";if(file_put_contents($argv[1],$c)!==strlen($c)){throw new RuntimeException("Cannot write inherited native coverage INI.");}' "$ini" "$extension" || exit 1
export PHPRC="$ini"
export XDEBUG_MODE=coverage
"$runtime" tests/tools/raw-coverage.php "$run/main.json" > "$run/run.log" 2>&1
result=$?
cat "$run/run.log"
if [[ ! -s "$run/main.json" ]]; then
    echo 'Native coverage did not produce a fresh source report.' >&2
    exit 1
fi
echo "NATIVE_REPORT_DIR=$run"
exit "$result"
