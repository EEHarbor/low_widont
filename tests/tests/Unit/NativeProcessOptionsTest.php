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
        $programs = array(
            'empty braced declaration' => array('declare(ticks=1) {} namespace OwnedInline;', '', '-r', false),
            'empty nested declaration' => array('declare(ticks=1) { declare(ticks=1) {} } namespace OwnedInline;', '', '-r', false),
            'empty semicolon body' => array('declare(ticks=1) { ; } namespace OwnedInline;', '', '-r', false),
            'empty colon declaration' => array('declare(ticks=1): enddeclare; namespace OwnedInline;', '', '-r', false),
            'live braced body' => array('declare(ticks=1) { echo ""; }', '', '-r', false),
            'live colon body' => array('declare(ticks=1): echo ""; enddeclare;', '', '-r', false),
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
        if (PHP_VERSION_ID >= 80000) {
            $programs['live braced declaration before namespace'] = array('declare(ticks=1) { echo ""; } namespace OwnedInline;', '', '-r', false);
            $programs['live colon declaration before namespace'] = array('declare(ticks=1): echo ""; enddeclare; namespace OwnedInline;', '', '-r', false);
        }
        return $programs;
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

    /**
     * Keep an already selected authority file single-loaded by native file identity.
     *
     * @dataProvider authorityPreludes
     * @param bool $alias Whether the selected path includes a normalized directory alias.
     * @return void
     */
    public function testSelectedAuthorityPrelude($alias): void
    {
        $authority = dirname(__DIR__, 2) . '/support/' . ($alias ? '../support/' : '') . 'Diagnostics.php';
        $program = sys_get_temp_dir() . '/native-authority-main-' . bin2hex(random_bytes(8)) . '.php';
        $archive = dirname(__DIR__, 3) . '/low_widont/low_widont.phar';
        file_put_contents($program, '<?php require ' . var_export('phar://' . $archive . '/FluxCapacitor/Conduit/StaticCache.php', true)
            . ';\\Low\\Widont\\FluxCapacitor\\Conduit\\StaticCache::set("authority-prelude", "native");'
            . 'echo \\Low\\Widont\\FluxCapacitor\\Conduit\\StaticCache::get("authority-prelude");');
        try {
            $result = NativeProcess::runCommand(array(PHP_BINARY, '-n', '-d', 'pcre.jit=0',
                '-d', 'auto_prepend_file=' . $authority, $program));
            self::assertSame(0, $result[0], $result[2]);
            self::assertSame('', $result[2]);
            self::assertSame('native', $result[1]);
        } finally {
            unlink($program);
        }
    }

    /**
     * Cover direct and equivalent native authority paths.
     *
     * @return array
     */
    public function authorityPreludes(): array
    {
        return array('exact' => array(false), 'normalized alias' => array(true));
    }

    /**
     * Retain owned startup for valid inline programs without live statements.
     *
     * @dataProvider emptyInlinePrograms
     * @param string $code Native empty program or legal declaration-only header.
     * @return void
     */
    public function testEmptyInlineProgram($code): void
    {
        $result = NativeProcess::runCommand(array(PHP_BINARY, '-n', '-d', 'pcre.jit=0', '-r', $code));
        self::assertSame(0, $result[0], $result[2]);
        self::assertSame('', $result[1]);
        self::assertSame('', $result[2]);
    }

    /**
     * Cover empty native input and headers with no later executable statement.
     *
     * @return array
     */
    public function emptyInlinePrograms(): array
    {
        return array(array(''), array('declare(ticks=1) {}'), array('namespace OwnedEmpty;'));
    }

    /**
     * Preserve interpolated text while guarding the surrounding declaration body.
     *
     * @dataProvider interpolatedDeclarationTexts
     * @param string $tail Literal brace text after the interpolated native value.
     * @return void
     */
    public function testInterpolatedDeclarationText($tail): void
    {
        $code = 'declare(ticks=1) { $number = 1; $stored = "{$number}' . $tail . '"; }';
        if (PHP_VERSION_ID >= 80000) {
            $code .= 'namespace OwnedInterpolated;';
        }
        $archive = dirname(__DIR__, 3) . '/low_widont/low_widont.phar';
        $code .= 'require ' . var_export('phar://' . $archive . '/FluxCapacitor/Conduit/StaticCache.php', true)
            . ';\\Low\\Widont\\FluxCapacitor\\Conduit\\StaticCache::set("declaration-text", $stored);'
            . 'echo \\Low\\Widont\\FluxCapacitor\\Conduit\\StaticCache::get("declaration-text");';
        $result = NativeProcess::runCommand(array(PHP_BINARY, '-n', '-d', 'pcre.jit=0', '-r', $code));
        self::assertSame(0, $result[0], $result[2]);
        self::assertSame('', $result[2]);
        self::assertSame('1' . $tail, $result[1]);
    }

    /**
     * Keep interpolated text tokens distinct from opening and closing punctuation.
     *
     * @return array
     */
    public function interpolatedDeclarationTexts(): array
    {
        return array(array('{'), array('}'));
    }

    /**
     * Preserve native tick callbacks after the first unconditional authority import.
     *
     * @dataProvider tickScopes
     * @param bool $laterHeader Whether a following declaration enables global ticks.
     * @return void
     */
    public function testTickCallbacksKeepTheirNativeCount($laterHeader): void
    {
        $register = '$observed=0; $record=static function()use(&$observed){$observed++;}; register_tick_function($record);';
        $work = '$work=7; unregister_tick_function($record);';
        $code = $laterHeader ? 'declare(ticks=1){' . $register . '}declare(ticks=1);' . $work
            : 'declare(ticks=1){' . $register . $work . '}';
        $archive = dirname(__DIR__, 3) . '/low_widont/low_widont.phar';
        $code .= 'require ' . var_export('phar://' . $archive . '/FluxCapacitor/Conduit/StaticCache.php', true)
            . ';\\Low\\Widont\\FluxCapacitor\\Conduit\\StaticCache::set("native-tick-count", $observed);'
            . 'echo \\Low\\Widont\\FluxCapacitor\\Conduit\\StaticCache::get("native-tick-count");';
        $result = NativeProcess::runCommand(array(PHP_BINARY, '-n', '-d', 'pcre.jit=0', '-r', $code));
        self::assertSame(0, $result[0], $result[2]);
        self::assertSame('', $result[2]);
        self::assertSame('2', $result[1]);
    }

    /**
     * Cover one body and a later native tick declaration on the same live path.
     *
     * @return array
     */
    public function tickScopes(): array
    {
        return array('one body' => array(false), 'later global ticks' => array(true));
    }

    /**
     * Establish authority in begin code without adding tick work to the end program.
     *
     * @dataProvider beginEndForms
     * @param bool $long Whether the native options use their long names.
     * @param bool $reversed Whether the end option appears before begin in argv.
     * @return void
     */
    public function testBeginProgramKeepsEndTickCount($long, $reversed): void
    {
        $begin = '$observed=0; $record=static function()use(&$observed){$observed++;}; register_tick_function($record);';
        $archive = dirname(__DIR__, 3) . '/low_widont/low_widont.phar';
        $end = 'declare(ticks=1); $work=7; unregister_tick_function($record); require '
            . var_export('phar://' . $archive . '/FluxCapacitor/Conduit/StaticCache.php', true)
            . ';\\Low\\Widont\\FluxCapacitor\\Conduit\\StaticCache::set("program-ticks", $observed);'
            . 'echo \\Low\\Widont\\FluxCapacitor\\Conduit\\StaticCache::get("program-ticks");';
        $first = array($long ? '--process-begin' : '-B', $begin);
        $last = array($long ? '--process-end' : '-E', $end);
        $command = array_merge(array(PHP_BINARY, '-n', '-d', 'pcre.jit=0'),
            $reversed ? array_merge($last, $first) : array_merge($first, $last));
        $result = NativeProcess::runCommand($command);
        self::assertSame(0, $result[0], $result[2]);
        self::assertSame('', $result[2]);
        self::assertSame('2', $result[1]);
    }

    /**
     * Retain native execution order for the known short and long begin/end forms.
     *
     * @return array
     */
    public function beginEndForms(): array
    {
        return array('short' => array(false, false), 'long' => array(true, false),
            'short reversed' => array(false, true), 'long reversed' => array(true, true));
    }
}
