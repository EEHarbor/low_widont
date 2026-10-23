"""Reject malformed native instrumentation without losing the offending record name."""
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
        for earlier_valid_record in (False, True):
            with self.subTest(earlier_valid_record=earlier_valid_record):
                with tempfile.TemporaryDirectory(prefix='low-widont-invalid-flags-') as temporary:
                    root = Path(temporary)
                    record = dict(source={}, php='8.2.30', xdebug='3.5.3', flags=7)
                    if earlier_valid_record:
                        (root / 'a-valid.json').write_text(json.dumps(record))
                    record['flags'] = 0
                    (root / 'b-invalid.json').write_text(json.dumps(record))
                    result = subprocess.run([sys.executable, '-B', str(tool), str(root)],
                                            capture_output=True, text=True, check=False)
                    self.assertEqual(1, result.returncode)
                    self.assertEqual('', result.stdout)
                    self.assertEqual('Missing or incompatible native instrumentation flags: b-invalid.json\n',
                                     result.stderr)


if __name__ == '__main__':
    unittest.main()
