<?php

use Low\Widont\FluxCapacitor\Conduit\PersistentCache;
use Low\Widont\FluxCapacitor\Conduit\StaticCache;

/** Verify the checked launcher rejects even caught raw-cache parser warnings. */
class RawRangeDiagnosticTest extends \LowWidontTests\TestCase
{
    /**
     * Exercise the actual raw cache reader and leave rejection to its durable journal.
     *
     * @dataProvider ranges
     * @param string $integer
     * @return void
     */
    public function testRawRangeWarningIsRetained($integer): void
    {
        \LowWidontTests\Environment::$ee->load = $this->getMockBuilder(\stdClass::class)->addMethods(['helper', 'library'])->getMock();
        \LowWidontTests\Environment::$ee->logger = $this->getMockBuilder(\stdClass::class)->addMethods(['developer'])->getMock();
        try {
            PersistentCache::set('owned-raw-range', array());
            file_put_contents(PATH_CACHE . 'low_widont/' . sha1('owned-raw-range'), 'i:' . $integer . ';');
            StaticCache::clear();
            try {
                PersistentCache::get('owned-raw-range');
            } catch (\ErrorException $exception) {
            }
            self::assertTrue(true);
        } finally {
            PersistentCache::delete('owned-raw-range');
            rmdir(PATH_CACHE . 'low_widont');
            rmdir(PATH_CACHE);
        }
    }

    /**
     * Include both diagnostic-producing native clamp directions.
     *
     * @return array
     */
    public static function ranges(): array
    {
        return array(array(str_repeat('9', 30)), array('-' . str_repeat('9', 30)));
    }
}
