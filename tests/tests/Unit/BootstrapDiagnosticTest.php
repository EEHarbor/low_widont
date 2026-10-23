<?php

declare(strict_types=1);

namespace LowWidontTests;

final class BootstrapDiagnosticTest extends TestCase
{
    /**
     * Supply authored first-require boundaries in standalone and PHPUnit entry paths.
     *
     * @return array
     */
    public function entryDiagnostics(): array
    {
        $entries = [
            ['tests/bootstrap.php', 'tests/support/Environment.php', []],
            ['tests/tools/autoload-probe.php', 'tests/support/Evidence.php', ['stream', '{context}']],
            ['tests/tools/archive-probe.php', 'tests/support/Evidence.php', ['checksum']],
            ['tests/tools/host-bootstrap-probe.php', 'tests/support/Evidence.php', []],
            ['tests/tools/raw-coverage.php', 'tests/tools/verify-tool.php', ['{context}/main.json']],
            ['tests/tools/request-context.php', 'tests/tools/verify-tool.php', ['site', '7.5.0', '{context}']],
        ];
        $cases = [];
        foreach ($entries as $entry) {
            foreach ([E_USER_WARNING, E_USER_DEPRECATED] as $severity) {
                $cases[] = array_merge($entry, [$severity]);
            }
        }
        return $cases;
    }

    /**
     * Reject suppressed bootstrap diagnostics before original addon execution can begin.
     *
     * @dataProvider entryDiagnostics
     * @param string $entry
     * @param string $dependency
     * @param array $arguments
     * @param int $severity
     * @return void
     * @throws \RuntimeException
     */
    public function testFirstRequiredSupportIsStrict($entry, $dependency, array $arguments, $severity): void
    {
        $root = dirname(__DIR__, 3);
        $directory = sys_get_temp_dir() . '/low-widont-entry-bootstrap-' . bin2hex(random_bytes(8));
        mkdir($directory, 0700);
        $directory = realpath($directory);
        try {
            foreach (['tests/tools', 'tests/support', 'tests/fixtures/addon', 'context'] as $folder) {
                mkdir($directory . '/' . $folder, 0700, true);
            }
            $source = $root . '/low_widont/';
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS)) as $file) {
                if ($file->isLink()) {
                    throw new \RuntimeException('Refusing a symlink in the original source mirror.');
                }
                $target = $directory . '/low_widont/' . substr($file->getPathname(), strlen($source));
                if (!is_dir(dirname($target))) {
                    mkdir(dirname($target), 0700, true);
                }
                copy($file->getPathname(), $target);
                $this->assertSame(hash_file('sha256', $file->getPathname()), hash_file('sha256', $target));
            }
            copy($root . '/tests/fixtures/addon/Extension.php', $directory . '/tests/fixtures/addon/Extension.php');
            copy($root . '/' . $entry, $directory . '/' . $entry);
            $bytes = file_get_contents($root . '/' . $dependency);
            $marker = '<?php';
            if ($dependency === 'tests/support/Environment.php') {
                $marker = 'namespace LowWidontTests {';
            }
            if ($dependency === 'tests/support/Evidence.php') {
                $marker = 'namespace LowWidontTests;';
            }
            $bytes = str_replace($marker, $marker . "\n    @trigger_error('entry bootstrap sentinel', " . $severity . ');', $bytes, $replacements);
            if ($replacements !== 1 || file_put_contents($directory . '/' . $dependency, $bytes) !== strlen($bytes)) {
                throw new \RuntimeException('Cannot prepare an isolated bootstrap diagnostic.');
            }
            $context = $directory . '/context';
            $arguments = array_map(static function ($argument) use ($context) {
                return str_replace('{context}', $context, $argument);
            }, $arguments);
            $command = array_merge([PHP_BINARY, '-d', 'error_reporting=-1', '-d', 'display_errors=1', '-d', 'log_errors=0', $directory . '/' . $entry], $arguments);
            $environment = getenv();
            $environment['TMPDIR'] = $context;
            $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['redirect', 1]], $pipes, $directory, $environment);
            $this->assertIsResource($process);
            $output = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            $this->assertNotSame(0, proc_close($process), $output);
            $this->assertStringContainsString('ErrorException: entry bootstrap sentinel', $output);
            $this->assertStringContainsString($dependency, $output);
            $this->assertFileDoesNotExist($context . '/main.json');
            $inventory = json_decode(file_get_contents($root . '/tests/SOURCE_INVENTORY.json'), true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame($inventory['archive_sha256'], hash_file('sha256', $root . '/low_widont/low_widont.phar'));
        } finally {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($iterator as $file) {
                $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            }
            rmdir($directory);
        }
    }
}
