<?php

namespace LowWidontTests;

use Low\Widont\FluxCapacitor\Conduit\GlobalFieldSettings;
use Low\Widont\FluxCapacitor\Conduit\SerializedValue;

require_once dirname(__DIR__, 2) . '/support/Database.php';

final class FoundationBoundaryTest extends TestCase
{
    /**
     * Preserve native first-resolution lifetime, ordering and inherited callers.
     *
     * @dataProvider nativeCalleeLifetimes
     * @param string $route
     * @param string $scenario
     * @param string $expected
     * @return void
     */
    public function testNativeCalleeLifetime($route, $scenario, $expected): void
    {
        $result = $this->nativeProbe('callee-lifetime-probe.php', array(
            dirname(__DIR__, 3) . '/low_widont/low_widont.phar', 'Low\\Widont', $scenario, $route
        ));
        self::assertSame(0, $result[0], $result[2] . $result[1]);
        self::assertSame('', $result[2]);
        self::assertSame(json_decode(base64_decode($expected), true), json_decode($result[1], true));
    }

    /**
     * Retain the actual pinned original's positive query and file-helper contracts.
     *
     * @return array
     */
    public static function nativeCalleeLifetimes(): array
    {
        return array(
            array('settings', 'between', 'eyJpdGVtcyI6W3siZmlyc3QiOjd9LHsic2Vjb25kIjo3fSx7ImluaGVyaXRlZCI6N31dLCJvdXRlcl9jYWxscyI6MCwiaW5uZXJfY2FsbHMiOjB9'),
            array('settings', 'row', 'eyJpdGVtcyI6W3siZmlyc3QiOjd9LHsic2Vjb25kIjo3fSx7ImluaGVyaXRlZCI6N31dLCJvdXRlcl9jYWxscyI6MCwiaW5uZXJfY2FsbHMiOjB9'),
            array('settings', 'no-row', 'eyJpdGVtcyI6W3siZmlyc3QiOm51bGx9LHsic2Vjb25kIjoib3V0ZXItaG9vayJ9LHsiaW5oZXJpdGVkIjoib3V0ZXItaG9vayJ9XSwib3V0ZXJfY2FsbHMiOjIsImlubmVyX2NhbGxzIjoyfQ=='),
            array('settings', 'row-throws', 'eyJpdGVtcyI6W3siZmlyc3QiOiJvd25lZC1yb3ctdGhyb3cifSx7InNlY29uZCI6N30seyJpbmhlcml0ZWQiOjd9XSwib3V0ZXJfY2FsbHMiOjAsImlubmVyX2NhbGxzIjowfQ=='),
            array('settings', 'num-rows', 'eyJpdGVtcyI6W3siZmlyc3QiOiJvdXRlci1ob29rIn0seyJzZWNvbmQiOiJvdXRlci1ob29rIn0seyJpbmhlcml0ZWQiOiJvdXRlci1ob29rIn1dLCJvdXRlcl9jYWxscyI6MywiaW5uZXJfY2FsbHMiOjN9'),
            array('settings', 'inner-registers-outer', 'eyJpdGVtcyI6W3siZmlyc3QiOjd9LHsic2Vjb25kIjo3fSx7ImluaGVyaXRlZCI6N31dLCJvdXRlcl9jYWxscyI6MCwiaW5uZXJfY2FsbHMiOjN9'),
            array('settings', 'outer-only-row-inner', 'eyJpdGVtcyI6W3siZmlyc3QiOiJvdXRlci1ob29rIn0seyJzZWNvbmQiOiJvdXRlci1ob29rIn0seyJpbmhlcml0ZWQiOiJvdXRlci1ob29rIn1dLCJvdXRlcl9jYWxscyI6MywiaW5uZXJfY2FsbHMiOjB9'),
            array('settings', 'child-first', 'eyJpdGVtcyI6W3siZmlyc3QiOjd9LHsic2Vjb25kIjo3fSx7ImluaGVyaXRlZCI6N31dLCJvdXRlcl9jYWxscyI6MCwiaW5uZXJfY2FsbHMiOjB9'),
            array('settings', 'recursive', 'eyJpdGVtcyI6W3sicmVjdXJzaXZlIjo3fSx7ImZpcnN0Ijo3fSx7InNlY29uZCI6N30seyJpbmhlcml0ZWQiOjd9XSwib3V0ZXJfY2FsbHMiOjAsImlubmVyX2NhbGxzIjowfQ=='),
            array('settings', 'preexisting', 'eyJpdGVtcyI6W3siZmlyc3QiOiJvdXRlci1ob29rIn0seyJzZWNvbmQiOiJvdXRlci1ob29rIn0seyJpbmhlcml0ZWQiOiJvdXRlci1ob29rIn1dLCJvdXRlcl9jYWxscyI6MywiaW5uZXJfY2FsbHMiOjN9'),
            array('cache', 'between', 'eyJpdGVtcyI6W3siZmlyc3QiOjd9LHsic2Vjb25kIjo3fV0sIm91dGVyX2NhbGxzIjowLCJpbm5lcl9jYWxscyI6MH0='),
            array('cache', 'read-registers', 'eyJpdGVtcyI6W3siZmlyc3QiOiJvdXRlci1ob29rIn0seyJzZWNvbmQiOiJvdXRlci1ob29rIn1dLCJvdXRlcl9jYWxscyI6MiwiaW5uZXJfY2FsbHMiOjB9'),
            array('cache', 'read-missing', 'eyJpdGVtcyI6W3siZmlyc3QiOmZhbHNlfSx7InNlY29uZCI6Im91dGVyLWhvb2sifV0sIm91dGVyX2NhbGxzIjoxLCJpbm5lcl9jYWxscyI6MH0='),
            array('cache', 'preexisting', 'eyJpdGVtcyI6W3siZmlyc3QiOiJvdXRlci1ob29rIn0seyJzZWNvbmQiOiJvdXRlci1ob29rIn1dLCJvdXRlcl9jYWxscyI6MiwiaW5uZXJfY2FsbHMiOjB9')
        );
    }

    /**
     * Coerce native parser inputs once after preserving original host hook dispatch.
     *
     * @dataProvider decodedStringableValues
     * @param string $route
     * @param string $kind
     * @param string|null $expected
     * @return void
     */
    public function testNativeDecodedStringable($route, $kind, $expected): void
    {
        $result = $this->nativeProbe('decoded-stringable-probe.php', array($route, $kind));
        self::assertSame(0, $result[0], $result[2] . $result[1]);
        self::assertSame('', $result[2]);
        self::assertSame(array('wire' => $expected, 'calls' => 1, 'same_exception' => $kind === 'throw', 'carrier_same' => true), json_decode($result[1], true));
    }

    /**
     * Include both public parser callers, legacy escaped text, native roots and throws.
     *
     * @return array
     */
    public static function decodedStringableValues(): array
    {
        $rows = array();
        foreach (array('settings', 'cache') as $route) {
            $rows[] = array($route, 'array', serialize(array('keep' => 7)));
            $rows[] = array($route, 'legacy-S', serialize('abc'));
            $rows[] = array($route, 'prefix', serialize(array('keep' => 7)));
            $rows[] = array($route, 'object', serialize((object) array('value' => 'native')));
            $rows[] = array($route, 'throw', null);
        }
        return $rows;
    }

    /**
     * Preserve raw base64 hook input before native-only escaped string handling.
     *
     * @return void
     */
    public function testOriginalBase64HookBeforeNativeParser(): void
    {
        $result = $this->nativeProbe('settings-base64-hook-probe.php', []);
        self::assertSame(0, $result[0], $result[2] . $result[1]);
        self::assertSame('', $result[2]);
        self::assertSame(array('value' => 'abc', 'raw_object' => true, 'support_calls' => 0), json_decode($result[1], true));
    }

    /**
     * Preserve raw carrier values and decoded bytes passed to existing host hooks.
     *
     * @return void
     */
    public function testOriginalSettingsHookInputs(): void
    {
        $result = $this->nativeProbe('settings-hook-inputs-probe.php', []);
        self::assertSame(0, $result[0], $result[2] . $result[1]);
        self::assertSame('', $result[2]);
        $rows = json_decode($result[1], true);
        foreach (array('raw-string', 'raw-null', 'raw-object', 'decoded-array', 'decoded-null', 'decoded-stringable') as $name) {
            self::assertSame(array('value' => array('value' => 'original-hook')), $rows[$name]['result']);
            self::assertSame(array(), $rows[$name]['events']);
        }
        self::assertSame(array('base64', 'NULL', null), $rows['raw-null']['inputs'][0]);
        self::assertSame(array('base64', 'object', array('carrier' => 1)), $rows['raw-object']['inputs'][0]);
        self::assertSame(array('unserialize', 'string', 'S:3:"abc";'), $rows['raw-string']['inputs'][1]);
        self::assertSame(array('unserialize', 'array', array('native' => 'wire')), $rows['decoded-array']['inputs'][1]);
        self::assertSame(array('unserialize', 'NULL', null), $rows['decoded-null']['inputs'][1]);
        self::assertSame(array('unserialize', 'object', array('wire' => 'S:3:"abc";')), $rows['decoded-stringable']['inputs'][1]);
        self::assertSame(0, $rows['decoded-stringable']['conversions']);
        self::assertSame(array('value' => array('value' => 'original-hook')), $rows['cache']['result']);
        self::assertSame(array(array('unserialize', 'string', 'S:3:"abc";')), $rows['cache']['inputs']);
        self::assertTrue($rows['cache']['writable_paths_match']);
    }

    /**
     * Preserve quiet native integer spellings and extension of stored strings.
     *
     * @dataProvider writableStringOffsets
     * @param int|string $key
     * @param string $expected
     * @return void
     */
    public function testWritableStringOffsetConversion($key, $expected): void
    {
        Environment::$ee->db = new Database(['fieldtypes' => [['name' => 'low_widont', 'settings' => base64_encode(serialize('abc'))]]]);
        NativeOffsetText::$calls = 0;
        NativeOffsetText::$failure = null;
        self::assertTrue((new GlobalFieldSettings())->updateSetting($key, new NativeOffsetText()));
        $written = Environment::$ee->db->tables['fieldtypes'][0]['settings'];
        self::assertSame(serialize($expected), base64_decode($written));
        self::assertSame(1, NativeOffsetText::$calls);
    }

    /**
     * Include signed, padded and leading-whitespace integers accepted by PHP74.
     *
     * @return array
     */
    public static function writableStringOffsets(): array
    {
        return array(array(0, 'lbc'), array(-1, 'abl'), array(-3, 'lbc'), array(3, 'abcl'), array(5, 'abc  l'),
            array('1', 'alc'), array('01', 'alc'), array('+1', 'alc'), array(' 1', 'alc'), array('-1', 'abl'));
    }

    /**
     * Retain readable updater extensions and quiet optional-file fallback.
     *
     * @dataProvider optionalOverrides
     * @param string $state
     * @return void
     */
    public function testOptionalUpdaterExtension($state): void
    {
        $result = $this->nativeProbe('optional-updater-probe.php', [$state]);
        self::assertSame(0, $result[0], $result[2] . $result[1]);
        self::assertSame('', $result[2]);
        self::assertSame(array('updated' => true, 'extension_calls' => $state === 'readable' ? 1 : 0), json_decode($result[1], true));
    }

    /**
     * Preserve native scalar, resource and object byte conversion exactly once.
     *
     * @dataProvider nativeOffsetValues
     * @param string $label
     * @param string $expected
     * @return void
     */
    public function testNativeNonstringOffsetValues($label, $expected): void
    {
        $values = array('integer' => 100, 'negative' => -100, 'float' => 12.5, 'infinity' => INF,
            'negative-infinity' => -INF, 'nan' => NAN, 'true' => true, 'stringable' => new NativeOffsetText());
        $resource = in_array($label, array('resource', 'closed-resource'), true) ? tmpfile() : null;
        if ($label === 'closed-resource') {
            fclose($resource);
        }
        $value = $resource === null ? $values[$label] : $resource;
        NativeOffsetText::$calls = 0;
        NativeOffsetText::$failure = null;
        Environment::$ee->db = new Database(['fieldtypes' => [['name' => 'low_widont', 'settings' => base64_encode(serialize('abc'))]]]);
        try {
            self::assertTrue((new GlobalFieldSettings())->updateSetting(1, $value));
            $writes = array_values(array_filter(Environment::$ee->db->calls, function ($call) { return $call[0] === 'update'; }));
            self::assertSame(serialize($expected), base64_decode(Environment::$ee->db->tables['fieldtypes'][0]['settings']));
            self::assertSame($label === 'stringable' ? 1 : 0, NativeOffsetText::$calls);
        } finally {
            if (is_resource($resource)) {
                fclose($resource);
            }
        }
    }

    /**
     * Include native quiet PHP74 coercions rather than originally invalid values.
     *
     * @return array
     */
    public static function nativeOffsetValues(): array
    {
        return array(array('integer', 'a1c'), array('negative', 'a-c'), array('float', 'a1c'), array('infinity', 'aIc'),
            array('negative-infinity', 'a-c'), array('nan', 'aNc'), array('true', 'a1c'), array('resource', 'aRc'),
            array('closed-resource', 'aRc'), array('stringable', 'alc'));
    }

    /**
     * Preserve a stringable callback exception before any database write.
     *
     * @return void
     */
    public function testNativeOffsetException(): void
    {
        NativeOffsetText::$calls = 0;
        $error = new \RuntimeException('owned native string conversion');
        NativeOffsetText::$failure = $error;
        Environment::$ee->db = new Database(['fieldtypes' => [['name' => 'low_widont', 'settings' => base64_encode(serialize('abc'))]]]);
        try {
            (new GlobalFieldSettings())->updateSetting(1, new NativeOffsetText());
            self::fail('The native string conversion exception must propagate.');
        } catch (\RuntimeException $actual) {
            self::assertSame($error, $actual);
        } finally {
            NativeOffsetText::$failure = null;
        }
        self::assertSame(1, NativeOffsetText::$calls);
        self::assertSame(array(), array_values(array_filter(Environment::$ee->db->calls, function ($call) { return $call[0] === 'update'; })));
    }

    /**
     * Preserve the native empty-string array key for null reads and writes.
     *
     * @return void
     */
    public function testNullSettingArrayKey(): void
    {
        Environment::$ee->db = new Database(['fieldtypes' => [['name' => 'low_widont', 'settings' => base64_encode(serialize(array('' => 'native', 'keep' => 'retained')))]]]);
        $settings = new GlobalFieldSettings();
        self::assertSame('native', $settings->getSetting(null));
        self::assertTrue($settings->updateSetting(null, 'changed'));
        $writes = array_values(array_filter(Environment::$ee->db->calls, function ($call) { return $call[0] === 'update'; }));
        self::assertSame(serialize(array('' => 'changed', 'keep' => 'retained')), base64_decode(Environment::$ee->db->tables['fieldtypes'][0]['settings']));
    }

    /**
     * Retain legal unrelated namespace functions and platform constants.
     *
     * @return void
     */
    public function testNativeSettingsFunctionResolution(): void
    {
        $result = $this->nativeProbe('settings-native-resolution.php', []);
        self::assertSame(0, $result[0], $result[2] . $result[1]);
        self::assertSame('', $result[2]);
        self::assertSame(array('read' => 'native', 'saved' => true, 'wire' => serialize(array(1000 => 'changed', 'keep' => 'retained')), 'legacy' => 'abc', 'support_calls' => 0, 'helper_calls' => 0), json_decode($result[1], true));
    }

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

final class NativeOffsetText
{
    public static $calls = 0;
    public static $failure;

    /**
     * Retain the native stringable conversion count and callback exception.
     *
     * @return string
     * @throws \RuntimeException
     */
    public function __toString()
    {
        self::$calls++;
        if (self::$failure !== null) {
            throw self::$failure;
        }
        return 'long';
    }
}
