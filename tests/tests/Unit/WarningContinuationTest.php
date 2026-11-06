<?php

declare(strict_types=1);

namespace LowWidontTests;

require_once dirname(__DIR__, 2) . '/support/SourceWarningObservation.php';
require_once dirname(__DIR__, 2) . '/support/Evidence.php';

final class WarningContinuationTest extends TestCase
{

    /**
     * Supply suppressed support diagnostics that must never enter observational continuation.
     *
     * @return array
     */
    public function supportDiagnostics(): array
    {
        return [[E_USER_WARNING], [E_USER_DEPRECATED]];
    }

    /**
     * Keep authored support warnings and deprecations strict even under suppression.
     *
     * @dataProvider supportDiagnostics
     * @param int $severity
     * @return void
     */
    public function testSupportDiagnosticsStillThrow($severity): void
    {
        $source = 'phar://' . dirname(__DIR__, 3) . '/low_widont/low_widont.phar/FluxCapacitor/Conduit/PersistentCache.php';
        $observer = new SourceWarningObservation($source, hash_file('sha256', $source));
        set_error_handler($observer, E_ALL);
        try {
            try {
                @trigger_error('authored support diagnostic', $severity);
                $this->fail('The observational handler accepted an authored support diagnostic.');
            } catch (\ErrorException $error) {
                $this->assertSame($severity, $error->getSeverity());
                $this->assertSame(__FILE__, $error->getFile());
                $this->assertSame([], $observer->diagnostics());
            }
        } finally {
            restore_error_handler();
        }
    }

    /**
     * Supply warning/deprecation faults at every authored bootstrap require in both entry points.
     *
     * @return array
     */
    public function bootstrapDiagnostics(): array
    {
        $cases = [];
        foreach (['cache-warning-observation.php', 'extraction-warning-observation.php'] as $script) {
            foreach (['Environment.php', 'Evidence.php', 'SourceWarningObservation.php'] as $support) {
                foreach ([E_USER_WARNING, E_USER_DEPRECATED] as $severity) {
                    $cases[] = [$script, $support, $severity];
                }
            }
        }
        return $cases;
    }

    /**
     * Fail before source execution when copied authored bootstrap code emits a suppressed diagnostic.
     *
     * @dataProvider bootstrapDiagnostics
     * @param string $script
     * @param string $support
     * @param int $severity
     * @return void
     */
    public function testBootstrapDiagnosticsStillThrow($script, $support, $severity): void
    {
        [$exit, $output, $published] = $this->bootstrapControl($script, $support, $severity);
        $this->assertNotSame(0, $exit);
        $this->assertStringContainsString('ErrorException: bootstrap support sentinel', $output);
        $this->assertStringContainsString('/tests/support/' . $support, $output);
        $this->assertStringNotContainsString('source_diagnostic', $output);
        $this->assertFalse($published);
    }

    /**
     * Supply both original observation entry points for pre-execution archive verification.
     *
     * @return array
     */
    public function observationScripts(): array
    {
        return [['cache-warning-observation.php'], ['extraction-warning-observation.php']];
    }

    /**
     * Reject an owned corrupted archive copy against inventory before invoking original source.
     *
     * @dataProvider observationScripts
     * @param string $script
     * @return void
     */
    public function testArchivePinsAreCheckedBeforeContinuation($script): void
    {
        [$exit, $output, $published] = $this->bootstrapControl($script);
        $this->assertNotSame(0, $exit);
        $this->assertStringContainsString('RuntimeException: Observation archive differs from the pinned source inventory.', $output);
        $this->assertStringNotContainsString('source_diagnostic', $output);
        $this->assertFalse($published);
    }

    /**
     * Isolate a support fault or corrupt input without touching the shipped source or shared PHP state.
     *
     * @param string $script
     * @param string|null $support
     * @param int|null $severity
     * @return array
     * @throws \RuntimeException
     */
    private function bootstrapControl($script, $support = null, $severity = null): array
    {
        $root = dirname(__DIR__, 3);
        $directory = sys_get_temp_dir() . '/low-widont-bootstrap-control-' . bin2hex(random_bytes(8));
        foreach (['tests/tools', 'tests/support', 'tests/.phpunit', 'low_widont', 'report'] as $folder) {
            mkdir($directory . '/' . $folder, 0700, true);
        }
        try {
            $inventory = json_decode(file_get_contents($root . '/tests/SOURCE_INVENTORY.json'), true, 512, JSON_THROW_ON_ERROR);
            foreach ($inventory['files'] as $name => $file) {
                if (strpos($name, '!/') === false) {
                    if (!is_dir(dirname($directory . '/' . $name))) {
                        mkdir(dirname($directory . '/' . $name), 0700, true);
                    }
                    copy($root . '/' . $name, $directory . '/' . $name);
                    $this->assertSame($file['sha256'], hash_file('sha256', $directory . '/' . $name));
                }
            }
            $archive = $directory . '/low_widont/low_widont.phar';
            copy($root . '/low_widont/low_widont.phar', $archive);
            $this->assertSame($inventory['archive_sha256'], hash_file('sha256', $archive));
            copy($root . '/tests/SOURCE_INVENTORY.json', $directory . '/tests/SOURCE_INVENTORY.json');
            copy($root . '/tests/tools/' . $script, $directory . '/tests/tools/' . $script);
            foreach (['Environment.php', 'Evidence.php', 'SourceWarningObservation.php'] as $name) {
                copy($root . '/tests/support/' . $name, $directory . '/tests/support/' . $name);
            }
            symlink($root . '/tests/.phpunit/phpunit-9.phar', $directory . '/tests/.phpunit/phpunit-9.phar');
            $generatedHash = null;
            if ($support !== null) {
                $file = $directory . '/tests/support/' . $support;
                $bytes = file_get_contents($file);
                $needle = $support === 'Environment.php' ? 'namespace LowWidontTests {' : 'namespace LowWidontTests;';
                $replacement = $needle . "\n    @trigger_error('bootstrap support sentinel', " . $severity . ');';
                $bytes = str_replace($needle, $replacement, $bytes, $replacements);
                if ($replacements !== 1 || file_put_contents($file, $bytes) !== strlen($bytes)) {
                    throw new \RuntimeException('Cannot prepare the authored bootstrap diagnostic control.');
                }
                $generatedHash = hash_file('sha256', $file);
            }
            if ($support === null && file_put_contents($archive, 'owned corrupt input', FILE_APPEND) !== strlen('owned corrupt input')) {
                throw new \RuntimeException('Cannot prepare the owned corrupt archive input.');
            }
            $report = realpath($directory . '/report');
            $arguments = $script === 'cache-warning-observation.php' ? ['base', 'get', $report] : ['cold', $report];
            $command = array_merge([PHP_BINARY, '-d', 'error_reporting=-1', '-d', 'display_errors=stderr', '-d', 'log_errors=0',
                $directory . '/tests/tools/' . $script], $arguments);
            $environment = getenv();
            $environment['TMPDIR'] = $report;
            $native = NativeProcess::runCommand($command, null, $environment);
            $output = $native[1] . $native[2];
            $exit = $native[0];
            $published = file_exists($report . '/observation.json');
            $this->assertSame($inventory['archive_sha256'], hash_file('sha256', $root . '/low_widont/low_widont.phar'));
            $reportRoot = getenv('LOW_WIDONT_WARNING_OBSERVATIONS_DIR');
            if ($reportRoot) {
                $context = ($support === null ? 'pin-' : 'bootstrap-') . $script . ($support === null ? '' : '-' . $support . '-' . $severity);
                $record = ['control' => $context, 'script' => $script, 'support' => $support, 'severity' => $severity,
                    'command' => $command, 'php' => PHP_VERSION, 'exit_status' => $exit, 'observation_published' => $published,
                    'source_archive_sha256' => $inventory['archive_sha256'], 'mirror_archive_sha256' => hash_file('sha256', $archive),
                    'generated_support_sha256' => $generatedHash, 'output_sha256' => hash('sha256', $output)];
                Evidence::write($reportRoot . '/controls/' . $context . '.json', $record);
                if (file_put_contents($reportRoot . '/controls/' . $context . '.log', $output) !== strlen($output)) {
                    throw new \RuntimeException('Cannot retain complete bootstrap control output.');
                }
            }
            return [$exit, $output, $published];
        } finally {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($iterator as $file) {
                $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            }
            rmdir($directory);
        }
    }
}
