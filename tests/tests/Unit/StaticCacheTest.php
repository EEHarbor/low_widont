<?php

declare(strict_types=1);

namespace LowWidontTests;

use Low\Widont\FluxCapacitor\Conduit\StaticCache;

final class StaticCacheTest extends TestCase
{
    /**
     * Store and retrieve scalar and structured values with normal and processed keys.
     *
     * @dataProvider cacheValues
     * @param mixed $value
     * @return void
     */
    public function testStoresValues($value): void
    {
        StaticCache::put(['request', 'entry'], $value);
        $this->assertTrue(StaticCache::has(['request', 'entry']));
        $this->assertSame($value, StaticCache::get(['request', 'entry']));
        StaticCache::set('raw:key', $value, true);
        $this->assertSame($value, StaticCache::get('raw:key', true));
        $this->assertArrayHasKey('raw:key', StaticCache::all());
    }

    /**
     * Supply the cache's documented array/object values and nonempty scalars.
     *
     * @return array
     */
    public function cacheValues(): array
    {
        return [['value'], [['nested' => [false, 0, null]]], [(object) ['id' => 5]], [42], [false]];
    }

    /**
     * Normalize composite string key components consistently with scalar keys.
     *
     * @return void
     */
    public function testNormalizesKeyComponents(): void
    {
        StaticCache::put(['entry', 'site'], ['id' => 10]);
        $this->assertSame(['id' => 10], StaticCache::get(['entry', 'site']));
        $this->assertArrayHasKey(sha1('entry:site'), StaticCache::all());
        $this->assertFalse(StaticCache::get('missing'));
        $this->assertFalse(StaticCache::has('missing'));
    }

    /**
     * Delete one value without clearing unrelated request data.
     *
     * @return void
     */
    public function testDeleteRemovesOnlyTheRequestedKey(): void
    {
        StaticCache::put('delete', 'first');
        StaticCache::put('keep', 'second');
        StaticCache::delete('delete');
        $this->assertFalse(StaticCache::has('delete'));
        $this->assertSame('second', StaticCache::get('keep'));
        $this->assertTrue(StaticCache::clear());
        $this->assertSame([], StaticCache::all());
        $this->assertTrue(StaticCache::clear());
    }

    /**
     * Report successful deletion through the documented boolean result.
     *
     * @group regression
     * @return void
     */
    public function testSuccessfulDeletionReportsSuccess(): void
    {
        StaticCache::put('entry', ['id' => 1]);
        $this->assertTrue(StaticCache::delete('entry'));
    }
    /**
     * Retrieve falsey values instead of treating a stored empty array or zero as missing.
     *
     * @group regression
     * @dataProvider falseyPayloads
     * @param mixed $value
     * @return void
     */
    public function testStoredFalseyPayloadsRemainRetrievable($value): void
    {
        StaticCache::put('entry', $value);
        $this->assertTrue(StaticCache::has('entry'));
        $this->assertSame($value, StaticCache::get('entry'));
    }

    /**
     * Supply empty arrays and meaningful zero values alongside empty strings.
     *
     * @return array
     */
    public function falseyPayloads(): array
    {
        return [[[]], [0], ['0'], ['']];
    }

    /**
     * Keep a meaningful zero key component distinct from an absent component.
     *
     * @group regression
     * @return void
     */
    public function testZeroKeyComponentDoesNotCollide(): void
    {
        StaticCache::put(['entry', 0, 'site'], ['value' => 'zero']);
        StaticCache::put(['entry', 'site'], ['value' => 'ordinary']);
        $this->assertSame(['value' => 'zero'], StaticCache::get(['entry', 0, 'site']));
        $this->assertCount(2, StaticCache::all());
    }

}
