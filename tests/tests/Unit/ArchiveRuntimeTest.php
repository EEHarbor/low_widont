<?php

declare(strict_types=1);

namespace LowWidontTests;

final class ArchiveRuntimeTest extends TestCase
{
    /**
     * Load the original archive stub even when class measurement uses verified loose copies.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        require_once dirname(__DIR__, 3) . '/low_widont/low_widont.phar';
    }

    /**
     * Extract every real manifest entry using the shipped PHP fallback helper.
     *
     * @dataProvider archiveEntries
     * @param string $name
     * @return void
     */
    public function testManifestAndRealEntryExtraction($name): void
    {
        $archive = dirname(__DIR__, 3) . '/low_widont/low_widont.phar';
        $stream = fopen($archive, 'rb');
        try {
            fseek($stream, \Extract_low_widont::LEN);
            $length = unpack('Vlength', fread($stream, 4))['length'];
            $manifest = fread($stream, $length);
            $info = \Extract_low_widont::_unpack($manifest);
            $this->assertArrayHasKey($name, $info['m']);
            $this->assertCount(22, $info['m']);
            $entry = $info['m'][$name];
            fseek($stream, \Extract_low_widont::LEN + 4 + $length + $entry[7]);
            $actual = \Extract_low_widont::extractFile($name, $entry, $stream);
            $this->assertSame(file_get_contents('phar://' . $archive . '/' . $name), $actual);
        } finally {
            fclose($stream);
        }
    }

    /**
     * Enumerate all original archive entries without extracting or rebuilding them.
     *
     * @return array
     */
    public function archiveEntries(): array
    {
        $path = dirname(__DIR__, 3) . '/low_widont/low_widont.phar';
        $prefix = 'phar://' . $path . '/';
        $entries = [];
        foreach (new \RecursiveIteratorIterator(new \Phar($path)) as $file) {
            $name = substr($file->getPathname(), strlen($prefix));
            $entries[$name] = [$name];
        }
        return $entries;
    }

    /**
     * Cover raw/gzip/bzip2 and read-chunk boundaries using valid PHAR entry metadata.
     *
     * @dataProvider payloadShapes
     * @param string $compression
     * @param int $length
     * @return void
     */
    public function testValidCompressionAndChunkBoundaries($compression, $length): void
    {
        $data = substr(str_repeat("a\0b\xff", (int) ceil($length / 4)), 0, $length);
        $encoded = $data;
        $flags = 0;
        if ($compression === 'gzip') {
            $encoded = gzdeflate($data);
            $flags = \Extract_low_widont::GZ;
        }
        if ($compression === 'bzip2') {
            $encoded = bzcompress($data);
            $flags = \Extract_low_widont::BZ2;
        }
        $stream = fopen('php://temp', 'w+b');
        try {
            fwrite($stream, $encoded);
            rewind($stream);
            $entry = [strlen($data), 0, strlen($encoded), sprintf('%u', crc32($data) & 0xffffffff), $flags, 0];
            $this->assertSame($data, \Extract_low_widont::extractFile('fixture.bin', $entry, $stream));
        } finally {
            fclose($stream);
        }
    }

    /**
     * Supply empty, exact-chunk, adjacent-chunk and supported compression payloads.
     *
     * @return array
     */
    public function payloadShapes(): array
    {
        return [['raw', 0], ['raw', 8191], ['raw', 8192], ['raw', 8193], ['raw', 16384], ['gzip', 16385], ['bzip2', 16385]];
    }

    /**
     * Honor TMPDIR and the platform fallback without changing global PHP configuration.
     *
     * @return void
     */
    public function testTemporaryDirectorySelection(): void
    {
        $original = getenv('TMPDIR');
        try {
            putenv('TMPDIR=/private/tmp/low-widont-tmpdir-fixture');
            $this->assertSame('/private/tmp/low-widont-tmpdir-fixture', \Extract_low_widont::tmpdir());
            putenv('TMPDIR');
            $this->assertSame(realpath('/tmp'), \Extract_low_widont::tmpdir());
        } finally {
            $original === false ? putenv('TMPDIR') : putenv('TMPDIR=' . $original);
        }
    }

    /**
     * Remove files and empty child directories while restoring the caller directory.
     *
     * @return void
     */
    public function testTemporaryCleanupPreservesTheWorkingDirectory(): void
    {
        $root = sys_get_temp_dir() . '/low-widont-cleanup-' . bin2hex(random_bytes(8));
        mkdir($root, 0700);
        mkdir($root . '/empty', 0700);
        file_put_contents($root . '/entry.txt', 'temporary');
        $directory = getcwd();
        try {
            \Extract_low_widont::_removeTmpFiles($root, $directory);
            $this->assertDirectoryDoesNotExist($root);
            $this->assertSame($directory, getcwd());
        } finally {
            chdir($directory);
            $this->removeTemporaryTree($root);
        }
    }

    /**
     * Remove nested extraction content without suppressed nonempty-directory warnings.
     *
     * @group regression
     * @return void
     */
    public function testNestedTemporaryCleanupIsDiagnosticFree(): void
    {
        $root = sys_get_temp_dir() . '/low-widont-cleanup-' . bin2hex(random_bytes(8));
        mkdir($root, 0700);
        mkdir($root . '/nested', 0700);
        file_put_contents($root . '/nested/entry.txt', 'temporary');
        $directory = getcwd();
        try {
            \Extract_low_widont::_removeTmpFiles($root, $directory);
            $this->assertDirectoryDoesNotExist($root);
            $this->assertSame($directory, getcwd());
        } finally {
            chdir($directory);
            $this->removeTemporaryTree($root);
        }
    }

    /**
     * Fail invalid checksum metadata through the extractor's explicit diagnostic.
     *
     * @return void
     */
    public function testChecksumMismatchIsReportedExplicitly(): void
    {
        $this->probeCorruptEntry('checksum', 'Invalid internal .phar file (checksum error)');
    }

    /**
     * Report invalid uncompressed size without dereferencing an undefined stat variable.
     *
     * @group regression
     * @return void
     */
    public function testSizeMismatchIsReportedWithoutPhpDiagnostics(): void
    {
        $this->probeCorruptEntry('size', 'Invalid internal .phar file (size error');
    }

    /**
     * Invoke terminal error paths in the selected runtime without sequential pipe drains.
     *
     * @param string $mode
     * @param string $expected
     * @return void
     */
    private function probeCorruptEntry($mode, $expected)
    {
        $command = [PHP_BINARY, '-d', 'error_reporting=-1', '-d', 'display_errors=1', '-d', 'log_errors=0', dirname(__DIR__, 2) . '/tools/archive-probe.php', $mode];
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['redirect', 1]], $pipes);
        $this->assertIsResource($process);
        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $exit = proc_close($process);
        $this->assertSame(0, $exit, $output);
        $this->assertStringContainsString($expected, $output);
        $this->assertDoesNotMatchRegularExpression('/(?:Warning|Notice|Deprecated|Fatal error):/', $output);
    }

    /**
     * Remove only a freshly allocated owned tree, including after source failures.
     *
     * @param string $root
     * @return void
     */
    private function removeTemporaryTree($root)
    {
        if (!is_dir($root)) {
            return;
        }
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($root);
    }
}
