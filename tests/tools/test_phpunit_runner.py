"""Exercise requested JUnit publication through the actual checked CLI wrapper."""
import os
from pathlib import Path
import shutil
import subprocess
import tempfile
import unittest
import xml.etree.ElementTree as ET

ROOT = Path(__file__).resolve().parents[2]


class JunitDestinationTest(unittest.TestCase):
    def setUp(self):
        """Copy only the required runner/bootstrap inputs into an owned export."""
        self.temporary = tempfile.TemporaryDirectory(prefix='low-widont-junit-')
        self.base = Path(self.temporary.name).resolve()
        self.root = self.base / 'export'
        self.root.mkdir()
        shutil.copytree(ROOT / 'low_widont', self.root / 'low_widont')
        for relative in ('tests/phpunit_runner.py', 'tests/process_support.py',
                         'tests/path_support.py', 'tests/authored_controls.py',
                         'tests/bootstrap.php', 'tests/tests/Unit/TestCase.php',
                         'tests/.phpunit/run-phpunit.sh'):
            target = self.root / relative
            target.parent.mkdir(parents=True, exist_ok=True)
            shutil.copy2(ROOT / relative, target)
        shutil.copytree(ROOT / 'tests/support', self.root / 'tests/support')
        (self.root / 'tests/.phpunit/phpunit-9.phar').symlink_to(ROOT / 'tests/.phpunit/phpunit-9.phar')
        self.evidence = self.base / 'evidence'
        self.evidence.mkdir()
        self.output = self.base / 'reports'
        self.output.mkdir()
        self.fixture = self.base / 'HealthyTest.php'
        self.fixture.write_text('<?php class HealthyTest extends \\PHPUnit\\Framework\\TestCase { public function testHealthy(): void { $this->assertTrue(true); } }')
        self.environment = os.environ.copy()
        self.environment.update(LOW_WIDONT_EVIDENCE_ROOT=str(self.evidence))

    def tearDown(self):
        """Remove only the export and outputs owned by this test."""
        self.temporary.cleanup()

    def invoke(self, path, inline=False):
        """Run the real wrapper with a selected destination spelling."""
        option = ['--log-junit=' + str(path)] if inline else ['--log-junit', str(path)]
        return subprocess.run([str(self.root / 'tests/.phpunit/run-phpunit.sh'),
                               '--no-configuration', '--bootstrap', str(self.root / 'tests/bootstrap.php'),
                               str(self.fixture), *option], cwd=self.root, env=self.environment,
                              capture_output=True, timeout=40)

    def test_rejects_unsafe_destinations_before_execution(self):
        """Preserve source, existing files and alias targets without starting PHPUnit."""
        occupied = self.output / 'occupied.xml'
        occupied.write_text('retained')
        dangling = self.output / 'dangling.xml'
        dangling.symlink_to(self.output / 'absent.xml')
        alias = self.output / 'source-alias'
        alias.symlink_to(self.root / 'low_widont', target_is_directory=True)
        source = self.root / 'low_widont/new-junit.xml'
        for inline in (False, True):
            for path in (source, occupied, dangling, alias / 'aliased-junit.xml'):
                with self.subTest(inline=inline, path=str(path)):
                    result = self.invoke(path, inline)
                    self.assertNotEqual(0, result.returncode, result.stdout + result.stderr)
                    self.assertNotIn(b'PHPUnit 9.', result.stdout)
                    self.assertEqual([], list(self.evidence.iterdir()))
                    self.assertFalse(source.exists())
                    self.assertFalse((self.root / 'low_widont/aliased-junit.xml').exists())
                    self.assertFalse((self.output / 'absent.xml').exists())
                    self.assertEqual('retained', occupied.read_text())
                    self.assertTrue(dangling.is_symlink())
                    self.assertTrue(alias.is_symlink())

    def test_publishes_fresh_owned_destinations(self):
        """Publish a fresh verified result in private temporary or known report storage."""
        reports = self.root / 'tests/.reports'
        reports.mkdir(mode=0o700)
        for index, path in enumerate((self.output / 'result.xml', reports / 'result.xml')):
            with self.subTest(path=str(path)):
                result = self.invoke(path, inline=bool(index))
                self.assertEqual(0, result.returncode, result.stdout + result.stderr)
                suite = ET.parse(path).getroot().find('testsuite')
                self.assertEqual('1', suite.get('tests'))
                self.assertEqual('1', suite.get('assertions'))
                self.assertEqual('0', suite.get('errors'))
                self.assertEqual('0', suite.get('failures'))


if __name__ == '__main__':
    unittest.main()
