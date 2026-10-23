<?php

error_reporting(E_ALL);
set_error_handler(static function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
}, E_ALL);
$root = dirname(__DIR__, 2);
require $root . '/tests/support/Environment.php';
require $root . '/tests/support/Evidence.php';
require $root . '/tests/support/SourceWarningObservation.php';
$scope = $argv[1];
$operation = $argv[2];
$directory = realpath($argv[3]);
if (!in_array($scope, ['base', 'module'], true) || !in_array($operation, ['get', 'set', 'delete', 'clear'], true)
    || !$directory || file_exists($directory . '/observation.json')) {
    throw new RuntimeException('A fresh owned directory and known cache context are required.');
}
define('BASEPATH', $root . '/tests/');
define('PATH_THIRD', $root . '/');
define('PATH_CACHE', $directory . '/cache/');
define('APP_VER', '7.5.0');
define('DIR_WRITE_MODE', 0700);
define('FILE_WRITE_MODE', 0600);
$collision = $directory . '/cache';
if ($scope === 'module') {
    mkdir($collision, 0700);
    $collision .= '/low_widont';
}
if (file_put_contents($collision, 'owned collision') !== strlen('owned collision')) {
    throw new RuntimeException('Cannot create the owned filesystem collision.');
}
$loader = new class {
    public $calls = [];
    /**
     * Retain the requested native EE library boundary.
     *
     * @param string $name
     * @return void
     */
    public function library($name) { $this->calls[] = $name; }
};
$logger = new class {
    public $messages = [];
    /**
     * Retain the source's exact developer log after the native warning.
     *
     * @param string $message
     * @return void
     */
    public function developer($message) { $this->messages[] = $message; }
};
LowWidontTests\Environment::$ee = (object) ['load' => $loader, 'logger' => $logger];
$archive = $root . '/low_widont/low_widont.phar';
$inventory = json_decode(file_get_contents($root . '/tests/SOURCE_INVENTORY.json'), true, 512, JSON_THROW_ON_ERROR);
if (hash_file('sha256', $archive) !== $inventory['archive_sha256']) {
    throw new RuntimeException('Observation archive differs from the pinned source inventory.');
}
$physical = 'phar://' . $archive . '/FluxCapacitor/Conduit/PersistentCache.php';
$sourceHash = $inventory['files']['low_widont/low_widont.phar!/FluxCapacitor/Conduit/PersistentCache.php']['sha256'];
$observer = new LowWidontTests\SourceWarningObservation($physical, $sourceHash);
$native = function_exists('xdebug_info') && in_array('coverage', xdebug_info('mode'), true);
if ($native) {
    xdebug_set_filter(XDEBUG_FILTER_CODE_COVERAGE, XDEBUG_PATH_INCLUDE, [$root . '/low_widont/', 'phar://' . $root . '/low_widont/']);
    xdebug_start_code_coverage(XDEBUG_CC_UNUSED | XDEBUG_CC_DEAD_CODE | XDEBUG_CC_BRANCH_CHECK);
}
require $root . '/low_widont/autoload.php';
set_error_handler($observer, E_ALL);
try {
    $arguments = $operation === 'clear' ? [] : ['entry'];
    if ($operation === 'set') {
        $arguments[] = ['zero' => 0];
    }
    $result = Low\Widont\FluxCapacitor\Conduit\PersistentCache::$operation(...$arguments);
} finally {
    restore_error_handler();
}
$source = [];
if ($native) {
    foreach (xdebug_get_code_coverage() as $file => $data) {
        if (strpos($file, $root . '/low_widont/') !== 0 && strpos($file, 'phar://' . $root . '/low_widont/') !== 0) {
            continue;
        }
        $data['sha256'] = hash_file('sha256', $file);
        $source[$file] = $data;
    }
    xdebug_stop_code_coverage();
}
$diagnostics = $observer->diagnostics();
$record = ['mode' => 'observational-warning-continuation', 'context' => 'cache-' . $scope . '-' . $operation,
    'diagnostic_free' => !$diagnostics, 'strict_suite_graphs_mergeable' => false,
    'php' => PHP_VERSION, 'binary' => PHP_BINARY, 'xdebug' => phpversion('xdebug') ?: null,
    'flags' => $native ? XDEBUG_CC_UNUSED | XDEBUG_CC_DEAD_CODE | XDEBUG_CC_BRANCH_CHECK : null,
    'native_coverage_collected' => $native, 'archive_sha256' => hash_file('sha256', $archive),
    'inventory_sha256' => hash_file('sha256', $root . '/tests/SOURCE_INVENTORY.json'),
    'phpunit_sha256' => hash_file('sha256', $root . '/tests/.phpunit/phpunit-9.phar'),
    'source_sha256' => $sourceHash, 'diagnostics' => $diagnostics, 'actual_return' => $result,
    'loader_calls' => $loader->calls, 'developer_messages' => $logger->messages,
    'collision_path' => $collision, 'collision_contents' => file_get_contents($collision),
    'cache_entry_created' => file_exists(PATH_CACHE . 'low_widont/' . sha1('entry')),
    'source' => $source];
LowWidontTests\Evidence::write($directory . '/observation.json', $record);
echo json_encode(['context' => $record['context'], 'diagnostic_free' => $record['diagnostic_free']], JSON_THROW_ON_ERROR), PHP_EOL;
exit($diagnostics ? 1 : 0);
