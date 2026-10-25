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
        file_put_contents($prelude, '<?php $GLOBALS["owned_native_caller"] = true;');
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

    /**
     * Keep executed inline code guarded even when a later lint option is present.
     *
     * @dataProvider inlinePrograms
     * @param string $prefix Legal native declaration or namespace prefix.
     * @param string $suffix Closing namespace delimiter when required.
     * @param string $form Native inline option form.
     * @param bool $separator Whether lint is a literal script argument.
     * @return void
     */
    public function testInlineExecutionIsNotCompileOnly($prefix, $suffix, $form, $separator): void
    {
        $code = $prefix . 'echo json_encode(array("arguments" => array_slice($argv,1),'
            . '"reporting" => error_reporting(), "display" => ini_get("display_errors"),'
            . '"startup" => ini_get("display_startup_errors")), JSON_THROW_ON_ERROR);' . $suffix;
        $command = array(PHP_BINARY, '-n', '-d', 'pcre.jit=0', '-d', 'error_reporting=0');
        if ($form === '-r' || $form === '--run') {
            $command[] = $form;
            $command[] = $code;
        } else {
            $command[] = ($form === 'attached-short' ? '-r' : $form) . $code;
        }
        if ($separator) {
            $command[] = '--';
        }
        $command[] = '-l';
        $result = NativeProcess::runCommand($command);
        self::assertSame(0, $result[0], $result[2]);
        self::assertSame('', $result[2]);
        self::assertSame(array('arguments' => $separator ? array('-l') : array(),
            'reporting' => -1, 'display' => 'stderr', 'startup' => '1'),
            json_decode($result[1], true, 512, JSON_THROW_ON_ERROR));
    }

    /**
     * Cover the native option forms and executable namespace/header boundaries.
     *
     * @return array
     */
    public function inlinePrograms(): array
    {
        return array(
            'short separated' => array('', '', '-r', false),
            'long separated' => array('', '', '--run', false),
            'short attached' => array('', '', 'attached-short', false),
            'long attached' => array('', '', '--run=', false),
            'strict declaration' => array('declare(strict_types=1);', '', '-r', false),
            'semicolon namespace' => array('namespace OwnedNativeInline;', '', '-r', false),
            'braced namespace' => array('namespace OwnedNativeInline {', '}', '-r', false),
            'declare before namespace' => array('/* prefix */ declare(strict_types=1); declare(ticks=0); namespace OwnedNativeInline;', '', '-r', false),
            'literal argument boundary' => array('', '', '-r', true)
        );
    }

    /**
     * Preserve the selected native INI caller prelude in the main global scope.
     *
     * @dataProvider iniSelections
     * @param string $form Native PHP configuration selection form.
     * @return void
     */
    public function testConfiguredCallerPrelude($form): void
    {
        $directory = sys_get_temp_dir() . '/native-ini-prelude-' . bin2hex(random_bytes(8));
        mkdir($directory, 0700);
        $program = $directory . '/main.php';
        $prelude = $directory . '/caller.php';
        $ini = $directory . '/php.ini';
        file_put_contents($prelude, '<?php $owned_native_local = "caller-global";');
        file_put_contents($ini, 'auto_prepend_file="' . $prelude . '"' . "\npcre.jit=0\nuser_agent=owned-native-selection\n");
        file_put_contents($program, '<?php echo json_encode(array("caller" => $owned_native_local ?? null,'
            . '"included" => get_included_files(), "agent" => ini_get("user_agent"),'
            . '"script" => $_SERVER["SCRIPT_FILENAME"], "reporting" => error_reporting()), JSON_THROW_ON_ERROR);');
        $selection = $form === 'directory' ? $directory : $ini;
        $command = $form === 'long attached' ? array(PHP_BINARY, '--php-ini=' . $selection)
            : array(PHP_BINARY, '-c', $selection);
        if ($form === 'default' || $form === 'scanned directory') {
            $command = array(PHP_BINARY);
        }
        $environment = getenv();
        if ($form === 'scanned directory') {
            $environment['PHP_INI_SCAN_DIR'] = $directory;
        }
        try {
            // Later scanned INI may override -c; the selected interpreter is the oracle.
            $settings = NativeProcess::runCommand(array_merge($command, array('-r',
                'echo json_encode(array("prelude" => ini_get("auto_prepend_file"),'
                . '"agent" => ini_get("user_agent")), JSON_THROW_ON_ERROR);')),
                $directory, $environment);
            self::assertSame(0, $settings[0], $settings[2]);
            self::assertSame('', $settings[2]);
            $selected = json_decode($settings[1], true, 512, JSON_THROW_ON_ERROR);
            $command[] = $program;
            $result = NativeProcess::runCommand($command, $directory, $environment);
            self::assertSame(0, $result[0], $result[2]);
            self::assertSame('', $result[2]);
            $actual = json_decode($result[1], true, 512, JSON_THROW_ON_ERROR);
            self::assertSame($selected['prelude'] === $prelude ? 'caller-global' : null, $actual['caller']);
            self::assertSame($program, $actual['script']);
            self::assertSame(-1, $actual['reporting']);
            self::assertSame($selected['agent'], $actual['agent']);
            if ($form !== 'default' && $form !== 'scanned directory') {
                self::assertSame('owned-native-selection', $actual['agent']);
            }
            if ($selected['prelude'] !== '' && strcasecmp($selected['prelude'], 'none') !== 0) {
                self::assertContains(realpath($selected['prelude']), $actual['included']);
            }
        } finally {
            unlink($program);
            unlink($prelude);
            unlink($ini);
            rmdir($directory);
        }
    }

    /**
     * Cover explicit, directory, scanned and default native INI selections.
     *
     * @return array
     */
    public function iniSelections(): array
    {
        return array(array('file'), array('long attached'), array('directory'), array('default'), array('scanned directory'));
    }
}
