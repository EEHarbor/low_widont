<?php

/**
 * Retain Xdebug blocks and exits without treating block hits as branch outcomes.
 *
 * PHPUnit 9 merges hit paths but can retain outgoing counters from only one test.
 * Observed consecutive path nodes therefore provide additional positive exit proof.
 */
ob_start();
require dirname(__DIR__) . '/.phpunit/phpunit-9.phar';
$output = ob_get_clean();
if ($output !== '' && $output !== "#!/usr/bin/env php\n") {
    throw new RuntimeException('Unexpected PHPUnit bootstrap output: ' . $output);
}
$coverage = require $argv[1];
$summary = [];
foreach ($coverage->getData()->functionCoverage() as $file => $methods) {
    foreach ($methods as $name => $method) {
        $graph = [];
        foreach ($method['branches'] as $id => $branch) {
            $graph[$id] = [$branch['op_start'], $branch['op_end'], $branch['line_start'], $branch['line_end'], $branch['out']];
        }
        $observedEdges = [];
        foreach ($method['paths'] as $path) {
            if (!$path['hit']) {
                continue;
            }
            for ($index = 1; $index < count($path['path']); $index++) {
                $observedEdges[$path['path'][$index - 1] . ':' . $path['path'][$index]] = true;
            }
        }
        $blocks = [];
        $exits = [];
        foreach ($method['branches'] as $id => $branch) {
            $blocks[] = ['id' => $id, 'lines' => [$branch['line_start'], $branch['line_end']], 'hit' => (bool) $branch['hit']];
            foreach ($branch['out'] as $index => $destination) {
                $rawHit = $branch['out_hit'][$index] ?? 0;
                $exits[] = ['from' => $id, 'to' => $destination, 'raw_out_hit' => $rawHit,
                    'observed_path_hit' => isset($observedEdges[$id . ':' . $destination]),
                    'proven_hit' => $rawHit > 0 || isset($observedEdges[$id . ':' . $destination])];
            }
        }
        $blockHits = count(array_filter($blocks, static function ($block) { return $block['hit']; }));
        $exitHits = count(array_filter($exits, static function ($exit) { return $exit['proven_hit']; }));
        $summary[] = ['file' => $file, 'method' => $name, 'php' => PHP_VERSION, 'xdebug' => phpversion('xdebug'),
            'source_sha256' => hash_file('sha256', $file), 'graph_sha256' => hash('sha256', json_encode($graph)),
            'basic_blocks' => count($blocks), 'basic_blocks_hit' => $blockHits,
            'basic_block_percent' => $blocks ? round(100 * $blockHits / count($blocks), 2) : null,
            'exits' => count($exits), 'exits_proven_hit' => $exitHits,
            'exit_percent' => $exits ? round(100 * $exitHits / count($exits), 2) : null,
            'blocks' => $blocks, 'outgoing' => $exits,
            'note' => 'Missing graphs and zero-exit methods have unknown exit coverage; synthetic or exceptional exits are not waived.'];
    }
}
echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), PHP_EOL;
