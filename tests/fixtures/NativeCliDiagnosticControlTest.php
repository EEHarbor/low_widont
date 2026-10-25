<?php

namespace LowWidontTests;

require_once dirname(__DIR__) . '/tests/Unit/TestCase.php';

final class NativeCliDiagnosticControlTest extends TestCase
{
    /**
     * Keep ignored caught child diagnostics red at the checked invocation boundary.
     *
     * @return void
     * @throws \RuntimeException When the requested control mode is unknown.
     */
    public function testNativeAuthority(): void
    {
        $mode = getenv('LOW_WIDONT_NATIVE_CLI_CONTROL_MODE');
        $archive = dirname(__DIR__, 2) . '/low_widont/low_widont.phar';
        $load = 'require ' . var_export('phar://' . $archive . '/FluxCapacitor/Conduit/StaticCache.php', true)
            . ';\\Low\\Widont\\FluxCapacitor\\Conduit\\StaticCache::set("owned-cli-control", "native");';
        $fault = 'try { @trigger_error("owned native CLI caught diagnostic", E_USER_DEPRECATED); } catch (\\Throwable $error) {}';
        if (in_array($mode, array('short', 'long', 'namespace', 'brace', 'declare-brace', 'declare-colon'), true)) {
            $code = $load . $fault;
            if ($mode === 'namespace') {
                $code = 'declare(strict_types=1); namespace OwnedCliControl;' . $code;
            }
            if ($mode === 'brace') {
                $code = 'namespace OwnedCliControl {' . $code . '}';
            }
            if ($mode === 'declare-brace' || $mode === 'declare-colon') {
                $code = $mode === 'declare-brace' ? 'declare(ticks=1) {' . $code . '}'
                    : 'declare(ticks=1):' . $code . 'enddeclare;';
                if (PHP_VERSION_ID >= 80000) {
                    $code .= 'namespace OwnedCliControl;';
                }
            }
            $command = array(PHP_BINARY, '-n', '-d', 'pcre.jit=0');
            if ($mode === 'long') {
                $command[] = '--run=' . $code;
            } else {
                $command[] = '-r';
                $command[] = $code;
            }
            $command[] = '-l';
            NativeProcess::runCommand($command);
            self::assertTrue(true);
            return;
        }
        if (!in_array($mode, array('prelude-caught', 'prelude-healthy'), true)) {
            throw new \RuntimeException('Unknown owned native CLI control.');
        }
        $directory = sys_get_temp_dir() . '/owned-native-cli-' . bin2hex(random_bytes(8));
        mkdir($directory, 0700);
        $prelude = $directory . '/caller.php';
        $program = $directory . '/main.php';
        file_put_contents($prelude, '<?php $owned_cli_local = "caller-global";'
            . ($mode === 'prelude-caught' ? $fault : ''));
        file_put_contents($program, '<?php ' . $load . 'echo $owned_cli_local;');
        try {
            $result = NativeProcess::runCommand(array(PHP_BINARY, '-n', '-d', 'pcre.jit=0',
                '-d', 'auto_prepend_file=' . $prelude, $program), $directory);
            if ($mode === 'prelude-healthy') {
                self::assertSame(0, $result[0], $result[2]);
                self::assertSame('caller-global', $result[1]);
                return;
            }
            self::assertTrue(true);
        } finally {
            unlink($prelude);
            unlink($program);
            rmdir($directory);
        }
    }
}
