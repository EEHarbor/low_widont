<?php

namespace LowWidontTests;

final class NativeProcessOptionsTest extends TestCase
{
    /**
     * Retain actual script arguments and caller prelude while enforcing startup settings.
     *
     * @dataProvider commandForms
     * @param array $options
     * @param bool $explicitFile
     * @param bool $separator
     * @param bool $callerPrelude
     * @return void
     */
    public function testNativeOptionsKeepScriptArguments(array $options, $explicitFile, $separator, $callerPrelude): void
    {
        $directory = sys_get_temp_dir() . '/native-options-' . bin2hex(random_bytes(8));
        mkdir($directory, 0700);
        $program = $directory . '/args.php';
        $prelude = $directory . '/caller.php';
        file_put_contents($program, <<<'CODE'
<?php
 echo json_encode(array('arguments' => array_slice($argv, 1), 'reporting' => error_reporting(),
    'display' => ini_get('display_errors'), 'startup' => ini_get('display_startup_errors'),
    'caller' => $GLOBALS['owned_native_caller'] ?? false), JSON_THROW_ON_ERROR);
CODE
        );
        $strict = dirname(__DIR__, 2) . '/support/Diagnostics.php';
        file_put_contents($prelude, '<?php require_once ' . var_export($strict, true) . '; $GLOBALS["owned_native_caller"] = true;');
        $command = array_merge(array(PHP_BINARY, '-d', 'pcre.jit=0'), $options);
        if ($callerPrelude) {
            $command = array_merge($command, array('-d', 'auto_prepend_file=' . $prelude));
        }
        if ($explicitFile) {
            $command[] = '-f';
        }
        $command[] = $program;
        if ($separator) {
            $command[] = '--';
        }
        $command[] = '-l';
        try {
            $result = NativeProcess::runCommand($command);
            self::assertSame(0, $result[0], $result[2]);
            self::assertSame('', $result[2]);
            $observed = json_decode($result[1], true, 512, JSON_THROW_ON_ERROR);
            self::assertSame(array('arguments' => $separator && !$explicitFile ? array('--', '-l') : array('-l'),
                'reporting' => -1, 'display' => 'stderr', 'startup' => '1', 'caller' => $callerPrelude), $observed);
        } finally {
            unlink($program);
            unlink($prelude);
            rmdir($directory);
        }
    }

    /**
     * Cover positional and explicit files, both define forms and a caller prelude.
     *
     * @return array
     */
    public function commandForms(): array
    {
        return array(
            'positional file' => array(array(), false, false, false),
            'literal argument separator' => array(array(), false, true, false),
            'explicit file' => array(array('-n'), true, true, false),
            'separated define' => array(array('-n', '-d', 'error_reporting=0'), false, false, false),
            'attached define' => array(array('-n', '-derror_reporting=0'), false, false, false),
            'long define' => array(array('-n', '--define=error_reporting=0'), false, false, false),
            'caller prelude' => array(array('-n', '-d', 'error_reporting=0'), false, false, true)
        );
    }

    /**
     * Preserve healthy compile-only execution after enforcing diagnostic settings.
     *
     * @return void
     */
    public function testQuietNativeLint(): void
    {
        $program = tempnam(sys_get_temp_dir(), 'native-lint-');
        file_put_contents($program, '<?php echo "not executed by lint";');
        try {
            $result = NativeProcess::runCommand(array(PHP_BINARY, '-n', '-d', 'pcre.jit=0', '-d', 'error_reporting=0', '-l', $program));
            self::assertSame(0, $result[0], $result[2]);
            self::assertSame('', $result[2]);
            self::assertStringContainsString('No syntax errors detected in ' . $program, $result[1]);
        } finally {
            unlink($program);
        }
    }
}
