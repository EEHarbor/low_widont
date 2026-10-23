<?php

declare(strict_types=1);

namespace LowWidontTests;

final class Facade
{
    private $loaded = [];

    /**
     * Register only previously unloaded legacy services, including loaded null values.
     *
     * @param string $name
     * @param mixed $value
     * @return void
     * @throws \RuntimeException
     */
    public function set($name, $value)
    {
        if ($this->has($name)) {
            throw new \RuntimeException('Cannot overwrite ' . $name . ' on the loader.');
        }
        $this->loaded[$name] = $value;
    }

    /**
     * Remove a loaded service using the EE legacy facade contract.
     *
     * @param string $name
     * @return void
     */
    public function remove($name)
    {
        unset($this->loaded[$name]);
    }

    /**
     * Distinguish an absent service from one explicitly registered as null.
     *
     * @param string $name
     * @return bool
     */
    public function has($name)
    {
        return array_key_exists($name, $this->loaded);
    }

    /**
     * Retrieve the exact registered service.
     *
     * @param string $name
     * @return mixed
     * @throws \InvalidArgumentException
     */
    public function get($name)
    {
        if (!$this->has($name)) {
            throw new \InvalidArgumentException('No such property: ' . $name);
        }
        return $this->loaded[$name];
    }
}
