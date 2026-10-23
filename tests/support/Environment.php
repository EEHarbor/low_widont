<?php

declare(strict_types=1);

namespace LowWidontTests {
    final class Environment
    {
        public static $ee;
        public static $services = [];
        public static $calls = [];

        /**
         * Convert every E_ALL diagnostic to an exception, including under suppression.
         *
         * @param int $severity
         * @param string $message
         * @param string $file
         * @param int $line
         * @return bool
         * @throws \ErrorException
         */
        public static function diagnostic($severity, $message, $file, $line)
        {
            throw new \ErrorException($message, 0, $severity, $file, $line);
        }
    }
}

namespace {
    /**
     * Resolve the legacy facade or a named EE dependency.
     *
     * @param string|null $service
     * @return mixed
     */
    function ee($service = null)
    {
        if ($service === null) {
            return \LowWidontTests\Environment::$ee;
        }

        \LowWidontTests\Environment::$calls[] = func_get_args();
        return \LowWidontTests\Environment::$services[$service];
    }

    /**
     * Read a complete cache file as EE's file helper does.
     *
     * @param string $path
     * @return string|false
     */
    function read_file($path)
    {
        return is_file($path) ? file_get_contents($path) : false;
    }

    /**
     * Write a complete cache file as EE's file helper does.
     *
     * @param string $path
     * @param string $data
     * @return bool
     */
    function write_file($path, $data)
    {
        return file_put_contents($path, $data) !== false;
    }

    /**
     * Render the opening form element with the EE helper call contract.
     *
     * @param string $action
     * @param array $attributes
     * @return string
     */
    function form_open($action, $attributes = [])
    {
        return '<form action="' . htmlspecialchars($action, ENT_QUOTES, 'UTF-8') . '" class="' . htmlspecialchars($attributes['class'], ENT_QUOTES, 'UTF-8') . '">';
    }

    /**
     * Render a text input with the EE helper call contract.
     *
     * @param string $name
     * @param string $value
     * @return string
     */
    function form_input($name, $value)
    {
        return '<input name="' . $name . '" value="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '" />';
    }

    /**
     * Render a checkbox with the EE helper call contract.
     *
     * @param string $name
     * @param string $value
     * @param bool $checked
     * @return string
     */
    function form_checkbox($name, $value, $checked)
    {
        return '<input type="checkbox" name="' . $name . '" value="' . $value . '"' . ($checked ? ' checked="checked"' : '') . ' />';
    }

    /**
     * Close an EE form.
     *
     * @return string
     */
    function form_close()
    {
        return '</form>';
    }
}
