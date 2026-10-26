"""Bind only the configured synthetic harness faults to their owned native receipts."""
import base64
import hashlib
import json
from pathlib import Path
import re


def digest(value):
    return hashlib.sha256(value).hexdigest()


def synthetic_controls(records, document, root, leader, prepared_tool):
    """Return precise authored fault bindings; every other event remains unexpected."""
    passed = {case.get('class') + '::' + case.get('name') for case in document.iter('testcase')
              if not any(child.tag in ('error', 'failure', 'warning', 'skipped') for child in case)}
    diagnostics = [(index, record) for index, record in enumerate(records) if record['kind'] == 'diagnostic']
    starts = [record for record in records if record['kind'] == 'native-start']
    spawns = {record['id']: record for record in records if record['kind'] == 'native-spawn'}
    startups = {record['pid']: record for record in records if record['kind'] == 'startup'}
    ends = {record['id']: record for record in records if record['kind'] == 'native-end'}
    allowed_events, allowed_exits, bindings = set(), set(), []

    def event(index, record, case, pid, severity, message, path, content, statement):
        if (record.get('case') != case or record['pid'] != pid or record['severity'] != severity
                or base64.b64decode(record['message_base64']) != message.encode()
                or base64.b64decode(record['file_base64']).decode() != str(path)
                or record.get('file_sha256') != digest(content) or not record['source_callsite']):
            return False
        lines = content.splitlines(keepends=True)
        return (0 < record['line'] <= len(lines) and lines[record['line'] - 1].strip() == statement.encode()
                and base64.b64decode(record['statement_base64']) == lines[record['line'] - 1])

    def owned_attempt(case):
        """Join the original fault child after exactly one healthy native INI read."""
        attempts = [record for record in starts if record.get('case') == case]
        if len(attempts) == 1:
            return attempts[0]
        if len(attempts) != 2:
            raise RuntimeError('Authored native case has an unexpected attempt count: ' + case)
        discovery, attempt = attempts
        command = discovery['command']
        code = "require_once '" + str(root / 'tests/support/Diagnostics.php') + "';echo base64_encode(ini_get(\"auto_prepend_file\"));"
        if '-r' not in command or command[command.index('-r') + 1] != code:
            raise RuntimeError('Authored native configuration program differs: ' + case)
        boundary = command.index('-r')
        options = command[1:boundary]
        index = 0
        while index < len(options):
            option = options[index]
            if option == '-n':
                index += 1
                continue
            if option not in ('-c', '-d', '-z', '--php-ini', '--define', '--zend-extension') or index + 1 == len(options):
                raise RuntimeError('Authored native configuration options differ: ' + case)
            index += 2
        strict_tail = command[boundary + 2:]
        body_option = next((strict_tail[index + 1] for index in range(0, len(strict_tail) - 1, 2)
                            if strict_tail[index] == '-d' and strict_tail[index + 1].startswith('low_widont.case_stdout="')), None)
        if body_option is None or not body_option.endswith('"'):
            raise RuntimeError('Authored body-output startup identity is missing: ' + case)
        body_encoded = body_option[len('low_widont.case_stdout="'):-1]
        body = json.loads(base64.b64decode(body_encoded, validate=True))
        identity = discovery['case_stdout_identity']
        if body != dict(path=discovery['temporary'] + '/case-stdout', device=identity['dev'],
                        inode=identity['ino'], nonce=discovery['id']):
            raise RuntimeError('Authored body-output startup identity differs: ' + case)
        if strict_tail != ['-d', 'error_reporting=-1', '-d', 'display_errors=stderr',
                           '-d', 'display_startup_errors=1', '-d', 'log_errors=0',
                           '-d', 'error_log=' + discovery['temporary'] + '/engine', '-d', body_option,
                           '-d', 'low_widont.phpunit_tool="' + base64.b64encode(json.dumps(prepared_tool).encode()).decode('ascii') + '"',
                           '-d', 'html_errors=0']:
            raise RuntimeError('Authored configuration startup settings differ: ' + case)
        pid = spawns[discovery['id']]['pid']
        startup = startups[pid]
        end = ends[discovery['id']]
        encoded = base64.b64decode(end['stdout_base64'], validate=True)
        try:
            value = base64.b64decode(encoded, validate=True)
        except ValueError as error:
            raise RuntimeError('Authored configuration output differs: ' + case) from error
        if (startup['script'] != '' or discovery.get('compile_only') or end['exit'] != 0
                or end['stderr_base64'] != '' or base64.b64encode(value) != encoded
                or discovery.get('cwd') != attempt.get('cwd')
                or any(record['pid'] == pid for _, record in diagnostics)):
            raise RuntimeError('Authored native configuration receipt differs: ' + case)
        return attempt

    main = [
        ('PackagingTest::testDiagnosticHandlerSeesSuppressedDeprecations', 16384, 'suppressed diagnostic fixture',
         "@trigger_error('suppressed diagnostic fixture', E_USER_DEPRECATED);", 'PackagingTest.php'),
        ('PackagingTest::testDiagnosticHandlerSeesSuppressedWarnings', 512, 'suppressed warning fixture',
         "@trigger_error('suppressed warning fixture', E_USER_WARNING);", 'PackagingTest.php'),
        ('WarningContinuationTest::testSupportDiagnosticsStillThrow with data set #0', 512, 'authored support diagnostic',
         "@trigger_error('authored support diagnostic', $severity);", 'WarningContinuationTest.php'),
        ('WarningContinuationTest::testSupportDiagnosticsStillThrow with data set #1', 16384, 'authored support diagnostic',
         "@trigger_error('authored support diagnostic', $severity);", 'WarningContinuationTest.php'),
    ]
    for suffix, severity, message, statement, filename in main:
        case = 'LowWidontTests\\' + suffix
        if case not in passed:
            continue
        path = root / 'tests/tests/Unit' / filename
        hits = [index for index, record in diagnostics if event(index, record, case, leader, severity, message, path, path.read_bytes(), statement)]
        if len(hits) != 1:
            raise RuntimeError('Authored main fault receipt differs: ' + case)
        allowed_events.update(hits)
        bindings.append(dict(case=case, pid=leader, kind='main', events=hits))

    profiles = {}
    entries = [('tests/bootstrap.php', 'tests/support/Environment.php'),
               ('tests/tools/autoload-probe.php', 'tests/support/Evidence.php'),
               ('tests/tools/archive-probe.php', 'tests/support/Evidence.php'),
               ('tests/tools/host-bootstrap-probe.php', 'tests/support/Evidence.php'),
               ('tests/tools/raw-coverage.php', 'tests/tools/verify-tool.php'),
               ('tests/tools/request-context.php', 'tests/tools/verify-tool.php')]
    for index, (script, dependency) in enumerate(entries):
        for offset, severity in enumerate((512, 16384)):
            case = 'LowWidontTests\\BootstrapDiagnosticTest::testFirstRequiredSupportIsStrict with data set #' + str(index * 2 + offset)
            profiles[case] = dict(script=script, dependency=dependency, severity=severity,
                                  message='entry bootstrap sentinel', owner='low-widont-entry-bootstrap-')
    scripts = ('cache-warning-observation.php', 'extraction-warning-observation.php')
    for script_index, script in enumerate(scripts):
        for support_index, support in enumerate(('Environment.php', 'Evidence.php', 'SourceWarningObservation.php')):
            for offset, severity in enumerate((512, 16384)):
                index = script_index * 6 + support_index * 2 + offset
                case = 'LowWidontTests\\WarningContinuationTest::testBootstrapDiagnosticsStillThrow with data set #' + str(index)
                profiles[case] = dict(script='tests/tools/' + script, dependency='tests/support/' + support,
                                      severity=severity, message='bootstrap support sentinel', owner='low-widont-bootstrap-control-')
        case = 'LowWidontTests\\WarningContinuationTest::testArchivePinsAreCheckedBeforeContinuation with data set #' + str(script_index)
        profiles[case] = dict(script='tests/tools/' + script, dependency=None,
                              message='Observation archive differs from the pinned source inventory.', owner='low-widont-bootstrap-control-')
    for case, profile in profiles.items():
        if case not in passed:
            continue
        attempt = owned_attempt(case)
        pid = spawns[attempt['id']]['pid']
        startup = startups[pid]
        script = Path(startup['script'])
        owned = script
        for part in Path(profile['script']).parts:
            owned = owned.parent
        if (not re.fullmatch(re.escape(profile['owner']) + '[0-9a-f]{16}', owned.name)
                or script != owned / profile['script']
                or str(script) not in attempt['command']
                or startup.get('script_sha256') != digest((root / profile['script']).read_bytes())):
            raise RuntimeError('Authored native script ownership/hash differs: ' + case)
        native_events = [(index, record) for index, record in diagnostics if record['pid'] == pid]
        dependency = profile['dependency']
        if dependency:
            marker = '<?php' if dependency.startswith('tests/tools/') else ('namespace LowWidontTests {' if dependency.endswith('Environment.php') else 'namespace LowWidontTests;')
            content = (root / dependency).read_bytes()
            statement = "@trigger_error('" + profile['message'] + "', " + str(profile['severity']) + ');'
            assert content.count(marker.encode()) == 1
            generated = content.replace(marker.encode(), (marker + '\n    ' + statement).encode())
            if len(native_events) != 1 or not event(*native_events[0], case, pid, profile['severity'], profile['message'], owned / dependency, generated, statement):
                raise RuntimeError('Authored generated dependency receipt differs: ' + case)
            allowed_events.add(native_events[0][0])
            exception = 'ErrorException'
            if startup.get('script_archive_sha256') != digest((root / 'low_widont/low_widont.phar').read_bytes()):
                raise RuntimeError('Authored native archive differs: ' + case)
        else:
            expected = digest((root / 'low_widont/low_widont.phar').read_bytes() + b'owned corrupt input')
            if native_events or startup.get('script_archive_sha256') != expected:
                raise RuntimeError('Owned corrupt-input control receipt differs: ' + case)
            exception = 'RuntimeException'
        end = ends[attempt['id']]
        stderr = base64.b64decode(end['stderr_base64'])
        fatal = ('Fatal error: Uncaught ' + exception + ': ' + profile['message'] + ' in ').encode()
        if (end['exit'] != 255 or end['stdout_base64'] != '' or not stderr.lstrip(b'\n').startswith(fatal)
                or len(re.findall(rb'(?m)^(?:PHP )?(?:Warning|Notice|Deprecated|Parse error|Fatal error):', stderr)) != 1):
            raise RuntimeError('Authored negative native stream/exit differs: ' + case)
        allowed_exits.add(attempt['id'])
        bindings.append(dict(case=case, pid=pid, native_id=attempt['id'], kind='native' if dependency else 'pin',
                             events=[index for index, record in native_events], script_sha256=startup['script_sha256']))

    for index in range(1, 9):
        case = 'LowWidontTests\\EvidenceDestinationTest::testDestinationBoundary with data set #' + str(index)
        if case not in passed:
            continue
        attempt = owned_attempt(case)
        pid = spawns[attempt['id']]['pid']
        command = attempt['command']
        collector = index >= 5
        if not collector:
            code = "require '" + str(root / 'tests/support/Diagnostics.php') + "';require $argv[1];LowWidontTests\\Evidence::write($argv[2],[\"probe\"=>true]);"
            if '-r' not in command or command[command.index('-r') + 1] != code:
                raise RuntimeError('Publisher control code differs: ' + case)
            owned = Path(command[-2]).parents[2]
            if command[-2] != str(owned / 'tests/support/Evidence.php'):
                raise RuntimeError('Publisher control dependency differs: ' + case)
        else:
            script = Path(startups[pid]['script'])
            owned = script.parents[2]
            if script != owned / 'tests/tools/raw-coverage.php' or startups[pid]['script_sha256'] != digest((root / 'tests/tools/raw-coverage.php').read_bytes()):
                raise RuntimeError('Collector control source differs: ' + case)
        if not re.fullmatch('low-widont-output-boundary-[0-9a-f]{16}', owned.name):
            raise RuntimeError('Output-boundary ownership differs: ' + case)
        if any(record['pid'] == pid for index, record in diagnostics):
            raise RuntimeError('Output-boundary control emitted a diagnostic: ' + case)
        end = ends[attempt['id']]
        stderr = base64.b64decode(end['stderr_base64'])
        message = ('Refusing to publish evidence within shipped source:' if index in (4, 8) else 'Refusing to replace evidence:')
        prefix = ('Fatal error: Uncaught RuntimeException: ' + message).encode()
        if (end['exit'] != 255 or end['stdout_base64'] != '' or not stderr.lstrip(b'\n').startswith(prefix)
                or len(re.findall(rb'(?m)^(?:PHP )?(?:Warning|Notice|Deprecated|Parse error|Fatal error):', stderr)) != 1):
            raise RuntimeError('Output-boundary negative stream/exit differs: ' + case)
        allowed_exits.add(attempt['id'])
        bindings.append(dict(case=case, pid=pid, native_id=attempt['id'], kind='output-boundary', events=[]))
    return allowed_events, allowed_exits, bindings
