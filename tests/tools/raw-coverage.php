<?php

error_reporting(E_ALL);
set_error_handler(static function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
}, E_ALL);

$root = dirname(__DIR__, 2);
require $root . '/tests/tools/verify-tool.php';
require $root . '/tests/support/Evidence.php';
if (!function_exists('xdebug_start_code_coverage')) {
    throw new RuntimeException('Native Xdebug branch instrumentation is required.');
}
$output = $argv[1] ?? null;
if (!$output || file_exists($output)) {
    throw new RuntimeException('Provide a fresh absolute native coverage report path.');
}
if (!is_dir(dirname($output))) {
    mkdir(dirname($output), 0700, true);
}
$report = tempnam(sys_get_temp_dir(), 'low-widont-raw-junit-');
putenv('LOW_WIDONT_CONTEXT_REPORT_DIR=' . dirname($output));
xdebug_set_filter(XDEBUG_FILTER_CODE_COVERAGE, XDEBUG_PATH_INCLUDE, [$root . '/low_widont/', 'phar://' . $root . '/low_widont/low_widont.phar/']);
xdebug_start_code_coverage(XDEBUG_CC_UNUSED | XDEBUG_CC_DEAD_CODE | XDEBUG_CC_BRANCH_CHECK);
ob_start();
require $root . '/tests/.phpunit/phpunit-9.phar';
$bootstrapOutput = ob_get_clean();
if ($bootstrapOutput !== '' && $bootstrapOutput !== "#!/usr/bin/env php\n") {
    throw new RuntimeException('Unexpected PHPUnit bootstrap output.');
}
try {
    $status = (new PHPUnit\TextUI\Command())->run(['native-coverage', '-c', $root . '/phpunit.xml', '--no-coverage', '--log-junit', $report], false);
    $xml = simplexml_load_file($report);
    if (!$xml || (int) $xml->testsuite['tests'] === 0) {
        throw new RuntimeException('Native coverage ran no tests.');
    }
    $counts = (array) $xml->testsuite->attributes();
    $data = xdebug_get_code_coverage();
    xdebug_stop_code_coverage();
} finally {
    unlink($report);
}
$source = [];
foreach ($data as $file => $coverage) {
    if (strpos($file, $root . '/low_widont/') !== 0 && strpos($file, 'phar://' . $root . '/low_widont/') !== 0) {
        continue;
    }
    $coverage['sha256'] = hash_file('sha256', $file);
    $source[$file] = $coverage;
}
$dispatchClass = new ReflectionClass('Low\\Widont\\FluxCapacitor\\Bridge\\EE');
LowWidontTests\Evidence::write($output, ['php' => PHP_VERSION, 'xdebug' => phpversion('xdebug'), 'ee' => APP_VER,
    'dispatch' => ['parent' => $dispatchClass->getParentClass()->getName(), 'constructor_start_line' => $dispatchClass->getMethod('__construct')->getStartLine()],
    'archive_sha256' => hash_file('sha256', $root . '/low_widont/low_widont.phar'),
    'phpunit_sha256' => hash_file('sha256', $root . '/tests/.phpunit/phpunit-9.phar'),
    'flags' => XDEBUG_CC_UNUSED | XDEBUG_CC_DEAD_CODE | XDEBUG_CC_BRANCH_CHECK,
    'suite' => $counts['@attributes'], 'source' => $source]);
echo 'NATIVE_REPORT=' . $output, PHP_EOL;
exit($status);
