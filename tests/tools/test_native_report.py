"""Reject malformed native instrumentation without losing the offending record name."""
import gzip
import json
from pathlib import Path
import subprocess
import sys
import tempfile
import unittest


class NativeReportTest(unittest.TestCase):
    """Exercise the report CLI's instrumentation guard in actual sorted input order."""

    def test_invalid_flags_identify_the_first_or_later_record(self):
        tool = Path(__file__).with_name('native-report.py')
        for earlier_valid_record, flags in ((earlier, flags) for earlier in (False, True) for flags in (None, 0, 6, 7.0)):
            with self.subTest(earlier_valid_record=earlier_valid_record, flags=flags):
                with tempfile.TemporaryDirectory(prefix='low-widont-invalid-flags-') as temporary:
                    root = Path(temporary)
                    record = dict(source={}, php='8.2.30', xdebug='3.5.3', flags=7)
                    if earlier_valid_record:
                        (root / 'a-valid.json').write_text(json.dumps(record))
                    record.pop('flags')
                    if flags is not None:
                        record['flags'] = flags
                    (root / 'b-invalid.json').write_text(json.dumps(record))
                    result = subprocess.run([sys.executable, '-B', str(tool), str(root)],
                                            capture_output=True, text=True, check=False)
                    self.assertEqual(1, result.returncode)
                    self.assertEqual('', result.stdout)
                    self.assertEqual('Missing or incompatible native instrumentation flags: b-invalid.json\n',
                                     result.stderr)

    def test_conditional_constructor_without_dispatch_remains_unknown(self):
        root = Path(__file__).resolve().parents[1]
        raw = json.loads(gzip.decompress((root / 'evidence/native/main.json.gz').read_bytes()))
        raw.pop('dispatch')
        with tempfile.TemporaryDirectory(prefix='low-widont-missing-dispatch-') as temporary:
            (Path(temporary) / 'record.json').write_text(json.dumps(raw))
            result = subprocess.run([sys.executable, '-B', str(Path(__file__).with_name('native-report.py')), temporary],
                                    capture_output=True, text=True, check=True)
        report = json.loads(result.stdout)
        constructors = [method for method in report['methods']
                        if method['file'].endswith('/Bridge/EE.php') and method['name'] == '__construct']
        self.assertEqual(6, len(constructors))
        self.assertTrue(all(not method['graph_known'] for method in constructors))
        self.assertTrue(any(graph['reason'] == 'Conditional constructor lacks runtime dispatch provenance'
                            for graph in report['ambiguous_graphs']))


if __name__ == '__main__':
    unittest.main()
