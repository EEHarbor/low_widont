<?php

namespace LowWidontTests;

/** Preserve original testcase bytes before output callbacks or expectations. */
final class BodyOutput
{
    /**
     * Append one original PHPUnit body snapshot to its process-owned file.
     *
     * @param \PHPUnit\Framework\TestCase $test Actual running testcase.
     * @param string $output Complete original PHPUnit buffer before callbacks.
     * @return void
     * @throws \RuntimeException When the private capture cannot be verified or written.
     */
    public static function record($test, $output): void
    {
        $encoded = \get_cfg_var('low_widont.case_stdout');
        if (!\is_string($encoded) || $encoded === '') {
            return;
        }
        $bytes = \base64_decode($encoded, true);
        $owned = $bytes === false ? null : \json_decode($bytes, true, 512, \JSON_THROW_ON_ERROR);
        if (!\is_array($owned) || !\is_string($owned['path'] ?? null) || !\is_string($owned['nonce'] ?? null)) {
            throw new \RuntimeException('Invalid body-output capture identity.');
        }
        \clearstatcache(true, $owned['path']);
        $named = \lstat($owned['path']);
        if ($named === false || \is_link($owned['path']) || ($named['mode'] & 0170000) !== 0100000
            || ($named['mode'] & 0777) !== 0600 || $named['nlink'] !== 1
            || $named['dev'] !== ($owned['device'] ?? null) || $named['ino'] !== ($owned['inode'] ?? null)) {
            throw new \RuntimeException('The body-output capture changed identity.');
        }
        if (!\is_string($output)) {
            throw new \RuntimeException('The testcase output buffer is unavailable.');
        }
        $record = \json_encode(array('pid' => \getmypid(), 'nonce' => $owned['nonce'],
            'class' => \get_class($test), 'name' => $test->getName(true),
            'stdout_base64' => \base64_encode($output)), \JSON_THROW_ON_ERROR) . "\n";
        $stream = \fopen($owned['path'], 'ab');
        $held = \is_resource($stream) ? \fstat($stream) : false;
        if ($held === false || $held['dev'] !== $named['dev'] || $held['ino'] !== $named['ino']
            || !\flock($stream, \LOCK_EX)) {
            if (\is_resource($stream)) {
                \fclose($stream);
            }
            throw new \RuntimeException('Cannot open the original body-output capture.');
        }
        try {
            $offset = 0;
            while ($offset < \strlen($record)) {
                $written = \fwrite($stream, \substr($record, $offset));
                if ($written === false || $written === 0) {
                    throw new \RuntimeException('Cannot retain the complete body-output snapshot.');
                }
                $offset += $written;
            }
            if (!\fflush($stream)) {
                throw new \RuntimeException('Cannot flush the body-output snapshot.');
            }
        } finally {
            \flock($stream, \LOCK_UN);
            \fclose($stream);
        }
    }
}
