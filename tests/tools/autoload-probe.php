<?php

declare(strict_types=1);

error_reporting(E_ALL);
set_error_handler(static function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
}, E_ALL);

$root = dirname(__DIR__, 2);
$mode = $argv[1];
$temporaryRoot = $argv[2];
require $root . '/tests/support/Evidence.php';
LowWidontTests\Evidence::nativeChild($root, 'autoload-' . $mode . '-opcache' . (int) ini_get('opcache.enable_cli'));
require $root . '/tests/support/Environment.php';
set_error_handler(['LowWidontTests\\Environment', 'diagnostic']);
error_reporting(E_ALL);
define('PATH_THIRD', $mode === 'missing-archive' ? $temporaryRoot . '/' : $root . '/');
if ($mode === 'direct-access') {
    require $root . '/low_widont/pi.low_widont.php';
    exit(1);
}
if ($mode === 'plugin-bootstrap') {
    define('BASEPATH', $root . '/tests/');
    require $root . '/low_widont/pi.low_widont.php';
    $plugin = new ReflectionClass('Low_widont');
    if ($plugin->getFileName() !== $root . '/low_widont/pi.low_widont.php'
        || $plugin->getParentClass()->getName() !== 'Low\\Widont\\FluxCapacitor\\Base\\Pi'
        || !class_exists('Low\\Widont\\FluxCapacitor\\Base\\Pi', false)) {
        throw new RuntimeException('The original plugin did not bootstrap its shipped parent.');
    }
    echo 'plugin-bootstrap:loaded';
    exit(0);
}
$sessionFallback = strpos($mode, 'session-fallback-') === 0;
if ($sessionFallback) {
    $sessionDirectory = $temporaryRoot . '/sessions';
    mkdir($sessionDirectory, 0700);
    $kind = substr($mode, strlen('session-fallback-'));
    $configured = $sessionDirectory;
    if ($kind === 'depth') {
        $configured = '0;' . $sessionDirectory;
    }
    if ($kind === 'permissions') {
        $configured = '0;0600;' . $sessionDirectory;
    }
    ini_set('session.save_handler', 'files');
    ini_set('session.use_cookies', '0');
    session_cache_limiter('');
    session_save_path($configured);
    session_id('low-widont-native-session');
    if (!session_start() || !session_write_close() || !is_file($sessionDirectory . '/sess_low-widont-native-session')) {
        throw new RuntimeException('The selected native session save path is not usable.');
    }
    LowWidontTests\Evidence::write($temporaryRoot . '/session-native.json', [
        'kind' => $kind, 'configured' => session_save_path(), 'native_session_file' => true,
        'temporary_fallback' => $temporaryRoot . '/missing', 'session_directory' => $sessionDirectory,
    ]);
    putenv('TMPDIR=' . $temporaryRoot . '/missing');
}
if ($mode === 'fallback' || $sessionFallback) {
    stream_wrapper_unregister('phar');
}
require $root . '/low_widont/autoload.php';
$loaders = spl_autoload_functions();
$loader = end($loaders);
$preloaded = strpos($mode, 'fallback-preloaded') === 0;
if ($preloaded) {
    $entry = 'FluxCapacitor/Base/Pi.php';
    $bytes = file_get_contents('phar://' . $root . '/low_widont/low_widont.phar/' . $entry);
    $cached = $temporaryRoot . '/pharextract/low_widont/' . $entry;
    mkdir(dirname($cached), 0700, true);
    if ($mode !== 'fallback-preloaded-missing') {
        if (file_put_contents($cached, $bytes) !== strlen($bytes) || hash_file('sha256', $cached) !== hash('sha256', $bytes)) {
            throw new RuntimeException('Preloaded fallback cache differs from the shipped archive.');
        }
    }
    if ($mode === 'fallback-preloaded-unreadable') {
        chmod($cached, 0000);
        if (is_readable($cached)) {
            throw new RuntimeException('The selected user cannot exercise an unreadable cache file.');
        }
    }
    require $root . '/low_widont/low_widont.phar';
    if (!stream_wrapper_unregister('phar')) {
        throw new RuntimeException('Cannot select the warmed fallback loader.');
    }
}
try {
    if ($preloaded) {
        if (in_array('phar', stream_get_wrappers(), true) || !class_exists('Low\\Widont\\FluxCapacitor\\Base\\Pi')) {
            throw new RuntimeException('The warmed loader did not load from the owned extraction cache.');
        }
        $reflection = new ReflectionClass('Low\\Widont\\FluxCapacitor\\Base\\Pi');
        if ($reflection->getFileName() !== $cached || hash_file('sha256', $reflection->getFileName()) !== hash('sha256', $bytes)) {
            throw new RuntimeException('The warmed loader loaded a different class file.');
        }
        echo 'fallback-preloaded:loaded';
    }
    if ($mode === 'namespace-guards') {
        if ($loader('Other\\FluxCapacitor\\ClassName') !== null || $loader('Low\\Widont\\AddonClass') !== null) {
            throw new RuntimeException('The namespace guard returned an unexpected result.');
        }
        echo 'namespace-guards:passed';
    }
    if ($mode === 'archive-loader') {
        require $root . '/low_widont/low_widont.phar';
        $found = false;
        foreach (spl_autoload_functions() as $archiveLoader) {
            if (!$archiveLoader instanceof Closure || substr((new ReflectionFunction($archiveLoader))->getFileName(), -10) !== '/index.php') {
                continue;
            }
            if ($archiveLoader('Foreign\\Namespace') !== null || $archiveLoader('Low\\Widont\\MissingClass') !== null || $archiveLoader('Low\\Widont\\FluxCapacitor\\Base\\Pi') !== null) {
                throw new RuntimeException('Unexpected archive loader return value.');
            }
            $found = class_exists('Low\\Widont\\FluxCapacitor\\Base\\Pi', false);
        }
        if (!$found) {
            throw new RuntimeException('The original archive loader did not load the existing class.');
        }
        echo 'archive-loader:loaded';
    }
    if ($mode === 'missing-archive') {
        $loader('Low\\Widont\\FluxCapacitor\\Base\\Pi');
    }
    if ($mode === 'missing-class') {
        $loader('Low\\Widont\\FluxCapacitor\\MissingClass');
    }
    if ($sessionFallback || in_array($mode, ['fallback', 'stream', 'stream-permission-only', 'stream-no-permission-check', 'stream-no-opcache-api'], true)) {
        echo class_exists('Low\\Widont\\FluxCapacitor\\Base\\Pi') ? 'loaded' : 'missing';
    }
} catch (Exception $exception) {
    if ($exception instanceof ErrorException) {
        throw $exception;
    }
    echo $exception->getMessage();
} finally {
    if ($preloaded && !stream_wrapper_restore('phar')) {
        throw new RuntimeException('Cannot restore the native PHAR wrapper for evidence hashing.');
    }
}
