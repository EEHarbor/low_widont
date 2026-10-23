<?php

declare(strict_types=1);

namespace LowWidontTests;

require_once dirname(__DIR__, 2) . '/support/SourceWarningObservation.php';
require_once dirname(__DIR__, 2) . '/support/Evidence.php';

final class WarningContinuationTest extends TestCase
{
    /**
     * Supply real base/module collisions for every operation that uses the path guard.
     *
     * @return array
     */
    public function cacheContexts(): array
    {
        $contexts = [];
        foreach (['base', 'module'] as $scope) {
            foreach (['get', 'set', 'delete', 'clear'] as $operation) {
                $contexts[] = [$scope, $operation];
            }
        }
        return $contexts;
    }

    /**
     * Retain actual warning continuation effects while requiring a diagnostic-free public result.
     *
     * @group regression
     * @dataProvider cacheContexts
     * @param string $scope
     * @param string $operation
     * @return void
     */
    public function testCacheCollisionsAreDiagnosticFree($scope, $operation): void
    {
        $this->observe('cache-warning-observation.php', [$scope, $operation], 'cache-' . $scope . '-' . $operation,
            function (array $record, $directory) use ($scope) {
                $this->assertFalse($record['actual_return']);
                $this->assertSame(['logger'], $record['loader_calls']);
                $path = $directory . '/cache/' . ($scope === 'module' ? 'low_widont/' : '');
                $kind = $scope === 'module' ? 'module cache folder' : 'cache folder';
                $this->assertSame(['Low Widont: Write Cache enabled but could not create ' . $kind . ': ' . $path], $record['developer_messages']);
                $this->assertSame('owned collision', $record['collision_contents']);
                $this->assertFalse($record['cache_entry_created']);
            });
    }

    /**
     * Supply actual cold extraction with and without original recursive cleanup.
     *
     * @return array
     */
    public function extractionContexts(): array
    {
        return [['cold'], ['cleanup']];
    }

    /**
     * Require unchanged extracted member bytes, reusable cache and diagnostic-free cleanup.
     *
     * @group regression
     * @dataProvider extractionContexts
     * @param string $mode
     * @return void
     */
    public function testActualExtractionIsDiagnosticFree($mode): void
    {
        $this->observe('extraction-warning-observation.php', [$mode], 'extraction-' . $mode,
            function (array $record, $directory) use ($mode) {
                $this->assertTrue($record['class_loaded']);
                $cache = $directory . '/pharextract/low_widont';
                $this->assertSame($cache . '/FluxCapacitor/Base/Pi.php', $record['class_file']);
                $this->assertSame($cache, $record['cwd_after_load']);
                $this->assertTrue($record['marker_created']);
                $inventory = json_decode(file_get_contents(dirname(__DIR__, 2) . '/SOURCE_INVENTORY.json'), true, 512, JSON_THROW_ON_ERROR);
                $members = [];
                foreach ($inventory['files'] as $name => $file) {
                    $prefix = 'low_widont/low_widont.phar!/';
                    if (strpos($name, $prefix) === 0 && $name !== $prefix . '{stub}') {
                        $members[substr($name, strlen($prefix))] = $file['sha256'];
                    }
                }
                ksort($members);
                $this->assertCount(22, $members);
                $this->assertSame($members, $record['members']);
                $this->assertSame($members, $record['extracted']);
                $this->assertSame($members, $record['reused']);
                if ($mode === 'cleanup') {
                    $this->assertSame(getcwd(), $record['cwd_after_cleanup']);
                    $this->assertSame([], $record['remaining_paths'], 'Original cleanup left paths in the owned extraction tree.');
                    $this->assertFalse($record['cache_directory_exists']);
                }
                if ($mode === 'cold') {
                    $this->assertTrue($record['cache_directory_exists']);
                }
            });
    }

    /**
     * Run an isolated observation while keeping every reported source diagnostic red.
     *
     * @param string $script
     * @param array $arguments
     * @param string $context
     * @param callable $assertEffects
     * @return void
     * @throws \RuntimeException
     */
    private function observe($script, array $arguments, $context, callable $assertEffects): void
    {
        $reportRoot = getenv('LOW_WIDONT_WARNING_OBSERVATIONS_DIR');
        $directory = ($reportRoot ?: sys_get_temp_dir()) . '/' . $context . '-' . bin2hex(random_bytes(8));
        mkdir($directory, 0700);
        $directory = realpath($directory);
        $command = array_merge([PHP_BINARY, '-d', 'error_reporting=-1', '-d', 'display_errors=1', '-d', 'log_errors=0',
            dirname(__DIR__, 2) . '/tools/' . $script], $arguments, [$directory]);
        $environment = getenv();
        $environment['TMPDIR'] = $directory;
        $environment['TMP'] = $directory;
        $environment['TEMP'] = $directory;
        try {
            $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['file', $directory . '/stderr.log', 'w']], $pipes, null, $environment);
            $this->assertIsResource($process);
            $stdout = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            $exit = proc_close($process);
            if (file_put_contents($directory . '/stdout.log', $stdout) !== strlen($stdout)
                || file_put_contents($directory . '/exit-status.txt', (string) $exit) !== strlen((string) $exit)) {
                throw new \RuntimeException('Cannot retain complete observation process evidence.');
            }
            $this->assertFileExists($directory . '/observation.json', file_get_contents($directory . '/stderr.log'));
            $record = json_decode(file_get_contents($directory . '/observation.json'), true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame('observational-warning-continuation', $record['mode']);
            $this->assertSame($context, $record['context']);
            $this->assertSame(PHP_VERSION, $record['php']);
            $this->assertFalse($record['strict_suite_graphs_mergeable']);
            $this->assertSame(hash_file('sha256', dirname(__DIR__, 3) . '/low_widont/low_widont.phar'), $record['archive_sha256']);
            $this->assertSame(!$record['diagnostics'], $record['diagnostic_free']);
            foreach ($record['diagnostics'] as $diagnostic) {
                $this->assertSame(E_WARNING, $diagnostic['severity']);
                $this->assertSame($record['source_sha256'], $diagnostic['source_sha256']);
                $this->assertStringContainsString(json_encode(['source_diagnostic' => $diagnostic], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), file_get_contents($directory . '/stderr.log'));
            }
            if ($record['native_coverage_collected']) {
                $this->assertSame(7, $record['flags']);
                $this->assertNotEmpty($record['source']);
            }
            $assertEffects($record, $directory);
            $this->assertSame([], $record['diagnostics'], 'Observed source diagnostics remain a regression: ' . file_get_contents($directory . '/stderr.log'));
            $this->assertSame(0, $exit, $stdout);
        } finally {
            if (!$reportRoot) {
                $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
                foreach ($iterator as $file) {
                    $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
                }
                rmdir($directory);
            }
        }
    }

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
            $command = array_merge([PHP_BINARY, '-d', 'error_reporting=-1', '-d', 'display_errors=1', '-d', 'log_errors=0',
                $directory . '/tests/tools/' . $script], $arguments);
            $environment = getenv();
            $environment['TMPDIR'] = $report;
            $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['redirect', 1]], $pipes, null, $environment);
            $this->assertIsResource($process);
            $output = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            $exit = proc_close($process);
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
