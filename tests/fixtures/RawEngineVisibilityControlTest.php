<?php

namespace LowWidontTests;

/** Exercise standard raw PHPUnit cases, independently of the addon test base. */
final class RawEngineVisibilityControlTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Keep original raw bytes checked before expected output or its callback.
     *
     * @dataProvider layouts
     * @param bool $html Native renderer choice.
     * @return void
     */
    public function testRawOutput($html): void
    {
        require_once dirname(__DIR__, 2) . '/low_widont/pi.low_widont.php';
        Environment::$ee = (object) array('TMPL' => (object) array('tagdata' => 'Actual final words'));
        \Low\Widont\FluxCapacitor\Conduit\StaticCache::put('app_settings', array('version' => '4.1.1', 'name' => 'Low Widont'));
        $this->assertSame('Actual final&#160;words', (new \Low_widont('Actual final words'))->return_data);
        $mode = getenv('LOW_WIDONT_RAW_ENGINE_MODE');
        $before = array(ini_get('display_errors'), ini_get('html_errors'), ini_get('log_errors'));
        ini_set('display_errors', '1');
        ini_set('html_errors', $html ? '1' : '0');
        ini_set('log_errors', '0');
        set_error_handler(static function () { return false; }, -1);
        ob_start();
        try {
            if (strpos($mode, 'warning') !== false) {
                trigger_error("Owned raw native renderer\nsecond line", E_USER_WARNING);
            }
            if (strpos($mode, 'literal') !== false) {
                echo 'Warning: ordinary customer <b>markup</b> stays data';
            }
        } finally {
            $output = ob_get_clean();
            restore_error_handler();
            ini_set('display_errors', $before[0]);
            ini_set('html_errors', $before[1]);
            ini_set('log_errors', $before[2]);
        }
        if (strpos($mode, 'callback-') === 0) {
            $this->setOutputCallback(static function ($bytes) { return 'transformed output'; });
            $this->expectOutputString('transformed output');
        } else {
            $this->expectOutputString($output);
        }
        echo $output;
        $this->assertTrue(true);
    }

    /**
     * Supply both already-supported native output renderers.
     *
     * @return array
     */
    public static function layouts(): array
    {
        return array('plain' => array(false), 'html' => array(true));
    }
}
