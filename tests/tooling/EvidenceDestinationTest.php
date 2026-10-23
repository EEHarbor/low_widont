<?php

declare(strict_types=1);

namespace LowWidontTests;

final class EvidenceDestinationTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Supply quiet publication and occupied/source-alias destinations for both entry points.
     *
     * @return array
     */
    public function destinations(): array
    {
        $cases = [['publisher', 'fresh']];
        foreach (['publisher', 'collector'] as $entry) {
            foreach (['dangling', 'existing', 'source-file-alias', 'source-directory-alias'] as $kind) {
                $cases[] = [$entry, $kind];
            }
        }
        return $cases;
    }

    /**
     * Preserve occupied outputs and their targets before any publication or suite execution.
     *
     * @dataProvider destinations
     * @param string $entry
     * @param string $kind
     * @return void
     * @throws \RuntimeException
     */
    public function testDestinationBoundary($entry, $kind): void
    {
        $root = dirname(__DIR__);
        $directory = sys_get_temp_dir() . '/low-widont-output-boundary-' . bin2hex(random_bytes(8));
        foreach (['tests/tools', 'tests/support', 'tests/.phpunit', 'low_widont', 'reports', 'outside'] as $folder) {
            mkdir($directory . '/' . $folder, 0700, true);
        }
        $directory = realpath($directory);
        try {
            foreach (['tools/raw-coverage.php', 'tools/verify-tool.php', 'support/Evidence.php', '.phpunit/manifest.json'] as $file) {
                copy($root . '/' . $file, $directory . '/tests/' . $file);
                $this->assertSame(hash_file('sha256', $root . '/' . $file), hash_file('sha256', $directory . '/tests/' . $file));
            }
            symlink($root . '/.phpunit/phpunit-9.phar', $directory . '/tests/.phpunit/phpunit-9.phar');
            $this->assertSame('6b5235f282c6ccab3674ac27c5b1ddbd1da838e74bf07002ddcfd030d31298d7', hash_file('sha256', $directory . '/tests/.phpunit/phpunit-9.phar'));
            file_put_contents($directory . '/low_widont/source-sentinel.txt', 'owned source boundary sentinel');
            $path = $directory . '/reports/report.json';
            if ($kind === 'fresh') {
                $path = $directory . '/reports/fresh/nested/report.json';
            }
            if ($kind === 'dangling') {
                symlink($directory . '/outside/new-report.json', $path);
            }
            if ($kind === 'existing') {
                file_put_contents($path, 'owned existing report');
            }
            if ($kind === 'source-file-alias') {
                symlink($directory . '/low_widont/source-sentinel.txt', $path);
            }
            if ($kind === 'source-directory-alias') {
                symlink($directory . '/low_widont', $directory . '/reports/source-alias');
                $path = $directory . '/reports/source-alias/new-report.json';
            }
            $before = $this->snapshot($directory);
            $command = [PHP_BINARY, '-d', 'error_reporting=-1', '-d', 'display_errors=1', '-d', 'log_errors=0'];
            if ($entry === 'publisher') {
                $code = 'error_reporting(E_ALL);set_error_handler(static function ($s,$m,$f,$l) {throw new ErrorException($m,0,$s,$f,$l);},E_ALL);'
                    . 'require $argv[1];LowWidontTests\\Evidence::write($argv[2],["probe"=>true]);';
                $command = array_merge($command, ['-r', $code, $directory . '/tests/support/Evidence.php', $path]);
            }
            if ($entry === 'collector') {
                $command = array_merge($command, [$directory . '/tests/tools/raw-coverage.php', $path]);
            }
            $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['redirect', 1]], $pipes, $directory);
            $this->assertIsResource($process);
            $output = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            $exit = proc_close($process);
            if ($kind === 'fresh') {
                $this->assertSame(0, $exit, $output);
                $this->assertSame('', $output);
                $this->assertSame(['probe' => true], json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR));
                $this->assertSame([], glob(dirname($path) . '/.publishing-*'));
                return;
            }
            $this->assertNotSame(0, $exit);
            $message = $kind === 'source-directory-alias' ? 'Refusing to publish evidence within shipped source:' : 'Refusing to replace evidence:';
            $this->assertStringContainsString('RuntimeException: ' . $message, $output);
            $this->assertDoesNotMatchRegularExpression('/(?:Warning|Notice|Deprecated):/', $output);
            $this->assertSame($before, $this->snapshot($directory), 'A rejected destination changed links, files or directories.');
            $this->assertFileDoesNotExist($directory . '/outside/new-report.json');
            $this->assertFileDoesNotExist($directory . '/low_widont/new-report.json');
        } finally {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($iterator as $file) {
                $file->isLink() || !$file->isDir() ? unlink($file->getPathname()) : rmdir($file->getPathname());
            }
            rmdir($directory);
        }
    }

    /**
     * Record paths and symlink text without following any alias into a source directory.
     *
     * @param string $directory
     * @return array
     */
    private function snapshot($directory): array
    {
        $snapshot = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST) as $file) {
            $relative = substr($file->getPathname(), strlen($directory) + 1);
            if ($file->isLink()) {
                $snapshot[$relative] = ['link' => readlink($file->getPathname())];
                continue;
            }
            $snapshot[$relative] = $file->isDir() ? ['directory' => true] : ['sha256' => hash_file('sha256', $file->getPathname())];
        }
        ksort($snapshot);
        return $snapshot;
    }
}
