"""Retain strict parent/native receipts before reporting configured PHPUnit results."""
import base64
import hashlib
import json
import os
from pathlib import Path
import subprocess
import sys
import tempfile
import time
import xml.etree.ElementTree as ET

from process_support import run_suite
from authored_controls import synthetic_controls
from path_support import destination

ROOT = Path(__file__).resolve().parent.parent


def main():
    """Execute the pinned tool and reject every unaccounted diagnostic."""
    args = sys.argv[1:]
    requested = None
    for index, value in enumerate(args):
        if value == '--log-junit':
            if index + 1 >= len(args):
                raise ValueError('JUnit destination is missing.')
            requested = destination(args[index + 1])
        if value.startswith('--log-junit='):
            requested = destination(value.split('=', 1)[1])
    tool = ROOT / 'tests/.phpunit/phpunit-9.phar'
    if hashlib.sha256(tool.read_bytes()).hexdigest() != '6b5235f282c6ccab3674ac27c5b1ddbd1da838e74bf07002ddcfd030d31298d7':
        raise RuntimeError('Pinned PHPUnit checksum differs.')
    run = Path(tempfile.mkdtemp(prefix='low-widont-checked-', dir=os.environ.get('LOW_WIDONT_EVIDENCE_ROOT')))
    journal = run / 'diagnostics.jsonl'
    journal.touch(mode=0o600)
    report = run / 'suite.xml'
    binary = os.environ.get('PHP_BINARY', '/Users/tomjaeger/Library/Application Support/Herd/bin/php74')
    command = [binary, '-d', 'error_reporting=-1', '-d', 'display_errors=stderr', '-d', 'display_startup_errors=1',
               '-d', 'log_errors=0', '-d', 'html_errors=0', '-d', 'auto_prepend_file=' + str(ROOT / 'tests/Support/Diagnostics.php'),
               str(tool), '--do-not-cache-result', *args, '--log-junit', str(report)]
    env = os.environ.copy()
    env['LOW_WIDONT_DIAGNOSTIC_JOURNAL'] = str(journal)
    started = time.time()
    result = run_suite(command, cwd=ROOT, environment=env, timeout=600,
                       output_paths=(run / 'raw-stdout', run / 'raw-stderr'))
    (run / 'stdout').write_bytes(result.stdout_bytes)
    (run / 'stderr').write_bytes(result.stderr_bytes)
    sys.stdout.buffer.write(result.stdout_bytes)
    sys.stderr.buffer.write(result.stderr_bytes)
    issues = []
    records = [json.loads(line) for line in journal.read_bytes().splitlines()]
    startups = [r for r in records if r['kind'] == 'startup']
    digest = hashlib.sha256((ROOT / 'low_widont/low_widont.phar').read_bytes()).hexdigest()
    if not startups or any(r['error_reporting'] != -1 or r['display_errors'] != 'stderr' or r['display_startup_errors'] != '1'
                           or os.path.realpath(r['binary']) != os.path.realpath(binary) or r['archive_sha256'] != digest for r in startups):
        issues.append('Missing or nonstrict actual startup/input receipt.')
    starts = {r['id']: r for r in records if r['kind'] == 'native-start'}
    spawns = {r['id']: r for r in records if r['kind'] == 'native-spawn'}
    ends = {r['id']: r for r in records if r['kind'] == 'native-end'}
    cleanup = {r['id']: r for r in records if r['kind'] == 'native-cleanup'}
    if set(starts) != set(spawns) or set(starts) != set(ends) or set(starts) != set(cleanup):
        issues.append('Native invocation receipts are incomplete.')
    expected = {result.ownership['held_leader']} | {spawns[k]['pid'] for k in starts if not starts[k]['compile_only']}
    if {r['pid'] for r in startups} != expected or len(startups) != len(expected):
        issues.append('Native actual startup/PID accounting differs.')
    for identifier, record in ends.items():
        if not cleanup[identifier]['removed'] or Path(starts[identifier]['temporary']).exists():
            issues.append('Native spool cleanup is incomplete: ' + identifier)
        for field in ('stdout_base64', 'stderr_base64'):
            base64.b64decode(record[field], validate=True)
    counts = None
    if not report.is_file() or report.stat().st_mtime < started:
        issues.append('Fresh positive JUnit is missing.')
    else:
        document = ET.parse(report).getroot()
        suite = document if document.tag == 'testsuite' else document.find('testsuite')
        counts = {key: int(suite.get(key, 0)) for key in ('tests', 'assertions', 'errors', 'failures', 'warnings', 'skipped')}
        if not counts['tests'] or counts['tests'] != len(list(document.iter('testcase'))):
            issues.append('Configured case counts are empty or inconsistent.')
        if requested:
            requested = destination(requested)
            with requested.open('xb') as output:
                output.write(report.read_bytes())
    expected_events, expected_exits, bindings = set(), set(), []
    if report.is_file():
        try:
            expected_events, expected_exits, bindings = synthetic_controls(records, document, ROOT, result.ownership['held_leader'])
        except (RuntimeError, KeyError, ValueError, AssertionError) as error:
            issues.append('Synthetic control verification failed: ' + str(error))
    for index, record in enumerate(records):
        if record['kind'] == 'diagnostic' and index not in expected_events:
            issues.append('Unexpected retained diagnostic: ' + repr(record))
    for identifier, record in ends.items():
        if identifier not in expected_exits and (record['exit'] != 0 or record['stderr_base64'] != ''):
            issues.append('Unexpected native exit/stderr: ' + identifier)
    if result.stderr_bytes:
        issues.append('Unexpected main stderr.')
    receipt = dict(command=command, exit=result.returncode, checked_exit=70 if issues else result.returncode, counts=counts,
                   issues=issues, synthetic_control_bindings=bindings, ownership=result.ownership, archive_sha256=digest, startups=len(startups),
                   native_attempts=len(starts), native_cleanup=len(cleanup), junit=str(report),
                   journal_sha256=hashlib.sha256(journal.read_bytes()).hexdigest(),
                   stdout_sha256=hashlib.sha256(result.stdout_bytes).hexdigest(), stderr_sha256=hashlib.sha256(result.stderr_bytes).hexdigest())
    (run / 'receipt.json').write_text(json.dumps(receipt, indent=2) + '\n')
    print('\nLOW_WIDONT_RECEIPT=' + str(run / 'receipt.json'), file=sys.stderr)
    return receipt['checked_exit']


if __name__ == '__main__':
    raise SystemExit(main())
