#!/usr/bin/env python3
"""Record portable line, block and exit evidence from one fresh coverage run."""
import json
import subprocess
import sys
import xml.etree.ElementTree as ET
from pathlib import Path

root = Path(__file__).resolve().parents[2]
run = Path(sys.argv[1]).resolve()
helper = Path('/Users/tomjaeger/.codex/skills/ee-addon-phpunit-coverage-writer/scripts/addon_method_coverage_report.py')


def normalize(path):
    """Map verified archive measurement copies back to their shipped archive entry."""
    path = str(path)
    prefix = str(run / 'source') + '/'
    if path.startswith(prefix):
        return 'low_widont/low_widont.phar!/' + path[len(prefix):]
    return path.removeprefix(str(root) + '/')


inventory = json.loads((root / 'tests/SOURCE_INVENTORY.json').read_text())
manifest = json.loads((run / 'inventory.json').read_text())
if inventory['archive_sha256'] != manifest['archive_sha256']:
    raise RuntimeError('Source inventory and coverage archive provenance differ.')
line_files = {}
for source in ET.parse(run / 'clover.xml').findall('.//file'):
    lines = source.findall('line[@type="stmt"]')
    covered = [int(line.attrib['num']) for line in lines if int(line.attrib['count']) > 0]
    missed = [int(line.attrib['num']) for line in lines if int(line.attrib['count']) == 0]
    line_files[normalize(source.attrib['name'])] = {
        'covered_executable_lines': covered,
        'uncovered_executable_lines': missed,
        'line_percent': round(100 * len(covered) / len(lines), 2) if lines else None,
    }
methods = []
for kind, scope in [('loose', ['--source-root', 'low_widont']), ('archive', ['--target', str(run / 'source')])]:
    command = [str(helper), '--repo-root', str(root), *scope, '--include-magic', '--coverage-clover', str(run / 'clover.xml'), '--reuse-clover', '--json']
    result = subprocess.run(command, check=True, capture_output=True, text=True)
    (run / ('methods-' + kind + '.json')).write_text(result.stdout)
    for method in json.loads(result.stdout)['methods']:
        method['file'] = normalize(root / method['file'])
        method['skill_status_not_branch_proof'] = method.pop('status')
        methods.append(method)
branches = json.loads((run / 'branches.json').read_text())
for branch in branches:
    branch['file'] = normalize(branch['file'])
checkpoint = {
    'baseline': inventory['baseline'], 'runtime': manifest['php'], 'xdebug': manifest['xdebug'], 'ee': manifest['ee'],
    'archive_sha256': inventory['archive_sha256'], 'report_directory': str(run),
    'completion': 'incomplete; source regressions and coverage gaps remain',
    'line_files': line_files, 'skill_method_line_report': methods, 'actual_blocks_and_exits': branches,
    'caveats': [
        'Skill Clover branch_percent=null is unknown, including rows labeled at_target by the helper.',
        'Known lossy outgoing counters cannot prove an exit unreachable. Observed path edges provide positive proof only for the same source/runtime/compiled graph.',
        'Zero exits have unknown exit coverage. Synthetic and exceptional exits remain unwaived.',
        'Archive extraction stub and native subprocess coverage are not merged into the measured graph.',
        'Full path coverage is not required.',
    ],
}
(root / 'tests/COVERAGE_CHECKPOINT.json').write_text(json.dumps(checkpoint, indent=2) + '\n')
evidence = json.loads((run / 'instrumentation-validation.json').read_text())
(root / 'tests/INSTRUMENTATION_EVIDENCE.json').write_text(json.dumps(evidence, indent=2) + '\n')
print('Checkpoint files:', len(line_files), 'skill method rows:', len(methods), 'actual compiled method graphs:', len(branches))
