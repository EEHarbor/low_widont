#!/usr/bin/env python3
"""Exercise actual checked native inline/prelude authority on one selected runtime."""
import argparse
import base64
import hashlib
import json
import os
from pathlib import Path
import re
import shutil
import subprocess
import xml.etree.ElementTree as ET

ROOT = Path(__file__).resolve().parent.parent


def main():
    """Retain exact green inner cases and require rejection of every caught fault."""
    parser = argparse.ArgumentParser()
    parser.add_argument('--php', required=True)
    parser.add_argument('output', type=Path)
    options = parser.parse_args()
    output = options.output
    if (not output.is_absolute() or output != output.resolve() or output.exists()
            or ROOT == output or ROOT in output.parents):
        raise RuntimeError('Use a fresh canonical external evidence directory.')
    output.mkdir(mode=0o700)
    rows = []
    fixture = ROOT / 'tests/fixtures/NativeCliDiagnosticControlTest.php'
    for mode in ('short', 'long', 'namespace', 'brace', 'declare-brace', 'declare-colon', 'prelude-caught', 'prelude-healthy'):
        if shutil.disk_usage(output).free < 2 * 1024**3:
            raise RuntimeError('Native CLI controls require2GiB free before each invocation.')
        directory = output / mode
        directory.mkdir(mode=0o700)
        environment = os.environ.copy()
        environment.update(PHP_BINARY=options.php, LOW_WIDONT_EVIDENCE_ROOT=str(directory),
                           LOW_WIDONT_NATIVE_CLI_CONTROL_MODE=mode)
        command = [str(ROOT / 'tests/.phpunit/run-phpunit.sh'), '-c', str(ROOT / 'phpunit.xml'),
                   str(fixture), '--log-junit', str(directory / 'suite.xml')]
        result = subprocess.run(command, cwd=ROOT, env=environment, capture_output=True, timeout=60)
        (directory / 'stdout').write_bytes(result.stdout)
        (directory / 'stderr').write_bytes(result.stderr)
        marker = re.search(rb'LOW_WIDONT_RECEIPT=([^\r\n]+)', result.stderr)
        if marker is None:
            raise RuntimeError('Actual checked receipt is missing: ' + mode)
        path = Path(os.fsdecode(marker.group(1)))
        receipt = json.loads(path.read_text())
        cases = list(ET.parse(directory / 'suite.xml').iter('testcase'))
        if len(cases) != 1:
            raise RuntimeError('The owned control did not execute exactly once: ' + mode)
        expected = {'tests': 1, 'assertions': 2 if mode == 'prelude-healthy' else 1,
                    'errors': 0, 'failures': 0, 'warnings': 0, 'skipped': 0}
        if receipt['counts'] != expected:
            raise RuntimeError('The actual inner control was not green: ' + mode)
        records = [json.loads(line) for line in (path.parent / 'diagnostics.jsonl').read_bytes().splitlines()]
        diagnostics = [record for record in records if record['kind'] == 'diagnostic']
        if mode == 'prelude-healthy':
            if result.returncode != 0 or receipt['issues'] or diagnostics:
                raise RuntimeError('A healthy uninstrumented caller prelude was rejected.')
        else:
            if (result.returncode != 70 or len(diagnostics) != 1
                    or diagnostics[0]['severity'] != 16384
                    or base64.b64decode(diagnostics[0]['message_base64'], validate=True) != b'owned native CLI caught diagnostic'
                    or not any('Unexpected retained diagnostic:' in issue for issue in receipt['issues'])):
                raise RuntimeError('The caught child diagnostic did not refuse validation: ' + mode)
            child = [record for record in records if record['kind'] == 'native-end'
                     and record['pid'] == diagnostics[0]['pid']]
            if len(child) != 1 or child[0]['exit'] != 0 or child[0]['stderr_base64'] != '':
                raise RuntimeError('An alternate child failure replaced the caught diagnostic: ' + mode)
        rows.append({'mode': mode, 'command': command, 'checked_exit': result.returncode,
                     'counts': expected, 'diagnostics': len(diagnostics), 'receipt': str(path),
                     'archive_sha256': receipt['archive_sha256'], 'junit_sha256': hashlib.sha256((directory / 'suite.xml').read_bytes()).hexdigest()})
        (output / 'results.json').write_text(json.dumps(rows, indent=2) + '\n')
    print(json.dumps({'runtime': options.php, 'controls': len(rows), 'results': str(output / 'results.json')}))


if __name__ == '__main__':
    main()
