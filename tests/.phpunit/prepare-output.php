<?php

/**
 * Prepare only a private PHPUnit tool copy with its original raw-output seam.
 *
 * @param string $source Pinned original tool archive.
 * @param string $target Fresh launcher-owned tool path.
 * @param string $hook Actual checked body-output recorder.
 * @return void
 * @throws RuntimeException When the pinned source or one exact seam differs.
 */
function low_widont_prepare_output($source, $target, $hook)
{
    $expected = '6b5235f282c6ccab3674ac27c5b1ddbd1da838e74bf07002ddcfd030d31298d7';
    if (error_reporting() !== -1 || ini_get('display_errors') !== 'stderr' || ini_get('display_startup_errors') !== '1'
        || hash_file('sha256', $source) !== $expected || !is_file($hook) || is_link($hook)
        || realpath(dirname($target)) !== dirname($target) || file_exists($target) || is_link($target)) {
        throw new RuntimeException('Original PHPUnit/output-capture inputs are not the owned pinned files.');
    }
    if (!copy($source, $target) || !chmod($target, 0600) || hash_file('sha256', $target) !== $expected) {
        throw new RuntimeException('Cannot prepare the exact original PHPUnit tool copy.');
    }
    $archive = new Phar($target);
    $stub = $archive->getStub();
    $alias = $archive->getAlias();
    $algorithm = $archive->getSignature()['hash_type'];
    $members = array();
    foreach (new RecursiveIteratorIterator($archive) as $file) {
        $name = substr($file->getPathname(), strlen('phar://' . $target . '/'));
        $members[$name] = hash('sha256', $file->getContent());
    }
    $member = 'phpunit/Framework/TestCase.php';
    $original = $archive[$member]->getContent();
    $anchor = '        $this->output = ob_get_contents();';
    if (substr_count($original, $anchor) !== 1) {
        throw new RuntimeException('The pinned PHPUnit raw-output seam changed.');
    }
    $retained = "\n        require_once " . var_export($hook, true) . ';'
        . "\n        \\LowWidontTests\\BodyOutput::record(\$this, \$this->output);";
    $updated = str_replace($anchor, $anchor . $retained, $original);
    $archive[$member] = $updated;
    unset($file);
    foreach (new RecursiveIteratorIterator($archive) as $file) {
        $name = substr($file->getPathname(), strlen('phar://' . $target . '/'));
        $actual = hash('sha256', $file->getContent());
        if ($actual !== ($name === $member ? hash('sha256', $updated) : $members[$name])) {
            throw new RuntimeException('An unrelated PHPUnit tool member changed.');
        }
    }
    if ($archive->getStub() !== $stub || $archive->getAlias() !== $alias
        || $archive->getSignature()['hash_type'] !== $algorithm || hash_file('sha256', $source) !== $expected) {
        throw new RuntimeException('The original PHPUnit tool/envelope changed.');
    }
    unset($file, $archive);
    clearstatcache(true, $target);
    $stat = lstat($target);
    $record = array('pid' => getmypid(), 'php' => PHP_VERSION, 'binary' => PHP_BINARY,
        'error_reporting' => error_reporting(), 'display_errors' => ini_get('display_errors'),
        'display_startup_errors' => ini_get('display_startup_errors'), 'source_sha256' => $expected,
        'generator_sha256' => hash_file('sha256', __FILE__), 'hook' => $hook,
        'hook_sha256' => hash_file('sha256', $hook), 'target' => $target,
        'target_sha256' => hash_file('sha256', $target), 'device' => $stat['dev'], 'inode' => $stat['ino'],
        'member' => $member, 'original_member_sha256' => hash('sha256', $original),
        'generated_member_sha256' => hash('sha256', $updated), 'member_count' => count($members),
        'only_one_member_changed' => true, 'original_stub_alias_signature_algorithm_retained' => true);
    echo json_encode($record, JSON_THROW_ON_ERROR), "\n";
}

low_widont_prepare_output($argv[1], $argv[2], $argv[3]);
