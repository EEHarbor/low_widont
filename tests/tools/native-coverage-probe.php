<?php

if (!function_exists('xdebug_start_code_coverage')) {
    throw new RuntimeException('Xdebug coverage must be enabled for instrumentation validation.');
}
xdebug_start_code_coverage(XDEBUG_CC_UNUSED | XDEBUG_CC_DEAD_CODE | XDEBUG_CC_BRANCH_CHECK);
require dirname(__DIR__) . '/fixtures/BranchOutcomes.php';
low_widont_branch_fixture(2);
$partial = xdebug_get_code_coverage();
low_widont_branch_fixture(1);
$complete = xdebug_get_code_coverage();
xdebug_stop_code_coverage();
file_put_contents($argv[1], json_encode(['php' => PHP_VERSION, 'source_sha256' => hash_file('sha256', dirname(__DIR__) . '/fixtures/BranchOutcomes.php'),
    'partial' => $partial, 'complete' => $complete], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
