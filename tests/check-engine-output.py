#!/usr/bin/env python3
"""Require real native diagnostic frames to reject while ordinary literal bytes pass."""
import argparse
import json
import os
from pathlib import Path
import re
import subprocess
import xml.etree.ElementTree as ET

ROOT = Path(__file__).resolve().parent.parent


def main():
    """Keep fresh actual streams/JUnit/receipts for each bounded authority context."""
    parser = argparse.ArgumentParser()
    parser.add_argument('--php', required=True)
    parser.add_argument('output', type=Path)
    options = parser.parse_args()
    output = options.output
    if not output.is_absolute() or output != output.resolve() or output.exists():
        raise ValueError('Use a fresh canonical external output directory.')
    output.mkdir(mode=0o700)
    rows = []
    for mode in ('main-plain', 'main-html', 'native-plain', 'native-html',
                 'main-literal', 'native-literal', 'main-expected', 'main-late', 'native-late'):
        directory = output / mode
        directory.mkdir(mode=0o700)
        command = [str(ROOT / 'tests/.phpunit/run-phpunit.sh'), '-c', str(ROOT / 'phpunit.xml'),
                   str(ROOT / 'tests/fixtures/EngineVisibilityControlTest.php'),
                   '--log-junit', str(directory / 'suite.xml')]
        environment = dict(os.environ, PHP_BINARY=options.php, LOW_WIDONT_EVIDENCE_ROOT=str(directory),
                           LOW_WIDONT_ENGINE_CONTROL=mode)
        result = subprocess.run(command, cwd=ROOT, env=environment, capture_output=True, timeout=60)
        (directory / 'stdout').write_bytes(result.stdout)
        (directory / 'stderr').write_bytes(result.stderr)
        marker = re.search(rb'LOW_WIDONT_RECEIPT=([^\r\n]+)', result.stderr)
        if marker is None:
            raise RuntimeError('Missing actual checked engine-control receipt.')
        receipt_path = Path(os.fsdecode(marker.group(1)))
        receipt = json.loads(receipt_path.read_bytes())
        cases = list(ET.parse(directory / 'suite.xml').getroot().iter('testcase'))
        if len(cases) != 1 or any(case.findall(kind) for case in cases for kind in ('error', 'failure', 'warning', 'skipped')):
            raise RuntimeError('The original deliberate tool fixture did not execute cleanly.')
        expected = 0 if 'literal' in mode else 70
        if result.returncode != expected or bool(receipt['issues']) != bool(expected):
            raise RuntimeError('Native visibility authority outcome differs: ' + mode)
        rows.append(dict(mode=mode, command=command, exit=result.returncode,
                         counts=receipt['counts'], receipt=str(receipt_path)))
        (output / 'results.json').write_text(json.dumps(rows, indent=2) + '\n')
    print(json.dumps(dict(controls=len(rows), results=str(output / 'results.json'))))


if __name__ == '__main__':
    main()
