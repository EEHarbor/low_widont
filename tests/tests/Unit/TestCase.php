<?php

declare(strict_types=1);

namespace LowWidontTests;

use Low\Widont\FluxCapacitor\Conduit\StaticCache;

abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    private $includePath;
    private $autoloaders;
    private $errorLevel;
    /**
     * Reset request state and fail source diagnostics for every test.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->includePath = get_include_path();
        $this->autoloaders = spl_autoload_functions();
        $this->errorLevel = error_reporting(E_ALL);
        set_error_handler([Environment::class, 'diagnostic']);
        Environment::$ee = (object) ['TMPL' => (object) ['tagdata' => 'Template final words']];
        Environment::$services = [];
        Environment::$calls = [];
        StaticCache::clear();
    }

    /**
     * Restore request state and the diagnostic handler.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        StaticCache::clear();
        Environment::$ee = null;
        Environment::$services = [];
        Environment::$calls = [];
        foreach (spl_autoload_functions() as $loader) {
            if (!in_array($loader, $this->autoloaders, true)) {
                spl_autoload_unregister($loader);
            }
        }
        set_include_path($this->includePath);
        restore_error_handler();
        error_reporting($this->errorLevel);
        parent::tearDown();
    }

    /**
     * Access a private source property without deprecated reflection calls.
     *
     * @param string $class
     * @param string $name
     * @return \ReflectionProperty
     */
    protected function property($class, $name)
    {
        $property = new \ReflectionProperty($class, $name);
        if (PHP_VERSION_ID < 80100) {
            $property->setAccessible(true);
        }
        return $property;
    }
    /**
     * Invoke an inherited protected method through PHP's reflection API.
     *
     * @param object $object
     * @param string $name
     * @param array $arguments
     * @return mixed
     */
    protected function invoke($object, $name, array $arguments = [])
    {
        $method = new \ReflectionMethod($object, $name);
        if (PHP_VERSION_ID < 80100) {
            $method->setAccessible(true);
        }
        return $method->invokeArgs($object, $arguments);
    }

}
