#!/usr/bin/env python3
"""Validate raw source provenance and report each compiled graph independently."""
import hashlib
import json
from pathlib import Path
import re
import sys

root = Path(__file__).resolve().parents[2]
run = Path(sys.argv[1]).resolve()
inventory_path = root / 'tests/SOURCE_INVENTORY.json'
inventory = json.loads(inventory_path.read_text())
records = []
observations = {}
ambiguous_graphs = []


def source_unit(path):
    """Identify original or verified private source by its shipped unit suffix."""
    if '.phar/' in path:
        return 'low_widont/low_widont.phar!/' + path.split('.phar/', 1)[1]
    if path.endswith('/low_widont/low_widont.phar'):
        return 'low_widont/low_widont.phar!/{stub}'
    marker = '/low_widont/'
    return 'low_widont/' + path.rsplit(marker, 1)[1]


for report in sorted(run.rglob('*.json')):
    raw = json.loads(report.read_text())
    if 'source' not in raw:
        continue
    report_id = str(report.relative_to(run))
    if type(raw.get('flags')) is not int or raw['flags'] != 7:
        raise SystemExit('Missing or incompatible native instrumentation flags: ' + report_id)
    if raw.get('archive_sha256', inventory['archive_sha256']) != inventory['archive_sha256']:
        raise SystemExit('Archive provenance mismatch: ' + report_id)
    records.append(dict(report=report_id, sha256=hashlib.sha256(report.read_bytes()).hexdigest(),
                        php=raw['php'], xdebug=raw['xdebug'], ee=raw.get('ee'), mode=raw.get('mode'),
                        source_units=len(raw['source'])))
    for physical, coverage in raw['source'].items():
        unit = source_unit(physical)
        if unit not in inventory['files']:
            raise SystemExit('Uninventoried coverage source: ' + physical)
        expected = inventory['archive_sha256'] if unit.endswith('!/{stub}') else inventory['files'][unit]['sha256']
        if coverage['sha256'] != expected:
            raise SystemExit('Source bytes differ: ' + physical)
        for function, graph in coverage.get('functions', {}).items():
            branches = graph.get('branches', {})
            branches = list(branches.values()) if isinstance(branches, dict) else branches
            if not branches:
                continue
            shape = [{key: value for key, value in block.items() if key not in ('hit', 'out_hit')} for block in branches]
            graph_hash = hashlib.sha256(json.dumps(shape, sort_keys=True).encode()).hexdigest()
            start = min(block['line_start'] for block in branches)
            short_name = function.split('->')[-1].split('::')[-1]
            if function.startswith('{closure:'):
                match = re.search(r':(\d+)-\d+}', function)
                short_name = '{closure@' + match.group(1) + '}' if match else function
            declarations = inventory['files'][unit]['functions_including_magic_and_closures']
            if unit.endswith('/Bridge/EE.php') and short_name == '__construct':
                if not raw.get('dispatch'):
                    ambiguous_graphs.append(dict(report=report_id, function=function, raw_start=start,
                                                 reason='Conditional constructor lacks runtime dispatch provenance'))
                    continue
                actual_start = raw['dispatch']['constructor_start_line']
                actual = [method for method in declarations if method['line'] == actual_start]
                if len(actual) != 1 or not actual[0]['line'] <= start <= actual[0]['end_line']:
                    ambiguous_graphs.append(dict(report=report_id, function=function, raw_start=start,
                                                 reflection_dispatch=raw['dispatch'], reason='Xdebug aliased conditional class constructor location'))
                    continue
            matches = [method for method in declarations if method['name'] == short_name
                       and method['line'] <= start <= method['end_line']]
            if function == '{main}':
                identity = '{procedural}'
                line_numbers = {int(line) for line in coverage['lines'] if not any(
                    method['line'] <= int(line) <= method['end_line'] for method in declarations)}
            else:
                if len(matches) != 1:
                    raise SystemExit('Ambiguous function provenance: ' + unit + ' ' + function)
                method = matches[0]
                identity = method['name'] + '@' + str(method['line'])
                line_numbers = set(range(method['line'], method['end_line'] + 1))
            key = (unit, identity, raw['php'], raw['xdebug'], graph_hash)
            lines = {int(line): hits for line, hits in coverage['lines'].items()
                     if int(line) in line_numbers and hits != -2}
            exits = [(block['op_start'], target, block.get('out_hit', [])[offset])
                     for block in branches for offset, target in enumerate(block.get('out', []))]
            observation = dict(report=report_id, ee=raw.get('ee'), mode=raw.get('mode'),
                               lines_hit=sum(hits > 0 for hits in lines.values()), lines_total=len(lines),
                               uncovered_lines=[line for line, hits in lines.items() if hits < 1],
                               basic_blocks_hit=sum(block['hit'] > 0 for block in branches), basic_blocks_total=len(branches),
                               unobserved_basic_blocks=[dict(op_start=block['op_start'], line_start=block['line_start'], line_end=block['line_end']) for block in branches if block['hit'] < 1],
                               raw_exits_hit=sum(hits > 0 for _, _, hits in exits), raw_exits_total=len(exits),
                               unobserved_exits=[dict(source=source, target=target) for source, target, hits in exits if hits < 1])
            observations.setdefault(key, []).append(observation)

methods = []
for unit, info in inventory['files'].items():
    declarations = info['functions_including_magic_and_closures'] + [dict(name='{procedural}', line=1, abstract_declaration=False)]
    for declaration in declarations:
        identity = declaration['name'] if declaration['name'] == '{procedural}' else declaration['name'] + '@' + str(declaration['line'])
        graphs = [dict(php=key[2], xdebug=key[3], compiled_graph_sha256=key[4], observations=value)
                  for key, value in observations.items() if key[:2] == (unit, identity)]
        methods.append(dict(file=unit, name=declaration['name'], line=declaration['line'],
                            abstract_declaration=declaration['abstract_declaration'], graph_known=bool(graphs), graphs=graphs))
result = dict(inventory_sha256=hashlib.sha256(inventory_path.read_bytes()).hexdigest(),
              archive_sha256=inventory['archive_sha256'], reports=records, ambiguous_graphs=ambiguous_graphs, methods=methods,
              caveats=['Every observation retains its own runtime, source unit and compiled graph provenance.',
                       'Counters and incompatible graphs are never merged; basic blocks are not branch outcomes.',
                       'Zero raw exits include synthetic terminal/exception exits and do not establish impossibility.',
                       'Native line markers can include unreachable function epilogues; consult Clover and source.',
                       'Missing graphs remain unknown; abstract declarations have no executable body.',
                       'No full path-coverage target is imposed.'])
print(json.dumps(result, indent=2))
