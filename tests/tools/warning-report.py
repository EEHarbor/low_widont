#!/usr/bin/env python3
"""Validate separate unhealthy source observations without merging strict counters."""
import hashlib
import json
from pathlib import Path
import sys
import xml.etree.ElementTree as ET

root = Path(__file__).resolve().parents[2]
directory = Path(sys.argv[1]).resolve()
inventory_path = root / 'tests/SOURCE_INVENTORY.json'
inventory = json.loads(inventory_path.read_text())
inventory_hash = hashlib.sha256(inventory_path.read_bytes()).hexdigest()
suite = ET.parse(directory / 'junit.xml').find('testsuite')
if suite is None or any(int(suite.get(key, '-1')) != value for key, value in
                        [('tests', 26), ('failures', 10), ('errors', 0), ('warnings', 0), ('skipped', 0)]):
    raise SystemExit('Expected the complete red observation suite and all strict support/pin controls')
if (directory / 'exit-status.txt').read_text().strip() != '1':
    raise SystemExit('The ordinary observation suite must retain its red exit status')
expected = {'cache-' + scope + '-' + operation for scope in ('base', 'module')
            for operation in ('get', 'set', 'delete', 'clear')} | {'extraction-cold', 'extraction-cleanup'}
paths = sorted(directory.glob('*/observation.json'))
records = []
contexts = []
for path in paths:
    raw = json.loads(path.read_text())
    context = raw.get('context')
    if not isinstance(context, str) or context not in expected:
        raise SystemExit('Unknown source observation context: ' + str(path))
    contexts.append(context)
    if raw.get('mode') != 'observational-warning-continuation' or raw.get('diagnostic_free') is not False or raw.get('strict_suite_graphs_mergeable') is not False:
        raise SystemExit('Observation mode must explicitly retain unhealthy, unmergeable source flow: ' + str(path))
    if type(raw.get('flags')) is not int or raw['flags'] != 7 or raw.get('native_coverage_collected') is not True:
        raise SystemExit('Observation instrumentation flags must be integer 7: ' + str(path))
    if raw.get('archive_sha256') != inventory['archive_sha256'] or raw.get('inventory_sha256') != inventory_hash:
        raise SystemExit('Observation source provenance differs: ' + str(path))
    if raw.get('phpunit_sha256') != '6b5235f282c6ccab3674ac27c5b1ddbd1da838e74bf07002ddcfd030d31298d7':
        raise SystemExit('Observation pinned tool differs: ' + str(path))
    if not raw.get('php') or not raw.get('binary') or not raw.get('xdebug') or not raw.get('source'):
        raise SystemExit('Observation runtime or native source is absent: ' + str(path))
    if (path.parent / 'exit-status.txt').read_text().strip() != '1':
        raise SystemExit('An observed source diagnostic must retain a red child exit: ' + str(path))
    stderr = (path.parent / 'stderr.log').read_text()
    emitted = [json.loads(line)['source_diagnostic'] for line in stderr.splitlines() if line.startswith('{"source_diagnostic":')]
    diagnostics = raw.get('diagnostics')
    if not diagnostics or emitted != diagnostics:
        raise SystemExit('Ordered immediate source diagnostics differ from retained stderr: ' + str(path))
    for diagnostic in diagnostics:
        if diagnostic.get('severity') != 2 or diagnostic.get('source_sha256') != raw.get('source_sha256') or type(diagnostic.get('masked_by_source')) is not bool:
            raise SystemExit('Source warning identity is incomplete: ' + str(path))
        source = diagnostic.get('file', '')
        expected_hash = (inventory['archive_sha256'] if context.startswith('extraction-')
                         else inventory['files']['low_widont/low_widont.phar!/FluxCapacitor/Conduit/PersistentCache.php']['sha256'])
        if diagnostic['source_sha256'] != expected_hash or not source.endswith(
                '/low_widont/low_widont.phar' if context.startswith('extraction-') else '/FluxCapacitor/Conduit/PersistentCache.php'):
            raise SystemExit('Diagnostic did not originate in the verified original source: ' + str(path))
    sources = []
    for physical, data in raw['source'].items():
        unit = data.get('source_unit')
        if unit is not None:
            marker = '/pharextract/low_widont/'
            if not isinstance(unit, str) or marker not in physical or unit != 'low_widont/low_widont.phar!/' + physical.split(marker, 1)[1]:
                raise SystemExit('Extracted source mapping differs: ' + physical)
            name = physical.split(marker, 1)[1]
            if data.get('source_bytes_verified_at') != 'after-extraction-before-cleanup' or raw.get('extracted', {}).get(name) != data.get('sha256'):
                raise SystemExit('Extracted compiled source lacks pre-cleanup byte verification: ' + physical)
        else:
            marker = '/low_widont/low_widont.phar/'
            if physical.startswith('phar://') and marker in physical:
                unit = 'low_widont/low_widont.phar!/' + physical.split(marker, 1)[1]
            elif physical.endswith('/low_widont/low_widont.phar'):
                unit = 'low_widont/low_widont.phar!/{stub}'
            elif '/low_widont/' in physical:
                unit = 'low_widont/' + physical.rsplit('/low_widont/', 1)[1]
        expected_hash = inventory['archive_sha256'] if unit and unit.endswith('!/{stub}') else inventory['files'].get(unit, {}).get('sha256')
        if expected_hash is None or data.get('sha256') != expected_hash:
            raise SystemExit('Observed compiled source bytes differ: ' + physical)
        if not data.get('functions'):
            raise SystemExit('Observed native function graphs are missing: ' + physical)
        graphs = []
        for name, graph in data.get('functions', {}).items():
            branches = graph.get('branches', {})
            if not branches:
                raise SystemExit('Observed native graph is missing branches: ' + name)
            items = list(branches.items()) if isinstance(branches, dict) else list(enumerate(branches))
            shape = [dict(id=str(index), **{key: value for key, value in block.items() if key not in ('hit', 'out_hit')}) for index, block in items]
            exits = [hit for _, block in items for hit in block.get('out_hit', [])]
            graphs.append(dict(name=name, compiled_graph_sha256=hashlib.sha256(json.dumps(shape, sort_keys=True).encode()).hexdigest(),
                               basic_blocks_hit=sum(block['hit'] > 0 for _, block in items), basic_blocks_total=len(items),
                               raw_exits_hit=sum(hit > 0 for hit in exits), raw_exits_total=len(exits)))
        sources.append(dict(unit=unit, physical_source=physical, sha256=data['sha256'],
                            positive_lines=[int(line) for line, hit in data.get('lines', {}).items() if hit > 0], graphs=graphs))
    records.append(dict(context=context, report=str(path.relative_to(directory)), sha256=hashlib.sha256(path.read_bytes()).hexdigest(),
                        stderr_sha256=hashlib.sha256(stderr.encode()).hexdigest(), php=raw['php'], binary=raw['binary'], xdebug=raw['xdebug'],
                        diagnostic_count=len(diagnostics), diagnostic_free=False, strict_suite_graphs_mergeable=False, sources=sources))
if len(contexts) != len(expected) or set(contexts) != expected:
    raise SystemExit('Observation records do not match every configured unhealthy context')
expected_controls = {'bootstrap-' + script + '-' + support + '-' + str(severity)
                     for script in ('cache-warning-observation.php', 'extraction-warning-observation.php')
                     for support in ('Environment.php', 'Evidence.php', 'SourceWarningObservation.php')
                     for severity in (512, 16384)} | {'pin-' + script for script in
                                                     ('cache-warning-observation.php', 'extraction-warning-observation.php')}
controls = []
for path in sorted((directory / 'controls').glob('*.json')):
    raw = json.loads(path.read_text())
    context = raw.get('control')
    if not isinstance(context, str) or context not in expected_controls:
        raise SystemExit('Unknown strict bootstrap/pin control: ' + str(path))
    output = path.with_suffix('.log').read_bytes()
    if type(raw.get('exit_status')) is not int or raw['exit_status'] == 0 or raw.get('observation_published') is not False:
        raise SystemExit('Strict bootstrap/pin control executed source or accepted a diagnostic: ' + context)
    if raw.get('output_sha256') != hashlib.sha256(output).hexdigest() or b'source_diagnostic' in output:
        raise SystemExit('Strict bootstrap/pin output is incomplete or continued source: ' + context)
    if raw.get('source_archive_sha256') != inventory['archive_sha256']:
        raise SystemExit('Strict bootstrap/pin production archive differs: ' + context)
    if context.startswith('bootstrap-'):
        if raw.get('mirror_archive_sha256') != inventory['archive_sha256'] or not raw.get('generated_support_sha256'):
            raise SystemExit('Bootstrap fault did not preserve production bytes: ' + context)
        if b'ErrorException: bootstrap support sentinel' not in output:
            raise SystemExit('Suppressed bootstrap diagnostic did not throw: ' + context)
    if context.startswith('pin-'):
        expected_corrupt = hashlib.sha256((root / 'low_widont/low_widont.phar').read_bytes() + b'owned corrupt input').hexdigest()
        if raw.get('mirror_archive_sha256') != expected_corrupt or b'Observation archive differs from the pinned source inventory.' not in output:
            raise SystemExit('Archive input was not rejected by its pre-execution pin: ' + context)
    controls.append(dict(control=context, report=str(path.relative_to(directory)), sha256=hashlib.sha256(path.read_bytes()).hexdigest(),
                         output_sha256=raw['output_sha256'], php=raw['php'], exit_status=raw['exit_status'], observation_published=False))
if len(controls) != len(expected_controls) or {item['control'] for item in controls} != expected_controls:
    raise SystemExit('Strict bootstrap/pin controls are incomplete')
print(json.dumps(dict(mode='observational-warning-continuation', diagnostic_free=False, strict_suite_graphs_mergeable=False,
                      inventory_sha256=inventory_hash, records=records, strict_bootstrap_controls=controls,
                      caveats=['Source warnings are emitted immediately and retain red ordinary regressions.',
                               'These unhealthy paths are separate from strict main/context coverage and never establish closure.',
                               'Extracted member bytes are verified against the immutable archive before original cleanup deletes files.',
                               'Basic blocks and raw outgoing exits retain individual source/runtime/compiled-graph provenance.',
                               'Positive closing-brace/PHAR terminal markers do not establish additional semantic paths.']), indent=2))
