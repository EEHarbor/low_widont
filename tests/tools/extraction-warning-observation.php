<?php

error_reporting(E_ALL);
set_error_handler(static function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
}, E_ALL);
$root = dirname(__DIR__, 2);
require $root . '/tests/support/Environment.php';
require $root . '/tests/support/Evidence.php';
require $root . '/tests/support/SourceWarningObservation.php';
$mode = $argv[1];
$directory = realpath($argv[2]);
if (!in_array($mode, ['cold', 'cleanup'], true) || !$directory || file_exists($directory . '/observation.json')
    || getenv('TMPDIR') !== $directory) {
    throw new RuntimeException('A fresh owned temporary directory and known extraction context are required.');
}
define('PATH_THIRD', $root . '/');
$archive = $root . '/low_widont/low_widont.phar';
$inventory = json_decode(file_get_contents($root . '/tests/SOURCE_INVENTORY.json'), true, 512, JSON_THROW_ON_ERROR);
$archiveHash = $inventory['archive_sha256'];
if (hash_file('sha256', $archive) !== $archiveHash) {
    throw new RuntimeException('Observation archive differs from the pinned source inventory.');
}
$members = [];
foreach (new RecursiveIteratorIterator(new Phar($archive)) as $file) {
    $name = substr($file->getPathname(), strlen('phar://' . $archive . '/'));
    $members[$name] = $inventory['files']['low_widont/low_widont.phar!/' . $name]['sha256'];
    if (hash_file('sha256', $file->getPathname()) !== $members[$name]) {
        throw new RuntimeException('Observation archive member differs from its pinned bytes: ' . $name);
    }
}
ksort($members);
$cache = $directory . '/pharextract/low_widont';
$observer = new LowWidontTests\SourceWarningObservation($archive, $archiveHash);
$origin = getcwd();
$native = function_exists('xdebug_info') && in_array('coverage', xdebug_info('mode'), true);
if ($native) {
    xdebug_set_filter(XDEBUG_FILTER_CODE_COVERAGE, XDEBUG_PATH_INCLUDE, [$root . '/low_widont/', $cache . '/']);
    xdebug_start_code_coverage(XDEBUG_CC_UNUSED | XDEBUG_CC_DEAD_CODE | XDEBUG_CC_BRANCH_CHECK);
}
require $root . '/low_widont/autoload.php';
if (!stream_wrapper_unregister('phar')) {
    throw new RuntimeException('Cannot select actual cold archive extraction.');
}
set_error_handler($observer, E_ALL);
try {
    $loaded = class_exists('Low\\Widont\\FluxCapacitor\\Base\\Pi');
    $classFile = $loaded ? (new ReflectionClass('Low\\Widont\\FluxCapacitor\\Base\\Pi'))->getFileName() : null;
    $extracted = [];
    foreach ($members as $name => $hash) {
        $extracted[$name] = hash_file('sha256', $cache . '/' . $name);
        if ($extracted[$name] !== $hash) {
            throw new RuntimeException('Actual extracted archive member differs: ' . $name);
        }
    }
    $marker = is_file($cache . '/' . md5_file($archive));
    $cwdAfterLoad = getcwd();
    Extract_low_widont::go(true);
    $reused = [];
    foreach ($members as $name => $hash) {
        $reused[$name] = hash_file('sha256', $cache . '/' . $name);
    }
    if ($mode === 'cleanup') {
        Extract_low_widont::_removeTmpFiles($cache, $origin);
    }
} finally {
    restore_error_handler();
    if (!stream_wrapper_restore('phar')) {
        throw new RuntimeException('Cannot restore the original archive wrapper.');
    }
}
$remaining = [];
if (is_dir($cache)) {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($cache, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST) as $file) {
        $remaining[substr($file->getPathname(), strlen($cache) + 1)] = $file->isDir() ? 'directory' : 'file';
    }
}
ksort($remaining);
$source = [];
if ($native) {
    foreach (xdebug_get_code_coverage() as $file => $data) {
        if (strpos($file, $cache . '/') === 0) {
            $name = substr($file, strlen($cache) + 1);
            if (!isset($extracted[$name]) || $extracted[$name] !== $members[$name]) {
                throw new RuntimeException('Compiled extracted source lacks verified archive bytes.');
            }
            $data['sha256'] = $extracted[$name];
            $data['source_unit'] = 'low_widont/low_widont.phar!/' . $name;
            $data['source_bytes_verified_at'] = 'after-extraction-before-cleanup';
            $data['exists_after_cleanup'] = is_file($file);
            if ($data['exists_after_cleanup'] && hash_file('sha256', $file) !== $data['sha256']) {
                throw new RuntimeException('Extracted source bytes changed after verification.');
            }
        } else {
            if (strpos($file, $root . '/low_widont/') !== 0) {
                continue;
            }
            $data['sha256'] = hash_file('sha256', $file);
        }
        $source[$file] = $data;
    }
    xdebug_stop_code_coverage();
}
$diagnostics = $observer->diagnostics();
$record = ['mode' => 'observational-warning-continuation', 'context' => 'extraction-' . $mode,
    'diagnostic_free' => !$diagnostics, 'strict_suite_graphs_mergeable' => false,
    'php' => PHP_VERSION, 'binary' => PHP_BINARY, 'xdebug' => phpversion('xdebug') ?: null,
    'flags' => $native ? XDEBUG_CC_UNUSED | XDEBUG_CC_DEAD_CODE | XDEBUG_CC_BRANCH_CHECK : null,
    'native_coverage_collected' => $native, 'archive_sha256' => $archiveHash,
    'inventory_sha256' => hash_file('sha256', $root . '/tests/SOURCE_INVENTORY.json'),
    'phpunit_sha256' => hash_file('sha256', $root . '/tests/.phpunit/phpunit-9.phar'),
    'source_sha256' => $archiveHash, 'diagnostics' => $diagnostics, 'class_loaded' => $loaded,
    'class_file' => $classFile, 'members' => $members, 'extracted' => $extracted, 'reused' => $reused,
    'marker_created' => $marker, 'cwd_after_load' => $cwdAfterLoad, 'cwd_after_cleanup' => getcwd(),
    'cache_directory_exists' => is_dir($cache), 'remaining_paths' => $remaining, 'source' => $source];
LowWidontTests\Evidence::write($directory . '/observation.json', $record);
echo json_encode(['context' => $record['context'], 'diagnostic_free' => $record['diagnostic_free']], JSON_THROW_ON_ERROR), PHP_EOL;
exit($diagnostics ? 1 : 0);
