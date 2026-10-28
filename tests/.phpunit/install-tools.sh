#!/usr/bin/env bash
set -euo pipefail
script_dir="$(cd "$(dirname "$0")" && pwd)"
runtime="${PHP_BINARY:-php}"
temporary=''
trap 'if [[ -n "$temporary" ]]; then rm -f "$temporary"; fi' EXIT
entries="$("$runtime" -r '$m=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR);foreach($m as $n=>$v)echo $n,"\t",$v["url"],"\t",$v["sha256"],PHP_EOL;' "$script_dir/manifest.json")"
while IFS=$'\t' read -r filename url expected; do
    target="$script_dir/$filename"
    if [[ -f "$target" && "$(shasum -a 256 "$target" | cut -d ' ' -f 1)" == "$expected" ]]; then
        continue
    fi
    temporary="$(mktemp "$script_dir/.download.XXXXXX")"
    curl --fail --location --silent --show-error "$url" -o "$temporary"
    actual="$(shasum -a 256 "$temporary" | cut -d ' ' -f 1)"
    if [[ "$actual" != "$expected" ]]; then
        echo "Checksum mismatch for $filename" >&2
        exit 1
    fi
    mv "$temporary" "$target"
    temporary=''
done <<< "$entries"
