<?php

declare(strict_types=1);

require_once __DIR__ . '/support/Environment.php';
require_once __DIR__ . '/tests/Unit/TestCase.php';

error_reporting(E_ALL);
if (!defined('BASEPATH')) {
    define('BASEPATH', dirname(__DIR__) . '/tests/');
    define('PATH_THIRD', dirname(__DIR__) . '/');
    define('PATH_CACHE', sys_get_temp_dir() . '/low-widont-' . getmypid() . '/');
    define('APP_VER', getenv('LOW_WIDONT_EE_VERSION') ?: '7.5.0');
    define('DIR_WRITE_MODE', 0700);
    define('FILE_WRITE_MODE', 0600);
}

require_once dirname(__DIR__) . '/low_widont/autoload.php';
