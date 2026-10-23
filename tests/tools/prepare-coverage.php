<?php

error_reporting(E_ALL);
set_error_handler(static function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
}, E_ALL);

$root = dirname(__DIR__, 2);
$run = getenv('LOW_WIDONT_REPORT_DIR');
if (!$run || !is_dir($run)) {
    throw new RuntimeException('LOW_WIDONT_REPORT_DIR must name a fresh existing report directory.');
}
$archivePath = $root . '/low_widont/low_widont.phar';
$archive = new Phar($archivePath);
$prefix = 'phar://' . $archivePath . '/';
$destination = $run . '/source';
$files = [];
foreach (new RecursiveIteratorIterator($archive) as $file) {
    if ($file->getExtension() !== 'php') {
        continue;
    }
    $relative = substr($file->getPathname(), strlen($prefix));
    $target = $destination . '/' . $relative;
    if (!is_dir(dirname($target))) {
        mkdir(dirname($target), 0700, true);
    }
    $contents = $file->getContent();
    file_put_contents($target, $contents);
    if (hash_file('sha256', $target) !== hash('sha256', $contents)) {
        throw new RuntimeException('Measurement copy differs from shipped archive: ' . $relative);
    }
    $files[$relative] = ['sha256' => hash('sha256', $contents), 'bytes' => strlen($contents)];
}
$loose = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/low_widont', FilesystemIterator::SKIP_DOTS)) as $file) {
    if ($file->getExtension() === 'php') {
        $loose[substr($file->getPathname(), strlen($root) + 1)] = hash_file('sha256', $file->getPathname());
    }
}
$manifest = ['baseline' => '3a38b1fb9146daa87ed9f1a83699521ea03091da', 'archive_sha256' => hash_file('sha256', $archivePath),
    'php' => PHP_VERSION, 'xdebug' => phpversion('xdebug'), 'phpunit_sha256' => hash_file('sha256', $root . '/tests/.phpunit/phpunit-9.phar'), 'ee' => getenv('LOW_WIDONT_EE_VERSION') ?: '7.5.0', 'source' => $files, 'loose' => $loose,
    'stub' => ['sha256' => hash('sha256', $archive->getStub()), 'bytes' => strlen($archive->getStub())]];
file_put_contents($run . '/inventory.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
$config = new DOMDocument();
$config->load($root . '/phpunit.xml');
$config->documentElement->setAttribute('bootstrap', $root . '/tests/coverage-bootstrap.php');
$xpath = new DOMXPath($config);
foreach ($xpath->query('/phpunit/testsuites/testsuite/directory | /phpunit/coverage/include/directory | /phpunit/coverage/include/file') as $directory) {
    $directory->nodeValue = $root . '/' . $directory->nodeValue;
}
$source = $config->createElement('directory', $destination);
$source->setAttribute('suffix', '.php');
$xpath->query('/phpunit/coverage/include')->item(0)->appendChild($source);
$config->save($run . '/phpunit.xml');
echo $run . '/phpunit.xml', PHP_EOL;
