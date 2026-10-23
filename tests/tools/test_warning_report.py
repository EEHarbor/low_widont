"""Validate unhealthy observation guards against retained real native records."""
import copy
import gzip
import json
from pathlib import Path
import subprocess
import sys
import tempfile
import unittest
import xml.etree.ElementTree as ET


class WarningReportTest(unittest.TestCase):
    """Reject metadata loss without inventing executable counters or healthy paths."""

    @classmethod
    def setUpClass(cls):
        cls.temporary = tempfile.TemporaryDirectory(prefix='low-widont-warning-report-')
        cls.directory = Path(cls.temporary.name)
        source = Path(__file__).resolve().parents[1] / 'evidence/warning-observations'
        for path in source.rglob('*'):
            if not path.is_file():
                continue
            target = cls.directory / path.relative_to(source)
            if path.suffix == '.gz':
                target = target.with_suffix('')
            target.parent.mkdir(exist_ok=True, parents=True)
            target.write_bytes(gzip.decompress(path.read_bytes()) if path.suffix == '.gz' else path.read_bytes())
        cls.tool = Path(__file__).with_name('warning-report.py')
        cls.path = sorted(cls.directory.glob('*/observation.json'))[0]
        cls.original = cls.path.read_bytes()
        cls.raw = json.loads(cls.original)

    @classmethod
    def tearDownClass(cls):
        cls.temporary.cleanup()

    def tearDown(self):
        self.path.write_bytes(self.original)

    def run_report(self):
        return subprocess.run([sys.executable, '-B', str(self.tool), str(self.directory)], capture_output=True, text=True)

    def assert_rejected(self, raw, message):
        self.path.write_text(json.dumps(raw))
        result = self.run_report()
        self.assertEqual(1, result.returncode)
        self.assertEqual('', result.stdout)
        self.assertIn(message, result.stderr)

    def test_valid_records_reproduce_the_retained_summary(self):
        result = self.run_report()
        self.assertEqual(0, result.returncode, result.stderr)
        self.assertEqual(json.loads((self.directory / 'summary.json').read_text()), json.loads(result.stdout))

    def test_flags_must_be_the_exact_integer_bitmask(self):
        for flags in (None, 0, 6, 7.0):
            with self.subTest(flags=flags):
                raw = dict(self.raw)
                raw.pop('flags')
                if flags is not None:
                    raw['flags'] = flags
                self.assert_rejected(raw, 'Observation instrumentation flags must be integer 7')

    def test_unhealthy_mode_and_separation_cannot_be_lost(self):
        for key in ('diagnostic_free', 'strict_suite_graphs_mergeable'):
            with self.subTest(key=key):
                self.assert_rejected(dict(self.raw, **{key: True}), 'unhealthy, unmergeable source flow')

    def test_every_diagnostic_and_red_status_must_be_retained(self):
        self.assert_rejected(dict(self.raw, diagnostics=[]), 'Ordered immediate source diagnostics differ')
        status = self.path.parent / 'exit-status.txt'
        original = status.read_bytes()
        try:
            status.write_text('0')
            self.assert_rejected(self.raw, 'must retain a red child exit')
        finally:
            status.write_bytes(original)

    def test_source_inventory_and_pinned_tool_must_match(self):
        for key, message in [('archive_sha256', 'Observation source provenance differs'),
                             ('inventory_sha256', 'Observation source provenance differs'),
                             ('phpunit_sha256', 'Observation pinned tool differs')]:
            with self.subTest(key=key):
                self.assert_rejected(dict(self.raw, **{key: '0' * 64}), message)
        raw = copy.deepcopy(self.raw)
        next(iter(raw['source'].values()))['sha256'] = '0' * 64
        self.assert_rejected(raw, 'Observed compiled source bytes differ')

    def test_missing_records_and_empty_suites_are_rejected(self):
        backup = self.path.with_suffix('.withdrawn')
        self.path.rename(backup)
        try:
            result = self.run_report()
            self.assertEqual(1, result.returncode)
            self.assertEqual('', result.stdout)
            self.assertIn('do not match every configured unhealthy context', result.stderr)
        finally:
            backup.rename(self.path)
        junit = self.directory / 'junit.xml'
        original = junit.read_bytes()
        try:
            tree = ET.fromstring(original)
            tree.find('testsuite').set('tests', '0')
            junit.write_bytes(ET.tostring(tree))
            self.assert_rejected(self.raw, 'Expected the complete red observation suite')
        finally:
            junit.write_bytes(original)

    def test_lost_native_branches_cannot_be_accepted_as_observation(self):
        raw = copy.deepcopy(self.raw)
        data = next(iter(raw['source'].values()))
        next(iter(data['functions'].values()))['branches'] = {}
        self.assert_rejected(raw, 'Observed native graph is missing branches')

    def test_strict_bootstrap_controls_cannot_be_omitted(self):
        path = sorted((self.directory / 'controls').glob('*.json'))[0]
        backup = path.with_suffix('.withdrawn')
        path.rename(backup)
        try:
            result = self.run_report()
            self.assertEqual(1, result.returncode)
            self.assertEqual('', result.stdout)
            self.assertIn('Strict bootstrap/pin controls are incomplete', result.stderr)
        finally:
            backup.rename(path)


if __name__ == '__main__':
    unittest.main()
