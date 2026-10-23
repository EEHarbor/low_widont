<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$mode = $argv[1];
$temporaryRoot = $argv[2];
require $root . '/tests/support/Environment.php';
set_error_handler(['LowWidontTests\\Environment', 'diagnostic']);
error_reporting(E_ALL);
define('PATH_THIRD', $mode === 'missing-archive' ? $temporaryRoot . '/' : $root . '/');
if ($mode === 'direct-access') {
    require $root . '/low_widont/pi.low_widont.php';
    exit(1);
}
if ($mode === 'fallback') {
    stream_wrapper_unregister('phar');
}
require $root . '/low_widont/autoload.php';
$loaders = spl_autoload_functions();
$loader = end($loaders);
try {
    if ($mode === 'missing-archive') {
        $loader('Low\\Widont\\FluxCapacitor\\Base\\Pi');
    }
    if ($mode === 'missing-class') {
        $loader('Low\\Widont\\FluxCapacitor\\MissingClass');
    }
    if (in_array($mode, ['fallback', 'stream'], true)) {
        echo class_exists('Low\\Widont\\FluxCapacitor\\Base\\Pi') ? 'loaded' : 'missing';
    }
} catch (Exception $exception) {
    if ($exception instanceof ErrorException) {
        throw $exception;
    }
    echo $exception->getMessage();
}
