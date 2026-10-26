<?php

namespace LowWidontTests;

/** Deliberate diagnostic faults validate tools and never count as product passes. */
final class EngineVisibilityControlTest extends TestCase
{
    /**
     * Exercise native framing, discarded expected output and post-test callbacks.
     *
     * @return void
     */
    public function testEngineVisibility(): void
    {
        require_once dirname(__DIR__, 2) . '/low_widont/pi.low_widont.php';
        \Low\Widont\FluxCapacitor\Conduit\StaticCache::put('app_settings', array('version' => '4.1.1', 'name' => 'Low Widont'));
        $this->assertSame('Actual final&#160;words', (new \Low_widont('Actual final words'))->return_data);
        $mode = getenv('LOW_WIDONT_ENGINE_CONTROL');
        $warning = strpos($mode, 'literal') === false;
        $html = strpos($mode, 'html') !== false;
        $late = strpos($mode, 'late') !== false;
        $program = 'ini_set("display_errors","1");ini_set("html_errors",' . ($html ? '"1"' : '"0"') . ');'
            . 'ini_set("log_errors","0");set_error_handler(static function(){return false;},-1);'
            . ($warning ? 'trigger_error("Owned native renderer\nsecond line",E_USER_WARNING);'
                : 'echo "Warning: ordinary customer <b>markup</b> stays data";')
            . 'restore_error_handler();';
        if (strpos($mode, 'native-') === 0) {
            if ($late) {
                $program = 'register_shutdown_function(static function(){' . $program . '});';
            }
            $result = NativeProcess::runCommand(array(PHP_BINARY, '-r', $program));
            $this->assertSame(0, $result[0]);
            $this->assertSame('', $result[2]);

            return;
        }
        $emit = static function () use ($warning, $html) {
            $before = array(ini_get('display_errors'), ini_get('html_errors'), ini_get('log_errors'));
            ini_set('display_errors', '1');
            ini_set('html_errors', $html ? '1' : '0');
            ini_set('log_errors', '0');
            set_error_handler(static function () { return false; }, -1);
            try {
                if ($warning) {
                    trigger_error("Owned native renderer\nsecond line", E_USER_WARNING);
                }
                if (!$warning) {
                    echo 'Warning: ordinary customer <b>markup</b> stays data';
                }
            } finally {
                restore_error_handler();
                ini_set('display_errors', $before[0]);
                ini_set('html_errors', $before[1]);
                ini_set('log_errors', $before[2]);
            }
        };
        if ($late) {
            register_shutdown_function($emit);
            $this->assertTrue(true);

            return;
        }
        if ($mode === 'main-expected') {
            ob_start();
            $emit();
            $output = ob_get_clean();
            $this->expectOutputString($output);
            echo $output;
            $this->assertTrue(true);

            return;
        }
        $emit();
        $this->assertTrue(true);
    }
}
