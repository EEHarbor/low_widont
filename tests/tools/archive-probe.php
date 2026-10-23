<?php

$root = dirname(__DIR__, 2);
require $root . '/tests/support/Evidence.php';
LowWidontTests\Evidence::nativeChild($root, 'archive-' . $argv[1]);
define('PATH_THIRD', $root . '/');
require $root . '/tests/support/Environment.php';
set_error_handler(['LowWidontTests\\Environment', 'diagnostic'], E_ALL);
require $root . '/low_widont/low_widont.phar';
$data = 'archive payload';
$stream = fopen('php://temp', 'w+b');
fwrite($stream, $data);
rewind($stream);
$entry = [strlen($data), 0, strlen($data), sprintf('%u', crc32($data) & 0xffffffff), 0, 0];
if ($argv[1] === 'checksum') {
    $entry[3] = '0';
}
if ($argv[1] === 'size') {
    $entry[0]++;
}
Extract_low_widont::extractFile('fixture.txt', $entry, $stream);
