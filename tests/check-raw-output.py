#!/usr/bin/env python3
"""Prove raw/main/native output callbacks preserve bytes and cannot erase authority."""
import argparse
import json
import os
from pathlib import Path
import re
import subprocess
import xml.etree.ElementTree as ET


def main():
    """Retain actual callback and expected-output controls on one selected runtime."""
    parser = argparse.ArgumentParser()
    parser.add_argument('--php', required=True)
    parser.add_argument('output', type=Path)
    options = parser.parse_args()
    if not options.output.is_absolute() or options.output.resolve() != options.output or options.output.exists():
        raise ValueError('Use a fresh physical external control directory.')
    options.output.mkdir(mode=0o700)
    root = Path(__file__).resolve().parents[1]
    rows = []
    for native in (False, True):
        for mode in ('warning', 'literal', 'callback-warning', 'callback-literal') + (('argv-literal',) if native else ()):
            directory = options.output / (('native-' if native else 'main-') + mode)
            directory.mkdir(mode=0o700)
            fixture = 'RawEngineNativeControlTest.php' if native else 'RawEngineVisibilityControlTest.php'
            command = [str(root / 'tests/.phpunit/run-phpunit.sh'), '--no-configuration', '--bootstrap',
                       str(root / 'tests/bootstrap.php'), '--log-junit', str(directory / 'suite.xml'),
                       str(root / 'tests/fixtures' / fixture)]
            environment = dict(os.environ, PHP_BINARY=options.php, LOW_WIDONT_EVIDENCE_ROOT=str(directory),
                               LOW_WIDONT_RAW_ENGINE_MODE=mode, LOW_WIDONT_RAW_CHILD_JUNIT=str(directory / 'child.xml'))
            result = subprocess.run(command, cwd=root, env=environment, capture_output=True, timeout=90)
            (directory / 'stdout').write_bytes(result.stdout)
            (directory / 'stderr').write_bytes(result.stderr)
            marker = re.search(rb'LOW_WIDONT_RECEIPT=([^\r\n]+)', result.stderr)
            if marker is None:
                raise RuntimeError('The actual raw control receipt is missing.')
            receipt = Path(os.fsdecode(marker.group(1)))
            data = json.loads(receipt.read_bytes())
            cases = list(ET.parse(directory / 'suite.xml').getroot().iter('testcase'))
            expected = 70 if 'warning' in mode else 0
            if result.returncode != expected or bool(data['issues']) != bool(expected):
                raise RuntimeError('The raw body authority outcome differs.')
            if any(case.findall(kind) for case in cases for kind in ('error', 'failure', 'warning', 'skipped')):
                raise RuntimeError('The deliberately diagnostic tool fixture did not retain its green inner assertions.')
            rows.append(dict(native=native, mode=mode, command=command, exit=result.returncode,
                             counts=data['counts'], receipt=str(receipt)))
            (options.output / 'results.json').write_text(json.dumps(rows, indent=2) + '\n')
    print(json.dumps(dict(controls=len(rows), results=str(options.output / 'results.json'))))


if __name__ == '__main__':
    main()
