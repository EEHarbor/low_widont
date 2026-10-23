<?php

error_reporting(E_ALL);
set_error_handler(static function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
}, E_ALL);

$root = dirname(__DIR__, 2);
define('PATH_THIRD', $root . '/');
require $root . '/low_widont/autoload.php';
$class = new ReflectionClass('Low\\Widont\\FluxCapacitor\\Base\\Pi');
$instance = $class->newInstanceWithoutConstructor();
$result = [];
foreach (['static', 'instance', 'reflection'] as $mode) {
    try {
        if ($mode === 'static') {
            $value = Low\Widont\FluxCapacitor\Base\Pi::info();
        }
        if ($mode === 'instance') {
            $value = $instance->info();
        }
        if ($mode === 'reflection') {
            $value = $class->getMethod('info')->invoke($instance);
        }
        $result[$mode] = ['returned' => $value];
    } catch (Throwable $exception) {
        $result[$mode] = ['exception' => get_class($exception), 'message' => $exception->getMessage(), 'file' => $exception->getFile(), 'line' => $exception->getLine()];
    }
}
echo json_encode(['php' => PHP_VERSION, 'source_sha256' => hash_file('sha256', 'phar://' . $root . '/low_widont/low_widont.phar/FluxCapacitor/Base/Pi.php'), 'attempts' => $result], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), PHP_EOL;
