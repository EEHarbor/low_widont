<?php

namespace LowWidontTests;

final class NativeDirectoryBindingTest extends TestCase
{
    /**
     * Retain independent original mkdir selection, unused sites and PHPUnit restoration.
     *
     * @dataProvider directoryBindingCases
     * @param string $case
     * @param array $results
     * @param array $nativePaths
     * @param array $hookPaths
     * @return void
     */
    public function testOriginalDirectoryCallees($case, array $results, array $nativePaths, array $hookPaths): void
    {
        require_once dirname(__DIR__, 3) . '/tests/support/NativeProcess.php';
        $arguments = array(dirname(__DIR__, 3) . '/low_widont/low_widont.phar', 'Low\\Widont', $case,
            dirname(__DIR__, 3) . '/tests/fixtures/ee-file-helper.php', 'low_widont', dirname(__DIR__, 3) . '/tests/.phpunit/phpunit-9.phar');
        $result = NativeProcess::runCommand(array_merge(array(PHP_BINARY, '-d', 'pcre.jit=0', dirname(__DIR__, 3) . '/tests/tools/cache-mkdir-sites-probe.php'), $arguments), dirname(__DIR__, 3));
        self::assertSame(0, $result[0], $result[1] . $result[2]);
        self::assertSame('', $result[2]);
        $observed = json_decode($result[1], true, 512, JSON_THROW_ON_ERROR);
        self::assertSame($results, $observed['results']);
        self::assertSame($nativePaths, array_column($observed['native'], 0));
        self::assertSame($hookPaths, array_column($observed['hooks'], 0));
        foreach ($observed['native'] as $native) {
            self::assertSame(@error_reporting(), $native[1]);
        }
        foreach ($observed['hooks'] as $hook) {
            self::assertSame(0700, $hook[1]);
            self::assertSame(@error_reporting(), $hook[2]);
        }
        if ($case === 'parent' || $case === 'virtual' || $case === 'nonalpha' || $case === 'opaque') {
            self::assertSame(serialize('stored'), $observed['stored']);
        }
        if ($case === 'opaque') {
            self::assertSame(8, $observed['conversions']);
        }
        self::assertCount(count($hookPaths), $observed['logs']);
        foreach ($observed['logs'] as $message) {
            self::assertStringContainsString('could not create', $message);
        }
    }

    /**
     * Include hooks registered between original opcodes and after skipped paths.
     *
     * @return array
     */
    public static function directoryBindingCases(): array
    {
        $base = 'ownedsite://root/cache/';
        $module = $base . 'low_widont/';
        return array(
            'nonalphabetic native stream scheme' => array('nonalpha', array(true), array('1x://root/cache/', '1x://root/cache/low_widont/'), array()),
            'opaque referenced directory path' => array('opaque', array(true), array($base, $module), array()),
            'virtual directory without host search bits' => array('virtual', array(true), array($base, $module), array()),
            'native parent stat unavailable' => array('parent', array(true), array($base, $module), array()),
            'between original sites' => array('late', array(false), array($base), array($module)),
            'unused site' => array('unused', array(true, false), array($module), array($base)),
            'static restoration' => array('snapshot', array(true, true), array($base, $module, $base, $module), array())
        );
    }
}
