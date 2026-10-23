<?php

declare(strict_types=1);

namespace LowWidontTests;

final class PackagingTest extends TestCase
{
    /**
     * Read addon metadata from the shipped JSON and PHP setup file.
     *
     * @return void
     */
    public function testSetupMatchesTheShippedMetadata(): void
    {
        $root = dirname(__DIR__, 3);
        $json = json_decode(file_get_contents($root . '/low_widont/addon.json'), true);
        $setup = require $root . '/low_widont/addon.setup.php';
        foreach (['name', 'description', 'version', 'namespace'] as $key) {
            $this->assertSame($json[$key], $setup[$key]);
        }
        $this->assertFalse($setup['settings_exist']);
        $this->assertSame('EEHarbor', $setup['author']);
        $this->assertSame('http://eeharbor.com/low_widont/documentation', $setup['docs_url']);
    }

    /**
     * Render both loose and archive license views with modern and legacy contexts.
     *
     * @dataProvider viewContexts
     * @param int $version
     * @param string $key
     * @param bool $ignored
     * @return void
     */
    public function testLicenseViews($version, $key, $ignored): void
    {
        $root = dirname(__DIR__, 3);
        Environment::$services['CP/Alert'] = $this->getMockBuilder(\stdClass::class)->addMethods(['getAllInlines'])->getMock();
        Environment::$services['CP/Alert']->method('getAllInlines')->willReturn('<div>Saved license</div>');
        $paths = [$root . '/low_widont/views/license.php', 'phar://' . $root . '/low_widont/low_widont.phar/Views/license.php'];
        if (getenv('LOW_WIDONT_REPORT_DIR')) {
            $paths[1] = getenv('LOW_WIDONT_REPORT_DIR') . '/source/Views/license.php';
        }
        foreach ($paths as $path) {
            $html = $this->render($path, $version, $key, $ignored);
            $expectedKey = strpos($key, 'ignore-site-') !== false ? '' : htmlspecialchars($key, ENT_QUOTES, 'UTF-8');
            $this->assertStringContainsString('name="license_key" value="' . $expectedKey . '"', $html);
            $this->assertStringContainsString('class="box ee' . $version, $html);
            $this->assertStringContainsString('<form action="save?site=1&amp;return=license"', $html);
            $this->assertSame($version > 2, strpos($html, 'Saved license') !== false);
            $this->assertSame($ignored, strpos($html, 'checked="checked"') !== false);
            $this->assertStringContainsString('Save License Key', $html);
        }
    }

    /**
     * Supply supported EE versions, ignore sentinels and characters needing escaping.
     *
     * @return array
     */
    public function viewContexts(): array
    {
        return [[2, 'key-123', false], [3, 'ignore-site-1', true], [6, 'key-"<&', false], [7, '', true]];
    }

    /**
     * Render a view with explicit caller variables and restore output buffering.
     *
     * @param string $path
     * @param int $ee_ver
     * @param string $license_key
     * @param bool $ignore_site
     * @return string
     */
    private function render($path, $ee_ver, $license_key, $ignore_site)
    {
        $action_url = 'save?site=1&return=license';
        ob_start();
        try {
            include $path;
            return ob_get_contents();
        } finally {
            ob_end_clean();
        }
    }

    /**
     * Fail suppressed deprecations instead of silently losing source diagnostics.
     *
     * @return void
     */
    public function testDiagnosticHandlerSeesSuppressedDeprecations(): void
    {
        $this->expectException(\ErrorException::class);
        $this->expectExceptionMessage('suppressed diagnostic fixture');
        @trigger_error('suppressed diagnostic fixture', E_USER_DEPRECATED);
    }

    /**
     * Fail suppressed ordinary warnings through the same strict diagnostic handler.
     *
     * @return void
     */
    public function testDiagnosticHandlerSeesSuppressedWarnings(): void
    {
        $this->expectException(\ErrorException::class);
        $this->expectExceptionMessage('suppressed warning fixture');
        @trigger_error('suppressed warning fixture', E_USER_WARNING);
    }

    /**
     * Compile every shipped PHP entry and the archive stub in the selected runtime.
     *
     * @return void
     */
    public function testAllShippedPhpCompilesWithoutDiagnostics(): void
    {
        $root = dirname(__DIR__, 3);
        $files = [];
        $temporaryRoot = sys_get_temp_dir() . '/low-widont-compile-' . bin2hex(random_bytes(8));
        mkdir($temporaryRoot, 0700);
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . '/low_widont', \FilesystemIterator::SKIP_DOTS)) as $file) {
            if (in_array($file->getExtension(), ['php', 'phar'], true)) {
                $files[] = $file->getPathname();
            }
        }
        foreach (new \RecursiveIteratorIterator(new \Phar($root . '/low_widont/low_widont.phar')) as $file) {
            if ($file->getExtension() === 'php') {
                $target = $temporaryRoot . '/' . count($files) . '.php';
                file_put_contents($target, $file->getContent());
                $this->assertSame(hash('sha256', $file->getContent()), hash_file('sha256', $target));
                $files[] = $target;
            }
        }
        $this->assertCount(27, $files);
        try {
            foreach ($files as $file) {
                $process = proc_open([PHP_BINARY, '-d', 'error_reporting=-1', '-d', 'display_errors=1', '-l', $file], [1 => ['pipe', 'w'], 2 => ['redirect', 1]], $pipes);
                $output = stream_get_contents($pipes[1]);
                fclose($pipes[1]);
                $exit = proc_close($process);
                $this->assertSame(0, $exit, $file . ': ' . $output);
                $this->assertStringContainsString('No syntax errors detected', $output, $file);
                $this->assertDoesNotMatchRegularExpression('/(?:Deprecated|Warning|Notice|Fatal error):/', $output, $file . ': ' . $output);
            }
        } finally {
            foreach (glob($temporaryRoot . '/*.php') as $temporaryFile) {
                unlink($temporaryFile);
            }
            rmdir($temporaryRoot);
        }
    }
}
