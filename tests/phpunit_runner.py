"""Retain strict parent/native receipts before reporting configured PHPUnit results."""
import base64
import hashlib
import json
import os
import secrets
import stat
from pathlib import Path
import subprocess
import sys
import tempfile
import time
import xml.etree.ElementTree as ET

from engine_output import native_stdout
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
    engine = run / 'engine.log'
    body_output = run / 'case-stdout.jsonl'
    engine.touch(mode=0o600)
    body_output.touch(mode=0o600)
    engine_identity = engine.stat()
    body_identity = body_output.stat()
    body_nonce = secrets.token_hex(16)
    body_capability = dict(path=str(body_output), device=body_identity.st_dev,
                           inode=body_identity.st_ino, nonce=body_nonce)
    encoded_body = base64.b64encode(json.dumps(body_capability).encode()).decode('ascii')
    report = run / 'suite.xml'
    binary = os.environ.get('PHP_BINARY', '/Users/tomjaeger/Library/Application Support/Herd/bin/php74')
    env = os.environ.copy()
    env['LOW_WIDONT_DIAGNOSTIC_JOURNAL'] = str(journal)
    prepare_command = [binary, '-d', 'error_reporting=-1', '-d', 'display_errors=stderr',
                       '-d', 'display_startup_errors=1', '-d', 'log_errors=0', '-d', 'phar.readonly=0',
                       str(ROOT / 'tests/.phpunit/prepare-output.php'), str(tool),
                       str(run / 'phpunit-output.phar'), str(ROOT / 'tests/support/BodyOutput.php')]
    prepared_result = run_suite(prepare_command, cwd=ROOT, environment=env, timeout=30,
                                output_paths=(run / 'prepare-stdout', run / 'prepare-stderr'))
    generation = dict(command=prepare_command, exit=prepared_result.returncode, ownership=prepared_result.ownership,
                      stdout_base64=base64.b64encode(prepared_result.stdout_bytes).decode('ascii'),
                      stderr_base64=base64.b64encode(prepared_result.stderr_bytes).decode('ascii'))
    (run / 'tool-generation.json').write_text(json.dumps(generation, indent=2) + '\n')
    if prepared_result.returncode != 0 or prepared_result.stderr_bytes:
        raise RuntimeError('The pinned output-capture tool could not be prepared; complete generation streams retained.')
    prepared = json.loads(prepared_result.stdout_bytes)
    prepared_file = Path(prepared['target'])
    actual = prepared_file.lstat()
    if (prepared['pid'] != prepared_result.ownership['held_leader'] or prepared['error_reporting'] != -1
            or prepared['display_errors'] != 'stderr' or prepared['display_startup_errors'] != '1'
            or os.path.realpath(prepared['binary']) != os.path.realpath(binary)
            or prepared['source_sha256'] != hashlib.sha256(tool.read_bytes()).hexdigest()
            or prepared['generator_sha256'] != hashlib.sha256((ROOT / 'tests/.phpunit/prepare-output.php').read_bytes()).hexdigest()
            or prepared['hook_sha256'] != hashlib.sha256((ROOT / 'tests/support/BodyOutput.php').read_bytes()).hexdigest()
            or prepared['target_sha256'] != hashlib.sha256(prepared_file.read_bytes()).hexdigest()
            or (actual.st_dev, actual.st_ino) != (prepared['device'], prepared['inode'])
            or stat.S_IMODE(actual.st_mode) != 0o600 or actual.st_nlink != 1
            or not prepared['only_one_member_changed'] or not prepared['original_stub_alias_signature_algorithm_retained']):
        raise RuntimeError('The generated PHPUnit input is not the original owned one-seam copy.')
    encoded_tool = base64.b64encode(json.dumps(prepared).encode()).decode('ascii')
    command = [binary, '-d', 'error_reporting=-1', '-d', 'display_errors=stderr', '-d', 'display_startup_errors=1',
               '-d', 'log_errors=0', '-d', 'error_log=' + str(engine),
               '-d', 'low_widont.case_stdout="' + encoded_body + '"',
               '-d', 'low_widont.phpunit_tool="' + encoded_tool + '"', '-d', 'html_errors=0', '-d', 'auto_prepend_file=' + str(ROOT / 'tests/Support/Diagnostics.php'),
               str(prepared_file), '--do-not-cache-result', *args, '--log-junit', str(report)]
    started = time.time()
    result = run_suite(command, cwd=ROOT, environment=env, timeout=600,
                       output_paths=(run / 'raw-stdout', run / 'raw-stderr'))
    (run / 'stdout').write_bytes(result.stdout_bytes)
    (run / 'stderr').write_bytes(result.stderr_bytes)
    sys.stdout.buffer.write(result.stdout_bytes)
    sys.stderr.buffer.write(result.stderr_bytes)
    issues = []
    for path, original in ((engine, engine_identity), (body_output, body_identity)):
        named = path.lstat()
        if (not stat.S_ISREG(named.st_mode) or stat.S_IMODE(named.st_mode) != 0o600
                or named.st_nlink != 1 or (named.st_dev, named.st_ino) != (original.st_dev, original.st_ino)):
            raise RuntimeError('Owned engine/body capture changed identity.')
    body_rows = [json.loads(line) for line in body_output.read_bytes().splitlines()]
    for row in body_rows:
        raw = base64.b64decode(row['stdout_base64'], validate=True)
        if (row['pid'] != result.ownership['held_leader'] or row['nonce'] != body_nonce
                or not isinstance(row['class'], str) or not isinstance(row['name'], str)):
            raise RuntimeError('Actual body output belongs to another invocation.')
        if native_stdout(raw):
            issues.append('Unexpected native engine frame in testcase output: ' + row['class'] + '::' + row['name'])
    if engine.stat().st_size:
        issues.append('Unexpected private main engine log.')
    if native_stdout(result.stdout_bytes):
        issues.append('Unexpected native engine frame in main stdout.')
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
        for field in ('stdout_base64', 'stderr_base64', 'engine_base64', 'case_stdout_base64'):
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
            expected_events, expected_exits, bindings = synthetic_controls(records, document, ROOT, result.ownership['held_leader'], prepared)
        except (RuntimeError, KeyError, ValueError, AssertionError) as error:
            issues.append('Synthetic control verification failed: ' + str(error))
    for index, record in enumerate(records):
        if record['kind'] == 'diagnostic' and index not in expected_events:
            issues.append('Unexpected retained diagnostic: ' + repr(record))
    for identifier, record in ends.items():
        raw = base64.b64decode(record['stdout_base64'], validate=True)
        engine_bytes = base64.b64decode(record['engine_base64'], validate=True)
        case_bytes = base64.b64decode(record['case_stdout_base64'], validate=True)
        if engine_bytes:
            issues.append('Unexpected private native engine log: ' + identifier)
        if native_stdout(raw):
            issues.append('Unexpected native engine frame in native stdout: ' + identifier)
        for row in [json.loads(line) for line in case_bytes.splitlines()]:
            body = base64.b64decode(row['stdout_base64'], validate=True)
            if row['pid'] != record['pid'] or row['nonce'] != identifier:
                raise RuntimeError('Native testcase output belongs to another invocation.')
            if native_stdout(body):
                issues.append('Unexpected native engine frame in native testcase output: ' + identifier)
        for field in ('engine_identity', 'case_stdout_identity'):
            before, after = starts[identifier][field], record[field]
            if before['dev'] != after['dev'] or before['ino'] != after['ino'] or after['nlink'] != 1:
                issues.append('Native engine/body capture changed identity: ' + identifier)
        if identifier not in expected_exits and (record['exit'] != 0 or record['stderr_base64'] != ''):
            issues.append('Unexpected native exit/stderr: ' + identifier)
    if result.stderr_bytes:
        issues.append('Unexpected main stderr.')
    if hashlib.sha256(prepared_file.read_bytes()).hexdigest() != prepared['target_sha256']:
        issues.append('Generated PHPUnit tool changed during execution.')
    receipt = dict(prepared_tool=prepared, tool_generation=str(run / 'tool-generation.json'), command=command, exit=result.returncode, checked_exit=70 if issues else result.returncode, counts=counts,
                   issues=issues, synthetic_control_bindings=bindings, ownership=result.ownership, archive_sha256=digest, startups=len(startups),
                   native_attempts=len(starts), native_cleanup=len(cleanup), junit=str(report),
                   main_engine_bytes=engine.stat().st_size, main_engine_sha256=hashlib.sha256(engine.read_bytes()).hexdigest(),
                   case_output_rows=len(body_rows), case_output_sha256=hashlib.sha256(body_output.read_bytes()).hexdigest(),
                   journal_sha256=hashlib.sha256(journal.read_bytes()).hexdigest(),
                   stdout_sha256=hashlib.sha256(result.stdout_bytes).hexdigest(), stderr_sha256=hashlib.sha256(result.stderr_bytes).hexdigest())
    (run / 'receipt.json').write_text(json.dumps(receipt, indent=2) + '\n')
    print('\nLOW_WIDONT_RECEIPT=' + str(run / 'receipt.json'), file=sys.stderr)
    return receipt['checked_exit']


if __name__ == '__main__':
    raise SystemExit(main())
