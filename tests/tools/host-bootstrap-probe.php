<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
require $root . '/tests/support/Evidence.php';
LowWidontTests\Evidence::nativeChild($root, 'host-bootstrap');
error_reporting(E_ALL);
set_error_handler(static function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
}, E_ALL);
define('PATH_THIRD', $root . '/');
define('BASEPATH', $root . '/tests/');
$GLOBALS['lowWidontHost'] = (object) ['config' => new class {
    /**
     * Supply the legacy app version before the source defines APP_VER.
     *
     * @param string $name
     * @return string|false
     */
    public function item($name) { return $name === 'app_version' ? '7.5.0' : false; }
}];

/**
 * Provide the EE2-compatible super object used by the source's fallback ee() function.
 *
 * @return object
 */
function get_instance()
{
    return $GLOBALS['lowWidontHost'];
}

require $root . '/low_widont/autoload.php';
$bridge = new Low\Widont\FluxCapacitor\Bridge\EE(['module' => 'low_widont', 'module_name' => 'Low Widont']);
echo json_encode(['version' => APP_VER, 'major' => $bridge->getEEVersion(), 'same_host' => ee() === get_instance()], JSON_THROW_ON_ERROR), PHP_EOL;
