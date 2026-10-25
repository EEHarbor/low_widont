<?php

$mode = $argv[1];
$letterPath = $mode === 'letter-existing-module';
if ($letterPath) {
    $mode = 'existing-module';
}
$fileUri = substr($mode, 0, 5) === 'file-';
if ($fileUri) {
    $mode = substr($mode, 5);
}
$directory = sys_get_temp_dir() . '/cache-search';
mkdir($directory, 0700);
$storage = $letterPath ? $directory . '/a:' : $directory;
if ($letterPath) {
    mkdir($storage, 0700);
}
mkdir($storage . '/parent', 0700);
$cache = $storage . '/parent/cache/';
if ($mode !== 'base') {
    mkdir($cache, 0700);
}
if ($mode === 'existing-module') {
    mkdir($cache . 'low_widont', 0700);
}
$originalDirectory = getcwd();
if ($letterPath) {
    chdir($directory);
}
define('PATH_CACHE', $letterPath ? 'a://parent/cache/' : ($fileUri ? 'file://' . $cache : $cache));
define('DIR_WRITE_MODE', 0700);
define('FILE_WRITE_MODE', 0600);
$app = (object) array(
    'load' => new class {
        /**
         * Accept the requested logger service.
         *
         * @param string $name
         * @return void
         */
        public function library($name) {}

        /**
         * Retain the file helper supplied by this fixture.
         *
         * @param string $name
         * @return void
         */
        public function helper($name) {}
    },
    'logger' => new class {
        public $messages = array();

        /**
         * Retain the cache failure message for the assertion.
         *
         * @param string $message
         * @return void
         */
        public function developer($message) { $this->messages[] = $message; }
    },
);

/**
 * Return the request-local EE service fixture.
 *
 * @return object
 */
function ee()
{
    global $app;
    return $app;
}

/**
 * Write bytes through the file helper contract used by PersistentCache.
 *
 * @param string $path
 * @param string $data
 * @return bool
 */
function write_file($path, $data)
{
    return file_put_contents($path, $data) !== false;
}

$archive = dirname(__DIR__, 2) . '/low_widont/low_widont.phar';
require 'phar://' . $archive . '/FluxCapacitor/Conduit/StaticCache.php';
require 'phar://' . $archive . '/FluxCapacitor/Conduit/PersistentCache.php';
$locked = $mode === 'base' ? $directory . '/parent' : ($mode === 'module' ? $cache : $cache . 'low_widont');
chmod($locked, 0200);
clearstatcache();
try {
    $result = array(
        'writable' => is_writable($locked),
        'searchable' => is_executable($locked),
        'value' => Low\Widont\FluxCapacitor\Conduit\PersistentCache::set('key', 'value'),
        'logs' => count($app->logger->messages),
        'cached' => Low\Widont\FluxCapacitor\Conduit\StaticCache::has('key'),
    );
} finally {
    chmod($locked, 0700);
    chdir($originalDirectory);
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $file) {
        if ($file->isDir()) {
            rmdir($file->getPathname());
        } else {
            unlink($file->getPathname());
        }
    }
    rmdir($directory);
}
echo json_encode($result, JSON_THROW_ON_ERROR);
