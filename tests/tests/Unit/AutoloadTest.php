<?php

declare(strict_types=1);

namespace LowWidontTests;

final class AutoloadTest extends TestCase
{
    /**
     * Reject foreign and addon-local non-FluxCapacitor namespaces without filesystem work.
     *
     * @return void
     */
    public function testNamespaceGuard(): void
    {
        foreach (spl_autoload_functions() as $loader) {
            if ($loader instanceof \Closure && (new \ReflectionFunction($loader))->getFileName() === dirname(__DIR__, 3) . '/low_widont/autoload.php') {
                $this->assertNull($loader('Other\\FluxCapacitor\\ClassName'));
                $this->assertNull($loader('Low\\Widont\\AddonClass'));
                return;
            }
        }
        $this->fail('The shipped addon autoloader is not registered.');
    }

    /**
     * Exercise actual loader failure and native/fallback archive paths in fresh processes.
     *
     * @dataProvider loaderModes
     * @param string $mode
     * @param string $expected
     * @param bool $opcache
     * @return void
     */
    public function testNativeLoaderPaths($mode, $expected, $opcache): void
    {
        $root = dirname(__DIR__, 3);
        $temporaryRoot = sys_get_temp_dir() . '/low-widont-loader-' . bin2hex(random_bytes(8));
        mkdir($temporaryRoot, 0700);
        $environment = getenv();
        $environment['TMPDIR'] = $temporaryRoot;
        $command = [PHP_BINARY, '-d', 'error_reporting=-1', '-d', 'display_errors=1'];
        if ($opcache) {
            $command = array_merge($command, ['-d', 'opcache.enable_cli=1', '-d', 'opcache.validate_permission=1']);
        }
        $command = array_merge($command, [$root . '/tests/tools/autoload-probe.php', $mode, $temporaryRoot]);
        try {
            $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['redirect', 1]], $pipes, $root, $environment);
            $output = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            $exit = proc_close($process);
            $this->assertSame(0, $exit, $output);
            $this->assertStringContainsString($expected, $output);
            $this->assertDoesNotMatchRegularExpression('/(?:Deprecated|Warning|Notice|Fatal error):/', $output);
        } finally {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($temporaryRoot, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($iterator as $file) {
                $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            }
            rmdir($temporaryRoot);
        }
    }

    /**
     * Include direct-access guards, missing paths and OPcache query-string loading.
     *
     * @return array
     */
    public function loaderModes(): array
    {
        return [
            ['direct-access', 'No direct script access allowed', false],
            ['missing-archive', 'Add-on PHAR file at location ', false],
            ['missing-class', 'Class at location ', false],
            ['stream', 'loaded', false],
            ['stream', 'loaded', true],
        ];
    }
    /**
     * Extract and load the archive without suppressed filesystem diagnostics.
     *
     * @group regression
     * @return void
     */
    public function testFallbackExtractionIsDiagnosticFree(): void
    {
        $this->testNativeLoaderPaths('fallback', 'loaded', false);
    }

}
