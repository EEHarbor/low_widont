<?php

declare(strict_types=1);

namespace LowWidontTests;

use Low\Widont\FluxCapacitor\Conduit\PersistentCache;
use Low\Widont\FluxCapacitor\Conduit\StaticCache;

final class PersistentCacheTest extends TestCase
{
    private $root;
    private $originalPaths;

    /**
     * Give the archive cache a unique filesystem root and EE helper collaborators.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->root = sys_get_temp_dir() . '/low-widont-cache-' . bin2hex(random_bytes(8));
        $this->originalPaths = [];
        foreach (['cache_path', 'module_cache_path'] as $name) {
            $property = $this->property(PersistentCache::class, $name);
            $this->originalPaths[$name] = $property->getValue();
            $property->setValue(null, $this->root . ($name === 'module_cache_path' ? '/low_widont/' : '/'));
        }
        $loader = $this->getMockBuilder(\stdClass::class)->addMethods(['helper', 'library'])->getMock();
        $logger = $this->getMockBuilder(\stdClass::class)->addMethods(['developer'])->getMock();
        Environment::$ee->load = $loader;
        Environment::$ee->logger = $logger;
    }

    /**
     * Restore source static properties and remove only this test's files.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        foreach ($this->originalPaths as $name => $value) {
            $this->property(PersistentCache::class, $name)->setValue(null, $value);
        }
        foreach (glob($this->root . '/low_widont/*') ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        if (is_dir($this->root . '/low_widont')) {
            rmdir($this->root . '/low_widont');
        }
        if (is_dir($this->root)) {
            rmdir($this->root);
        }
        parent::tearDown();
    }

    /**
     * Round-trip nested values using the shipped serialized on-disk format.
     *
     * @group regression
     * @dataProvider storedValues
     * @param mixed $value
     * @return void
     */
    public function testCompositeKeyRoundTrip($value): void
    {
        $this->assertTrue(PersistentCache::set(['entry', 5], $value));
        $file = $this->root . '/low_widont/' . sha1('entry:5');
        $this->assertSame(serialize($value), file_get_contents($file));
        $this->assertTrue(PersistentCache::has(['entry', 5]));
        StaticCache::clear();
        $actual = PersistentCache::get(['entry', 5]);
        is_object($value) ? $this->assertEquals($value, $actual) : $this->assertSame($value, $actual);
    }

    /**
     * Round-trip all payload shapes through a scalar filesystem cache key.
     *
     * @dataProvider storedValues
     * @param mixed $value
     * @return void
     */
    public function testScalarKeyRoundTrip($value): void
    {
        $this->assertTrue(PersistentCache::set('entry', $value));
        $this->assertSame(serialize($value), file_get_contents($this->root . '/low_widont/' . sha1('entry')));
        StaticCache::clear();
        $actual = PersistentCache::get('entry');
        is_object($value) ? $this->assertEquals($value, $actual) : $this->assertSame($value, $actual);
    }

    /**
     * Include serialized falsey values as well as structured payloads.
     *
     * @return array
     */
    public function storedValues(): array
    {
        return [[['id' => 1, 'empty' => null]], [(object) ['id' => 2]], ['text'], ['0'], [''], [0], [1.25], [false], [null], [[]]];
    }

    /**
     * Log missing files and leave unrelated request cache entries intact.
     *
     * @return void
     */
    public function testMissingCacheLogsAndReturnsFalse(): void
    {
        Environment::$ee->logger->expects($this->once())->method('developer')->with($this->stringContains('could not find cache'));
        $this->assertFalse(PersistentCache::get('missing'));
        $this->assertFalse(PersistentCache::has('missing'));
    }

    /**
     * Delete an existing entry and tolerate repeated deletion.
     *
     * @return void
     */
    public function testDeleteAndClearPreserveUnrelatedStaticData(): void
    {
        PersistentCache::set('first', ['id' => 1]);
        PersistentCache::set('second', ['id' => 2]);
        StaticCache::put('unrelated', 'keep');
        $this->assertTrue(PersistentCache::delete('first'));
        $this->assertFalse(PersistentCache::has('first'));
        $this->assertTrue(PersistentCache::delete('first'));
        $this->assertTrue(PersistentCache::clear());
        $this->assertFalse(PersistentCache::has('second'));
        $this->assertSame('keep', StaticCache::get('unrelated'));
        $this->assertTrue(PersistentCache::clear());
    }

    /**
     * Honor preloaded request cache data without touching the filesystem.
     *
     * @return void
     */
    public function testReadsAProcessedRequestCacheKey(): void
    {
        StaticCache::put('processed', ['id' => 7], true);
        $this->assertSame(['id' => 7], PersistentCache::get('processed'));
        $this->assertDirectoryDoesNotExist($this->root);
    }

    /**
     * Use the request cache written by set when the file disappears afterward.
     *
     * @group regression
     * @return void
     */
    public function testSetAndGetUseTheSameStaticCacheKey(): void
    {
        PersistentCache::set('entry', ['id' => 9]);
        unlink($this->root . '/low_widont/' . sha1('entry'));
        $this->assertSame(['id' => 9], PersistentCache::get('entry'));
    }
    /**
     * Return false and log when a file occupies the configured cache directory.
     *
     * @group regression
     * @dataProvider cacheOperations
     * @param string $operation
     * @param array $arguments
     * @return void
     */
    public function testCachePathCreationFailure($operation, array $arguments): void
    {
        mkdir($this->root, 0700);
        $file = $this->root . '/not-a-directory';
        file_put_contents($file, 'occupied');
        $this->property(PersistentCache::class, 'cache_path')->setValue(null, $file);
        Environment::$ee->logger->expects($this->once())->method('developer')->with($this->stringContains('could not create cache folder'));
        try {
            $this->assertFalse(PersistentCache::$operation(...$arguments));
        } finally {
            unlink($file);
        }
    }

    /**
     * Supply all public operations that must honor the cache path guard.
     *
     * @return array
     */
    public function cacheOperations(): array
    {
        return [['get', ['key']], ['set', ['key', 'value']], ['delete', ['key']], ['clear', []]];
    }

    /**
     * Respect a genuine unwritable cache directory instead of attempting file writes.
     *
     * @return void
     */
    public function testUnwritableCachePath(): void
    {
        mkdir($this->root, 0700);
        chmod($this->root, 0500);
        Environment::$ee->logger->expects($this->once())->method('developer')->with($this->stringContains('cache path not writable'));
        try {
            $this->assertFalse(PersistentCache::set('key', 'value'));
        } finally {
            chmod($this->root, 0700);
        }
    }

    /**
     * Reject a non-directory occupying the addon cache directory without warnings.
     *
     * @group regression
     * @return void
     */
    public function testModuleCachePathCreationFailure(): void
    {
        mkdir($this->root, 0700);
        file_put_contents($this->root . '/low_widont', 'occupied');
        Environment::$ee->logger->expects($this->once())->method('developer')->with($this->stringContains('could not create module cache folder'));
        try {
            $this->assertFalse(PersistentCache::set('key', 'value'));
        } finally {
            unlink($this->root . '/low_widont');
        }
    }

    /**
     * Respect a genuine unwritable addon cache directory.
     *
     * @return void
     */
    public function testUnwritableModuleCachePath(): void
    {
        mkdir($this->root, 0700);
        mkdir($this->root . '/low_widont', 0500);
        Environment::$ee->logger->expects($this->once())->method('developer')->with($this->stringContains('module cache path not writable'));
        try {
            $this->assertFalse(PersistentCache::set('key', 'value'));
        } finally {
            chmod($this->root . '/low_widont', 0700);
        }
    }

    /**
     * Exercise every guard return against genuine filesystem permissions.
     *
     * @dataProvider guardedReads
     * @param string $operation
     * @param array $arguments
     * @param bool $moduleOnly
     * @return void
     */
    public function testReadDeleteAndClearPermissionGuards($operation, array $arguments, $moduleOnly): void
    {
        mkdir($this->root, 0700);
        mkdir($this->root . '/low_widont', 0700);
        $path = $moduleOnly ? $this->root . '/low_widont' : $this->root;
        chmod($path, 0500);
        Environment::$ee->logger->expects($this->once())->method('developer')->with($this->stringContains($moduleOnly ? 'module cache path not writable' : 'cache path not writable'));
        try {
            $this->assertFalse(PersistentCache::$operation(...$arguments));
        } finally {
            chmod($path, 0700);
        }
    }

    /**
     * Cover both generic and module permission guards through all read/delete APIs.
     *
     * @return array
     */
    public function guardedReads(): array
    {
        return [['get', ['entry'], false], ['delete', ['entry'], false], ['clear', [], false],
            ['get', ['entry'], true], ['delete', ['entry'], true], ['clear', [], true]];
    }

    /**
     * Keep directories and unrelated static values while removing cache files.
     *
     * @return void
     */
    public function testClearIgnoresChildDirectories(): void
    {
        PersistentCache::set('entry', 'cached');
        mkdir($this->root . '/low_widont/child', 0700);
        try {
            $this->assertTrue(PersistentCache::clear());
            $this->assertDirectoryExists($this->root . '/low_widont/child');
            $this->assertFalse(PersistentCache::has('entry'));
        } finally {
            rmdir($this->root . '/low_widont/child');
        }
    }

    /**
     * Honor the public processed-key argument without hashing an already hashed key again.
     *
     * @group regression
     * @return void
     */
    public function testHasHonorsProcessedKeys(): void
    {
        PersistentCache::set('entry', 'cached');
        $this->assertTrue(PersistentCache::has(sha1('entry'), true));
    }

    /**
     * Preserve a meaningful zero component in a documented composite filesystem key.
     *
     * @group regression
     * @return void
     */
    public function testCompositeZeroKeyDoesNotOverwriteItsParent(): void
    {
        PersistentCache::set(['entry'], 'parent');
        PersistentCache::set(['entry', 0], 'zero');
        StaticCache::clear();
        $this->assertSame('parent', PersistentCache::get('entry'));
        $this->assertFileExists($this->root . '/low_widont/' . sha1('entry:0'));
    }

    /**
     * Reject damaged cache serialization without uncaught PHP diagnostics.
     *
     * @group regression
     * @return void
     */
    public function testMalformedCacheIsDiagnosticFree(): void
    {
        PersistentCache::set('entry', 'cached');
        file_put_contents($this->root . '/low_widont/' . sha1('entry'), 'broken serialized data');
        StaticCache::clear();
        $this->assertFalse(PersistentCache::get('entry'));
    }

}
