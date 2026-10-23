<?php

declare(strict_types=1);

namespace LowWidontTests;

final class RequestContextTest extends TestCase
{
    /**
     * Exercise valid addon navigation, EE dispatch, CP/site and fieldtype loader contexts.
     *
     * @dataProvider contexts
     * @param string $mode
     * @param string $version
     * @return void
     */
    public function testIsolatedRequestContext($mode, $version): void
    {
        $this->runContext($mode, $version);
    }

    /**
     * Supply supported host layouts without replacing production source bytes.
     *
     * @return array
     */
    public function contexts(): array
    {
        return [
            ['navigation', '3.9.0'], ['navigation', '4.3.0'], ['navigation', '5.4.0'], ['navigation', '6.4.0'], ['navigation', '7.5.0'],
            ['legacy', '2.5.5'], ['legacy-existing-directives', '2.5.5'], ['cp', '7.5.0'], ['site', '7.5.0'],
            ['fieldtype-legacy', '3.9.0'], ['fieldtype-installer', '7.5.0'], ['fieldtype-predefined', '7.5.0'],
            ['legacy-navigation', '2.5.5'], ['theme-root', '7.5.0'], ['updater-legacy', '2.5.5'], ['updater-legacy', '3.9.0'],
            ['legacy-fallback-cache', '2.5.5'], ['script-pagination', '7.5.0'], ['license-payload', '7.5.0'],
            ['fieldtype-preloaded', '7.5.0'], ['fieldtype-installer-missing', '7.5.0'],
            ['fieldtype-preloaded-system', '7.5.0'], ['fieldtype-preloaded-legacy', '7.5.0'], ['fieldtype-preloaded-installer', '7.5.0'],
        ];
    }

    /**
     * Preserve request-context source failures while unrelated cases continue executing.
     *
     * @group regression
     * @dataProvider regressionContexts
     * @param string $mode
     * @param string $version
     * @return void
     */
    public function testIsolatedSourceRegressions($mode, $version): void
    {
        $this->runContext($mode, $version);
    }

    /**
     * Supply legacy comparison, optional include and extension-exception failures.
     *
     * @return array
     */
    public function regressionContexts(): array
    {
        return [['legacy', '2.6.0'], ['legacy', '2.7.3'], ['legacy', '2.9.0'], ['legacy-cache', '2.9.0'], ['updater', '7.5.0'], ['missing-navigation-base', '7.5.0']];
    }

    /**
     * Execute one fresh request with immutable archive copies and isolated addon fixtures.
     *
     * @param string $mode
     * @param string $version
     * @return void
     */
    private function runContext($mode, $version)
    {
        $root = dirname(__DIR__, 3);
        $temporary = sys_get_temp_dir() . '/low-widont-request-' . bin2hex(random_bytes(8));
        mkdir($temporary, 0700);
        $temporary = realpath($temporary);
        $environment = getenv();
        $environment['TMPDIR'] = $temporary;
        $environment['TMP'] = $temporary;
        $environment['TEMP'] = $temporary;
        $command = [PHP_BINARY, '-d', 'error_reporting=-1', '-d', 'display_errors=1', '-d', 'log_errors=0',
            $root . '/tests/tools/request-context.php', $mode, $version, $temporary];
        try {
            $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['redirect', 1]], $pipes, $root, $environment);
            $this->assertIsResource($process);
            $output = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            $exit = proc_close($process);
            $this->assertSame(0, $exit, $output);
            $result = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame($mode, $result['mode']);
            $this->assertSame($version, $result['ee']);
            $this->assertNotEmpty($result['cases']);
            foreach ($result['shipped_source_hashes'] as $relative => $hash) {
                $this->assertSame(hash_file('sha256', $root . '/low_widont/' . $relative), $hash, $relative);
            }
            if ($result['native_report'] !== null) {
                $this->assertFileExists($result['native_report']);
                $native = json_decode(file_get_contents($result['native_report']), true, 512, JSON_THROW_ON_ERROR);
                $archiveEntries = 0;
                foreach ($native['source'] as $file => $coverage) {
                    if (strpos($file, 'phar://') === 0) {
                        $entry = explode('.phar/', $file, 2)[1];
                        $this->assertSame(hash_file('sha256', 'phar://' . $root . '/low_widont/low_widont.phar/' . $entry), $coverage['sha256']);
                        $archiveEntries++;
                    }
                }
                $this->assertGreaterThan(0, $archiveEntries, 'Native context evidence must include actual archive graphs.');
            }
            $this->assertSame([], $result['failures'], json_encode($result['failures'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $this->assertGreaterThan(0, $result['assertions']);
        } finally {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($temporary, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $file) {
                $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            }
            rmdir($temporary);
        }
    }
}
