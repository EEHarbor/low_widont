<?php

namespace LowWidontTests;

use Low\Widont\FluxCapacitor\Conduit\GlobalFieldSettings;
use Low\Widont\FluxCapacitor\Conduit\SerializedValue;

require_once dirname(__DIR__, 2) . '/support/Database.php';

final class FoundationBoundaryTest extends TestCase
{
    /**
     * Preserve legal child methods without adding parent helper declarations.
     *
     * @dataProvider existingCacheMethods
     * @param string $method
     * @param string $visibility
     * @param string $static
     * @return void
     */
    public function testExistingCacheSubclassMethods($method, $visibility, $static): void
    {
        $result = $this->nativeProbe('cache-subclass-probe.php', array(dirname(__DIR__, 3) . '/low_widont/low_widont.phar', $method, $visibility, $static));
        self::assertSame(0, $result[0], $result[2] . $result[1]);
        self::assertSame('', $result[2]);
        self::assertSame(array('value' => 'existing child method'), json_decode($result[1], true));
    }

    /**
     * Keep public, protected and private static and instance child methods legal.
     *
     * @return array
     */
    public static function existingCacheMethods(): array
    {
        $cases = array();
        foreach (array('createDirectory', 'isWritableDirectory') as $method) {
            foreach (array('public', 'protected', 'private') as $visibility) {
                foreach (array('static', 'instance') as $static) {
                    $cases[] = array($method, $visibility, $static);
                }
            }
        }
        return $cases;
    }

    /**
     * Keep legal child method names available in the legacy extensible settings class.
     *
     * @return void
     */
    public function testExistingSettingsSubclassMethod(): void
    {
        $result = $this->nativeProbe('settings-subclass-probe.php', []);
        self::assertSame(0, $result[0], $result[2] . $result[1]);
        self::assertSame('', $result[2]);
        self::assertSame(['value' => 'existing child method'], json_decode($result[1], true));
    }

    /**
     * Preserve native float array-key reads and writes without conversion warnings.
     *
     * @dataProvider floatSettingKeys
     * @param float $key
     * @param int $integer
     * @return void
     */
    public function testNativeFloatSettingKeys($key, $integer): void
    {
        $stored = array($integer => 'native', 'keep' => 'retained');
        Environment::$ee->db = new Database(['fieldtypes' => [['name' => 'low_widont', 'settings' => base64_encode(serialize($stored))]]]);
        $settings = new GlobalFieldSettings();
        self::assertSame('native', $settings->getSetting($key));
        self::assertSame(serialize($stored), serialize($settings->getSettings()));
        self::assertTrue($settings->updateSetting($key, 'changed'));
        $written = Environment::$ee->db->tables['fieldtypes'][0]['settings'];
        self::assertSame(serialize(array($integer => 'changed', 'keep' => 'retained')), base64_decode($written));
    }

    /**
     * Include native fractional, nonfinite and integer-width wrapping keys.
     *
     * @return array
     */
    public static function floatSettingKeys(): array
    {
        $width = 2.0 ** (PHP_INT_SIZE * 8);
        return array(
            array(1.9, 1), array(-1.9, -1), array(INF, 0), array(-INF, 0), array(NAN, 0),
            array(1e100, 0), array(-1e100, 0), array($width / 2, PHP_INT_MIN),
            array($width + 4096.0, 4096), array(-$width - 4096.0, -4096),
        );
    }

    /**
     * Keep the native first byte when writing a longer string to a stored offset.
     *
     * @dataProvider stringOffsetValues
     * @param string $value
     * @param string $expected
     * @return void
     */
    public function testNativeStringOffsetWrite($value, $expected): void
    {
        $stored = 'abc';
        Environment::$ee->db = new Database(['fieldtypes' => [['name' => 'low_widont', 'settings' => base64_encode(serialize($stored))]]]);
        self::assertTrue((new GlobalFieldSettings())->updateSetting(1, $value));
        $written = Environment::$ee->db->tables['fieldtypes'][0]['settings'];
        self::assertSame(serialize($expected), base64_decode($written));
    }

    /**
     * Retain byte semantics for ordinary text, UTF-8 and embedded null bytes.
     *
     * @return array
     */
    public static function stringOffsetValues(): array
    {
        return array(array('long', 'alc'), array("\xc3\xa9", "a\xc3c"), array("\0tail", "a\0c"));
    }

    /**
     * Retain native queued callbacks after malformed or oversized later tokens.
     *
     * @dataProvider malformedTails
     * @param string $tail
     * @return void
     */
    public function testMalformedTailCallbacks($tail): void
    {
        $before = set_error_handler(static function () {});
        restore_error_handler();
        $stored = 'a:3:{i:0;S:1:"\41";i:1;' . serialize(new DeferredSettingsCallback()) . 'i:2;' . $tail . '}';
        try {
            SerializedValue::decode($stored);
            self::fail('The native callback exception must propagate.');
        } catch (\RuntimeException $exception) {
            self::assertSame('owned deferred callback', $exception->getMessage());
        }
        $after = set_error_handler(static function () {});
        restore_error_handler();
        self::assertSame($before, $after);
    }

    /**
     * Cover ordinary, escaped, oversized and negative custom payload boundaries.
     *
     * @return array
     */
    public function malformedTails(): array
    {
        $huge = str_repeat('9', 30);
        return [
            ['S:9:"bad";'], ['S:1:"\zz";'], ['s:9:"bad S:";'], ['!S:'], ['C:1:"X":-999:{S:}'],
            ['S:' . $huge . ':"x";'], ['s:' . $huge . ':"x";'], ['O:' . $huge . ':"X":0:{}'],
            ['E:' . $huge . ':"X:Y";'], ['C:' . $huge . ':"X":0:{}'],
            ['C:1:"X":' . $huge . ':{S:}'], ['C:1:"X":-' . $huge . ':{S:}'],
        ];
    }

    /**
     * Keep valid padded escaped lengths, signed object counts and references.
     *
     * @return void
     */
    public function testLegacyLengthAndReferenceSpellings(): void
    {
        $zeros = str_repeat('0', 40);
        self::assertSame(['B', 'A'], SerializedValue::decode('a:2:{i:0;s:' . $zeros . '1:"B";i:1;S:' . $zeros . '1:"\41";}'));
        $decoded = SerializedValue::decode('a:2:{i:0;O:08:"stdClass":+01:{s:1:"x";S:1:"\41";}i:1;r:2;}');
        self::assertSame($decoded[0], $decoded[1]);
        self::assertSame('A', $decoded[0]->x);
        $decoded = SerializedValue::decode('a:2:{s:1:"x";S:1:"\41";s:1:"y";R:02;}');
        $decoded['x'] = 'changed';
        self::assertSame('changed', $decoded['y']);
    }

    /**
     * Preserve native settings roots and clean native string offset reads.
     *
     * @dataProvider nativeRoots
     * @param mixed $stored
     * @param mixed $key
     * @param mixed $expected
     * @return void
     */
    public function testNativeRootLookups($stored, $key, $expected): void
    {
        Environment::$ee->db = new Database(['fieldtypes' => [['name' => 'low_widont', 'settings' => base64_encode(serialize($stored))]]]);
        $settings = new GlobalFieldSettings();
        self::assertSame($expected, $settings->getSetting($key));
        self::assertSame(serialize($stored), serialize($settings->getSettings()));
    }

    /**
     * Cover valid scalar offsets, missing keys and non-indexable stored roots.
     *
     * @return array
     */
    public function nativeRoots(): array
    {
        return [
            ['abc', 0, 'a'], ['abc', '0', 'a'], ['abc', 1, 'b'], ['abc', -1, 'c'], ['abc', '+1', 'b'],
            ['abc', '01', 'b'], ['abc', 99, null], ['abc', 'missing', null], ['abc', [], null],
            [7, 'missing', null], [true, 'missing', null], [new \stdClass(), 'missing', null],
            [[1 => 'value'], true, 'value'], [['' => 'value'], null, 'value'],
            [[1 => 'value'], 1.0, 'value'], [[1 => 'value'], 1.2, 'value'],
        ];
    }

    /**
     * Keep computed ArrayAccess reads and native string writes in stored settings.
     *
     * @return void
     */
    public function testStoredCarrierWrites(): void
    {
        Environment::$ee->db = new Database(['fieldtypes' => [['name' => 'low_widont', 'settings' => base64_encode(serialize(new ComputedSettingsCarrier(['keep' => 7])))]]]);
        $settings = new GlobalFieldSettings();
        self::assertSame('computed', $settings->getSetting('missing'));
        self::assertTrue($settings->updateSetting('nested', ['object' => (object) ['name' => 'retained']]));
        self::assertSame(7, $settings->getSetting('keep'));
        self::assertInstanceOf(\stdClass::class, $settings->getSetting('nested')['object']);
        Environment::$ee->db->tables['fieldtypes'][0]['settings'] = base64_encode(serialize('abc'));
        self::assertTrue($settings->updateSetting(1, 'Z'));
        self::assertSame(base64_encode(serialize('aZc')), Environment::$ee->db->tables['fieldtypes'][0]['settings']);
        self::assertSame('Z', $settings->getSetting(1));
    }

    /**
     * Retain native numeric clamps through settings reads and subsequent writes.
     *
     * @dataProvider numericClamps
     * @param string $number
     * @param int $expected
     * @return void
     */
    public function testNativeNumericClamps($number, $expected): void
    {
        $stored = 'a:2:{s:4:"keep";i:7;s:5:"value";i:' . $number . ';}';
        Environment::$ee->db = new Database(['fieldtypes' => [['name' => 'low_widont', 'settings' => base64_encode($stored)]]]);
        $settings = new GlobalFieldSettings();
        self::assertSame(['keep' => 7, 'value' => $expected], $settings->getSettings());
        self::assertSame(base64_encode($stored), Environment::$ee->db->tables['fieldtypes'][0]['settings']);
        self::assertTrue($settings->updateSetting('next', 9));
        self::assertSame(['keep' => 7, 'value' => $expected, 'next' => 9], $settings->getSettings());
    }

    /**
     * Include both native saturation directions.
     *
     * @return array
     */
    public function numericClamps(): array
    {
        return [[str_repeat('9', 30), PHP_INT_MAX], ['-' . str_repeat('9', 30), PHP_INT_MIN]];
    }

    /**
     * Retain extraction targets and hidden files across actual go and cleanup.
     *
     * @dataProvider extractionLinks
     * @param string $mode
     * @param string $location
     * @return void
     */
    public function testExtractionLinks($mode, $location): void
    {
        $result = $this->nativeProbe('extraction-link-probe.php', [$mode, $location]);
        self::assertSame(0, $result[0], $result[2] . $result[1]);
        self::assertSame('', $result[2]);
        self::assertSame(['blocked' => $mode !== 'cleanup', 'sentinel' => 'retained', 'link' => $mode !== 'cleanup', 'cwd_preserved' => true, 'target_removed' => true, 'sibling_preserved' => true], json_decode($result[1], true));
    }

    /**
     * Cover automatic/manual extraction and native cleanup separator behavior.
     *
     * @return array
     */
    public function extractionLinks(): array
    {
        return [['go', 'root'], ['go', 'container'], ['fallback', 'root'], ['fallback', 'container'], ['cleanup', 'root'], ['cleanup', 'dangling'], ['cleanup', 'trailing'], ['cleanup', 'backslash']];
    }

    /**
     * Log a false cache result when writable POSIX paths cannot be searched.
     *
     * @dataProvider cacheSearchPaths
     * @param string $mode
     * @return void
     */
    public function testNonsearchableCachePaths($mode): void
    {
        $result = $this->nativeProbe('cache-search-probe.php', [$mode]);
        self::assertSame(0, $result[0], $result[2] . $result[1]);
        self::assertSame('', $result[2]);
        self::assertSame(['writable' => true, 'searchable' => false, 'value' => false, 'logs' => 1, 'cached' => false], json_decode($result[1], true));
    }

    /**
     * Include base creation, module creation and an existing module.
     *
     * @return array
     */
    public function cacheSearchPaths(): array
    {
        return [['base'], ['module'], ['existing-module']];
    }

    /**
     * Restore the explicit caller directory after unlinking a stale extraction root.
     *
     * @dataProvider cleanupDirectories
     * @param string $kind
     * @return void
     */
    public function testCleanupRestoresSuppliedDirectory($kind): void
    {
        $result = $this->nativeProbe('cleanup-directory-probe.php', [$kind]);
        self::assertSame(0, $result[0], $result[2] . $result[1]);
        self::assertSame('', $result[2]);
        self::assertSame(['restored' => true, 'removed' => true, 'retained' => 'unchanged'], json_decode($result[1], true));
    }

    /**
     * Cover existing, dangling and separator-suffixed root links.
     *
     * @return array
     */
    public function cleanupDirectories(): array
    {
        return [['existing'], ['dangling'], ['trailing']];
    }

    /**
     * Load the Foundation fallback when its optional override cannot be read.
     *
     * @dataProvider optionalOverrides
     * @param string $state
     * @return void
     */
    public function testOptionalNavigationOverride($state): void
    {
        $result = $this->nativeProbe('optional-nav-probe.php', [$state]);
        self::assertSame(0, $result[0], $result[2] . $result[1]);
        self::assertSame('', $result[2]);
        self::assertSame(['loaded' => true, 'override' => $state === 'readable'], json_decode($result[1], true));
    }

    /**
     * Include the normal extension and both optional fallback boundaries.
     *
     * @return array
     */
    public function optionalOverrides(): array
    {
        return [['readable'], ['missing'], ['unreadable']];
    }

    /**
     * Give filesystem probes a private context and remove only its owned files.
     *
     * @param string $script
     * @param array $arguments
     * @return array
     * @throws \RuntimeException
     */
    private function nativeProbe($script, array $arguments): array
    {
        $directory = sys_get_temp_dir() . '/low-widont-boundary-' . bin2hex(random_bytes(8));
        mkdir($directory, 0700);
        $environment = getenv();
        $environment['TMPDIR'] = $directory;
        try {
            return NativeProcess::runCommand(array_merge([PHP_BINARY, '-d', 'sys_temp_dir=' . $directory, dirname(__DIR__, 2) . '/tools/' . $script], $arguments), null, $environment);
        } finally {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $file) {
                if ($file->isLink() || !$file->isDir()) {
                    unlink($file->getPathname());
                } else {
                    rmdir($file->getPathname());
                }
            }
            rmdir($directory);
        }
    }
}

class DeferredSettingsCallback
{
    /**
     * Demonstrate native callback finalization after a later parser error.
     *
     * @return void
     * @throws \RuntimeException
     */
    public function __wakeup(): void
    {
        throw new \RuntimeException('owned deferred callback');
    }
}

class ComputedSettingsCarrier extends \ArrayObject
{
    /**
     * Return a computed value even when offsetExists would be false.
     *
     * @param mixed $key
     * @return mixed
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($key)
    {
        return parent::offsetExists($key) ? parent::offsetGet($key) : 'computed';
    }
}
