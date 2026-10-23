<?php

namespace Low\Widont\Conduit;

class McpNav extends \Low\Widont\FluxCapacitor\Conduit\McpNav
{
    public static $items;
    public static $buttons;
    public static $map;
    public static $deferred = false;
    public $events = [];

    /**
     * Supply an addon navigation configuration or the real parent's empty default.
     *
     * @return array
     */
    protected function defaultItems()
    {
        return self::$items === null ? parent::defaultItems() : self::$items;
    }

    /**
     * Supply addon buttons or the real parent's empty default.
     *
     * @return array
     */
    protected function defaultButtons()
    {
        return self::$buttons === null ? parent::defaultButtons() : self::$buttons;
    }

    /**
     * Supply addon aliases or the real parent's empty default.
     *
     * @return array
     */
    protected function defaultActiveMap()
    {
        return self::$map === null ? parent::defaultActiveMap() : self::$map;
    }

    /**
     * Exercise the parent default while allowing an explicitly deferred addon.
     *
     * @return bool
     */
    public function deferGenerate()
    {
        return self::$deferred ?: parent::deferGenerate();
    }

    /**
     * Preserve the parent lifecycle hook and record addon ordering.
     *
     * @return void
     */
    public function preGenerateNav()
    {
        parent::preGenerateNav();
        $this->events[] = 'before';
    }

    /**
     * Preserve the parent lifecycle hook and record addon ordering.
     *
     * @return void
     */
    public function postGenerateNav()
    {
        parent::postGenerateNav();
        $this->events[] = 'after';
    }
}
