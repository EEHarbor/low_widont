<?php

declare(strict_types=1);

error_reporting(E_ALL);
set_error_handler(static function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
}, E_ALL);
$directory = realpath($argv[1] ?? '');
if ($directory === false || !is_file($directory . '/DB_active_rec.php')) {
    throw new RuntimeException('Provide an installed EE legacy database source directory.');
}
require $directory . '/DB_driver.php';
require $directory . '/DB_active_rec.php';
class_alias('CI_DB_active_record', 'CI_DB');
require $directory . '/drivers/mysqli/mysqli_driver.php';
$original = (new ReflectionClass('CI_DB_mysqli_driver'))->newInstanceWithoutConstructor();
$original->where('limit', 9);
$cloned = clone $original;
$cloned->where('site_id', 1);
$cloned->ar_from = ['low_widont_settings'];
echo json_encode([
    'php' => PHP_VERSION,
    'source_directory' => $directory,
    'driver_sha256' => hash_file('sha256', $directory . '/DB_driver.php'),
    'active_record_sha256' => hash_file('sha256', $directory . '/DB_active_rec.php'),
    'mysqli_driver_sha256' => hash_file('sha256', $directory . '/drivers/mysqli/mysqli_driver.php'),
    'clone_handler' => method_exists($original, '__clone'),
    'original_conditions' => $original->ar_where,
    'cloned_conditions' => $cloned->ar_where,
    'compiled_sql' => $cloned->_compile_select(),
    'boundary' => 'Native EE query compilation only; no connection or SQL execution.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), PHP_EOL;
