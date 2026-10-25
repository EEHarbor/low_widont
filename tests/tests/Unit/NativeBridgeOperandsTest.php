<?php

namespace LowWidontTests;

final class NativeBridgeOperandsTest extends TestCase
{
    /**
     * Keep all four legacy callers' native frames, host operands and call-site lifetime.
     *
     * @dataProvider bridgeOperandCases
     * @param string $kind
     * @param string $mode
     * @return void
     */
    public function testOriginalBridgeOperands($kind, $mode): void
    {
        $root = dirname(__DIR__, 3);
        $result = NativeProcess::runCommand(array(PHP_BINARY, $root . '/tests/tools/bridge-native-operands-probe.php',
            $root . '/low_widont/low_widont.phar', $kind, $mode), $root);
        self::assertSame(0, $result[0], $result[1] . $result[2]);
        self::assertSame('', $result[2]);
        $masked = @error_reporting();
        $events = array();
        if ($mode === 'hook') {
            $events = array(array('carrier-hook', 'object', $masked), array('parser-hook', 'object', $masked), array('destroy', $masked));
        }
        if ($mode === 'frame') {
            $events = array(array('carrier-hook', 'string', $masked), array('convert', 'unserialize', $masked), array('destroy', $masked));
        }
        if ($mode === 'raw') {
            $events = array(array('convert', 'base64_decode', $masked), array('destroy', $masked));
        }
        self::assertSame(array('values' => $mode === 'snapshot' ? array('native', 'native') : array('native'), 'events' => $events), json_decode($result[1], true, 512, JSON_THROW_ON_ERROR));
    }

    /**
     * Cover each independent original Bridge expression and supported operand boundary.
     *
     * @return array
     */
    public static function bridgeOperandCases(): array
    {
        $cases = array();
        foreach (array('EE2', 'EE3', 'EE4', 'EE5') as $kind) {
            foreach (array('hook', 'frame', 'raw', 'snapshot', 'native-guard') as $mode) {
                $cases[$kind . ' ' . $mode] = array($kind, $mode);
            }
        }
        return $cases;
    }
}
