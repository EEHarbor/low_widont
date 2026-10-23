<?php

require __DIR__ . '/bootstrap.php';

$measurementRoot = getenv('LOW_WIDONT_REPORT_DIR') . '/source/';
spl_autoload_register(function ($class) use ($measurementRoot) {
    $prefix = 'Low\\Widont\\';
    if (strpos($class, $prefix) !== 0) {
        return;
    }
    $relative = str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($measurementRoot . $relative)) {
        require $measurementRoot . $relative;
    }
}, true, true);
