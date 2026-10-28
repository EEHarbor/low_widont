<?php

error_reporting(E_ALL);
set_error_handler(static function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
}, E_ALL);

$root = dirname(__DIR__, 2);
$manifest = json_decode(file_get_contents($root . '/tests/.phpunit/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
$file = $root . '/tests/.phpunit/phpunit-9.phar';
if (!is_file($file) || hash_file('sha256', $file) !== $manifest['phpunit-9.phar']['sha256']) {
    throw new RuntimeException('Missing or mismatched pinned PHPUnit. Download ' . $manifest['phpunit-9.phar']['url']);
}
