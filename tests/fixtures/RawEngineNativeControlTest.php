<?php

namespace LowWidontTests;

final class RawEngineNativeControlTest extends TestCase
{
    /**
     * Retain raw child framework buffers while preserving its original green result.
     *
     * @return void
     */
    public function testRawChildOutput(): void
    {
        $root = dirname(__DIR__, 2);
        if (getenv('LOW_WIDONT_RAW_ENGINE_MODE') === 'argv-literal') {
            $path = $root . '/tests/.phpunit/phpunit-9.phar';
            $result = NativeProcess::runCommand(array(PHP_BINARY, '-r', 'echo json_encode($argv[1]);', '--', $path));
            $this->assertSame(0, $result[0]);
            $this->assertSame($path, json_decode($result[1], true, 512, JSON_THROW_ON_ERROR));
            $this->assertSame('', $result[2]);

            return;
        }
        $result = NativeProcess::runCommand(array(PHP_BINARY, $root . '/tests/.phpunit/phpunit-9.phar',
            '--no-configuration', '--bootstrap', $root . '/tests/bootstrap.php', '--do-not-cache-result',
            '--log-junit', getenv('LOW_WIDONT_RAW_CHILD_JUNIT'), __DIR__ . '/RawEngineVisibilityControlTest.php'));
        $this->assertSame(0, $result[0]);
        $this->assertSame('', $result[2]);
    }
}
