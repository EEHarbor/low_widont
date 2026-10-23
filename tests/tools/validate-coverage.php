<?php

ob_start();
require dirname(__DIR__) . '/.phpunit/phpunit-9.phar';
ob_end_clean();
$directory = $argv[1];
$native = json_decode(file_get_contents($directory . '/native-instrumentation.json'), true, 512, JSON_THROW_ON_ERROR);
$fixture = dirname(__DIR__) . '/fixtures/BranchOutcomes.php';
$partial = $native['partial'][$fixture]['functions']['low_widont_branch_fixture'];
$complete = $native['complete'][$fixture]['functions']['low_widont_branch_fixture'];
$coverage = require $directory . '/instrumentation.php';
$merged = $coverage->getData()->functionCoverage()[$fixture]['low_widont_branch_fixture'];
$missedRealExits = [];
$lossyMergedExits = [];
$graph = [];
foreach ($complete['branches'] as $id => $branch) {
    $graph[$id] = [$branch['op_start'], $branch['op_end'], $branch['line_start'], $branch['line_end'], $branch['out']];
    $mergedBranch = $merged['branches'][$id];
    if ($graph[$id] !== [$mergedBranch['op_start'], $mergedBranch['op_end'], $mergedBranch['line_start'], $mergedBranch['line_end'], $mergedBranch['out']]) {
        throw new RuntimeException('Native and PHPUnit instrumentation compiled different graphs.');
    }
    if (!$partial['branches'][$id]['hit']) {
        throw new RuntimeException('Validation fixture did not visit every basic block.');
    }
    foreach ($branch['out'] as $index => $destination) {
        if ($destination === 2147483645) {
            continue;
        }
        if (!$partial['branches'][$id]['out_hit'][$index]) {
            $missedRealExits[] = [$id, $destination];
        }
        if ($branch['out_hit'][$index] && !$mergedBranch['out_hit'][$index]) {
            $lossyMergedExits[] = [$id, $destination];
        }
    }
}
if (!$missedRealExits) {
    throw new RuntimeException('Fixture failed to distinguish block coverage from exit coverage.');
}
if (!$lossyMergedExits) {
    throw new RuntimeException('Expected PHPUnit 9 outgoing-counter loss was not reproduced; investigate this instrumentation version.');
}
$report = ['php' => PHP_VERSION, 'xdebug' => phpversion('xdebug'), 'source_sha256' => $native['source_sha256'],
    'graph_sha256' => hash('sha256', json_encode($graph)),
    'all_basic_blocks_hit_with_missed_real_exits' => $missedRealExits,
    'native_positive_but_merged_zero_exits' => $lossyMergedExits,
    'merged_paths' => $merged['paths'], 'native_complete' => $complete, 'phpunit_merged' => $merged];
file_put_contents($directory . '/instrumentation-validation.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
echo 'Instrumentation fixture confirms basic-block/exit distinction and PHPUnit outgoing-counter loss.', PHP_EOL;
