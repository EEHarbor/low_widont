<?php

namespace LowWidontTests;

final class NativeSettingsOffsetContainer extends \ArrayObject
{
    public static $reporting = array();

    /**
     * Observe the original offset callback reporting context.
     *
     * @param mixed $name
     * @return string
     */
    public function offsetGet($name): string
    {
        self::$reporting[] = error_reporting();
        return 'native-offset';
    }
}

final class NativeSettingsLifetimeTest extends TestCase
{
    /**
     * Retain quiet native NAN carrier bytes before the original parser hook.
     *
     * @return void
     */
    public function testNativeNanCarrierKeepsItsDecodedBytes(): void
    {
        require_once dirname(__DIR__, 3) . '/tests/support/NativeProcess.php';
        $result = $this->runProbe(dirname(__DIR__, 3) . '/tests/tools/settings-native-nan-probe.php', array(
            dirname(__DIR__, 3) . '/low_widont/low_widont.phar'
        ));
        self::assertSame(0, $result[0], $result[1] . $result[2]);
        self::assertSame('', $result[2]);
        self::assertSame(array('decoded' => 'NAM=', 'reporting' => @error_reporting()), json_decode($result[1], true, 512, JSON_THROW_ON_ERROR));
    }

    /**
     * Preserve the original reporting scope for custom offset reads.
     *
     * @return void
     */
    public function testOffsetCallbackKeepsOriginalReportingContext(): void
    {
        require_once dirname(__DIR__, 2) . '/support/Database.php';
        Environment::$ee->db = new Database(array('fieldtypes' => array(array('name' => 'low_widont',
            'settings' => base64_encode(serialize(new NativeSettingsOffsetContainer()))))));
        $settings = new \Low\Widont\FluxCapacitor\Conduit\GlobalFieldSettings();
        NativeSettingsOffsetContainer::$reporting = array();

        try {
            self::assertSame('native-offset', $settings->getSetting('computed'));
            self::assertSame(array(@error_reporting()), NativeSettingsOffsetContainer::$reporting);
        } finally {
            NativeSettingsOffsetContainer::$reporting = array();
        }
    }

    /**
     * Keep native conversion, release, hydration and exceptional query cleanup ordered.
     *
     * @dataProvider temporaryArguments
     * @param string $route
     * @param string $failure
     * @return void
     */
    public function testTemporaryArgumentsKeepNativeReleaseOrder($route, $failure): void
    {
        require_once dirname(__DIR__, 3) . '/tests/support/NativeProcess.php';
        $result = $this->runProbe(dirname(__DIR__, 3) . '/tests/tools/settings-temporary-argument-probe.php', array(
            dirname(__DIR__, 3) . '/low_widont/low_widont.phar', 'Low\\Widont', $route, $failure
        ));
        self::assertSame(0, $result[0], $result[1] . $result[2]);
        self::assertSame('', $result[2]);
        $observed = json_decode($result[1], true, 512, JSON_THROW_ON_ERROR);
        $masked = @error_reporting();
        $events = array(array('convert', $masked), array('destroy', $masked));
        if ($failure === 'none') {
            $outcome = array('ready' => true);
            $events[] = array('hydrate', true, $masked);
        } else {
            $outcome = array('exception' => $failure === 'conversion' ? 'conversion' : 'destructor');
        }
        $events[] = array('query-destroy', -1);
        self::assertSame(array('outcome' => $outcome, 'destroyed' => true, 'events' => $events), $observed);
    }

    /**
     * Cover both native temporary operands and each ordinary callback failure.
     *
     * @return array
     */
    public static function temporaryArguments(): array
    {
        $cases = array();
        foreach (array('raw', 'decoded') as $route) {
            foreach (array('none', 'conversion', 'destructor', 'both') as $failure) {
                $cases[$route . ' ' . $failure] = array($route, $failure);
            }
        }
        return $cases;
    }

    /**
     * Keep native Stringable conversion inside the real parser frame.
     *
     * @return void
     */
    public function testDecodedCallbackKeepsNativeConversionFrame(): void
    {
        require_once dirname(__DIR__, 3) . '/tests/support/NativeProcess.php';
        $result = $this->runProbe(dirname(__DIR__, 3) . '/tests/tools/settings-native-frame-probe.php', array(
            dirname(__DIR__, 3) . '/low_widont/low_widont.phar', 'GlobalFieldSettings'
        ));
        self::assertSame(0, $result[0], $result[1] . $result[2]);
        self::assertSame('', $result[2]);
        self::assertSame(array('value' => array('kept' => 'native'), 'events' => array(
            array('frame' => 'unserialize', 'reporting' => @error_reporting())
        )), json_decode($result[1], true, 512, JSON_THROW_ON_ERROR));
    }

    /**
     * Retain original setter arguments until successful or throwing writes finish.
     *
     * @dataProvider databaseOutcomes
     * @param string $outcome
     * @return void
     */
    public function testReplacementSurvivesDatabaseUpdate($outcome): void
    {
        require_once dirname(__DIR__, 3) . '/tests/support/NativeProcess.php';
        $result = $this->runProbe(dirname(__DIR__, 3) . '/tests/tools/settings-temporary-replacement-probe.php', array(
            dirname(__DIR__, 3) . '/low_widont/low_widont.phar', 'Low\\Widont', $outcome
        ));
        self::assertSame(0, $result[0], $result[1] . $result[2]);
        self::assertSame('', $result[2]);
        $expectedOutcome = $outcome === 'happy' ? array('value' => true) : array('exception_same' => true, 'exception' => 'RuntimeException');
        self::assertSame(array('outcome' => $expectedOutcome, 'events' => array('convert', array(
            0 => 'db-update', 'destroyed' => false, 'value' => 'alc'
        ), 'destroy'), 'destroyed' => true), json_decode($result[1], true, 512, JSON_THROW_ON_ERROR));
    }

    /**
     * Exercise both original database return paths without diagnostic exceptions.
     *
     * @return array
     */
    public static function databaseOutcomes(): array
    {
        return array(array('happy'), array('throw'));
    }

    /**
     * Preserve original opcode binding through actual pinned PHPUnit static restoration.
     *
     * @dataProvider staticRestorationPoints
     * @param string $route
     * @param string $when
     * @return void
     */
    public function testStaticRestorationCannotRebindNativeCallees($route, $when): void
    {
        require_once dirname(__DIR__, 3) . '/tests/support/NativeProcess.php';
        $result = $this->runProbe(dirname(__DIR__, 3) . '/tests/tools/settings-static-restore-probe.php', array(
            dirname(__DIR__, 3) . '/low_widont/low_widont.phar', $route, $when
        ));
        self::assertSame(0, $result[0], $result[1] . $result[2]);
        self::assertSame('', $result[2]);
        self::assertSame(array(7, 7, 7), json_decode($result[1], true, 512, JSON_THROW_ON_ERROR));
    }

    /**
     * Cover unloaded, loaded and separately initialized lexical reader state.
     *
     * @return array
     */
    public static function staticRestorationPoints(): array
    {
        return array(array('settings', 'before-source'), array('settings', 'after-source'),
            array('cache', 'after-source'), array('cache', 'after-other-reader'));
    }
    /**
     * Keep the original public key available to legal serializer callbacks.
     *
     * @dataProvider rawKeyParameters
     * @param string $case
     * @param array $expected
     * @return void
     */
    public function testOriginalKeyParameter($case, array $expected): void
    {
        require_once dirname(__DIR__, 3) . '/tests/support/NativeProcess.php';
        $result = $this->runProbe(dirname(__DIR__, 3) . '/tests/tools/settings-raw-key-probe.php', array(
            dirname(__DIR__, 3) . '/low_widont/low_widont.phar', 'Low\\Widont', $case
        ));
        self::assertSame(0, $result[0], $result[1] . $result[2]);
        self::assertSame('', $result[2]);
        self::assertSame(array($expected), json_decode($result[1], true, 512, JSON_THROW_ON_ERROR));
    }

    /**
     * Cover original fractional and null keys without replacing their parameters.
     *
     * @return array
     */
    public static function rawKeyParameters(): array
    {
        return array(array('fraction', array('double', 1.9)), array('null', array('NULL', null)));
    }

    /**
     * Preserve the original truthy NAN root without a new boolean conversion.
     *
     * @return void
     */
    public function testNativeNanSettingsRoot(): void
    {
        require_once dirname(__DIR__, 2) . '/support/Database.php';
        Environment::$ee->db = new Database(array('fieldtypes' => array(array('name' => 'low_widont', 'settings' => base64_encode('d:NAN;')))));
        $settings = new \Low\Widont\FluxCapacitor\Conduit\GlobalFieldSettings();
        $value = $settings->getSettings();
        self::assertIsFloat($value);
        self::assertTrue(is_nan($value));
    }

    /**
     * Keep raw ArrayAccess key and value temporaries in their original release order.
     *
     * @dataProvider arrayAccessWriteOutcomes
     * @param string $case
     * @return void
     */
    public function testRawArrayAccessArgumentLifetime($case): void
    {
        require_once dirname(__DIR__, 3) . '/tests/support/NativeProcess.php';
        $result = $this->runProbe(dirname(__DIR__, 3) . '/tests/tools/settings-aa-argument-lifetime-probe.php', array(
            dirname(__DIR__, 3) . '/low_widont/low_widont.phar', 'Low\\Widont', $case
        ));
        self::assertSame(0, $result[0], $result[1] . $result[2]);
        self::assertSame('', $result[2]);
        $events = array('assign');
        if ($case !== 'assign-throws') {
            $events[] = 'db-update';
        }
        $events[] = 'key-destroy';
        $events[] = 'value-destroy';
        if ($case !== 'happy') {
            $events[] = 'caught-same';
        }
        self::assertSame($events, json_decode($result[1], true, 512, JSON_THROW_ON_ERROR));
    }

    /**
     * Retain success and both native callback-unwind boundaries.
     *
     * @return array
     */
    public static function arrayAccessWriteOutcomes(): array
    {
        return array(array('happy'), array('assign-throws'), array('db-throws'));
    }

    /**
     * Retain the repository's durable native-process join.
     *
     * @param string $script
     * @param array $arguments
     * @return array
     */
    private function runProbe($script, array $arguments): array
    {
        $directory = sys_get_temp_dir() . '/widont-settings-lifetime-' . bin2hex(random_bytes(8));
        mkdir($directory, 0700);
        $environment = getenv();
        $environment['TMPDIR'] = $directory;
        try {
            return NativeProcess::runCommand(array_merge(array(PHP_BINARY, '-d', 'sys_temp_dir=' . $directory, $script), $arguments), dirname(__DIR__, 3), $environment);
        } finally {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($files as $file) {
                $file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            }
            rmdir($directory);
        }
    }

}
