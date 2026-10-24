<?php

namespace LowWidontTests;

final class NativeProcess
{
    /**
     * Retain native streams and diagnostics without changing the caller's context.
     *
     * @param array $command
     * @param string|null $directory
     * @param array|null $environment
     * @param float $timeout
     * @return array
     * @throws \RuntimeException When launch, lifetime or capture fails.
     */
    public static function runCommand(array $command, $directory = null, $environment = null, $timeout = 20)
    {
        if ($timeout <= 0 || !$command || $command[0] !== PHP_BINARY) {
            throw new \RuntimeException('Native probes require the selected PHP binary and a positive deadline.');
        }
        $temporary = sys_get_temp_dir() . '/low-widont-process-' . bin2hex(random_bytes(8));
        mkdir($temporary, 0700);
        array_splice($command, 1, 0, array('-d', 'error_reporting=-1', '-d', 'display_errors=stderr',
            '-d', 'display_startup_errors=1', '-d', 'log_errors=0', '-d', 'html_errors=0',
            '-d', 'auto_prepend_file=' . __DIR__ . '/Diagnostics.php'));
        $id = bin2hex(random_bytes(8));
        Diagnostics::record(array('kind' => 'native-start', 'id' => $id, 'command' => $command, 'temporary' => $temporary,
            'cwd' => $directory, 'case' => Diagnostics::currentCase(), 'compile_only' => in_array('-l', $command, true)));
        $environment = $environment === null ? getenv() : $environment;
        $environment['LOW_WIDONT_TEST_CASE'] = Diagnostics::currentCase() ?: '';
        $process = null;
        $completed = false;
        try {
            $process = proc_open($command, array(0 => array('pipe', 'r'), 1 => array('file', $temporary . '/stdout', 'w'),
                2 => array('file', $temporary . '/stderr', 'w')), $pipes, $directory, $environment);
            if (!is_resource($process)) {
                throw new \RuntimeException('Cannot launch the owned native probe.');
            }
            fclose($pipes[0]);
            $status = proc_get_status($process);
            Diagnostics::record(array('kind' => 'native-spawn', 'id' => $id, 'pid' => $status['pid']));
            $deadline = hrtime(true) + $timeout * 1000000000;
            while ($status['running']) {
                if (hrtime(true) >= $deadline) {
                    throw new \RuntimeException('Owned native probe exceeded its deadline.');
                }
                usleep(10000);
                $status = proc_get_status($process);
            }
            $closed = proc_close($process);
            $process = null;
            $exit = $status['exitcode'] === -1 ? $closed : $status['exitcode'];
            $stdout = file_get_contents($temporary . '/stdout');
            $stderr = file_get_contents($temporary . '/stderr');
            Diagnostics::record(array('kind' => 'native-end', 'id' => $id, 'pid' => $status['pid'], 'exit' => $exit,
                'stdout_base64' => base64_encode($stdout), 'stderr_base64' => base64_encode($stderr)));
            $completed = true;
            return array($exit, $stdout, $stderr);
        } finally {
            if (is_resource($process)) {
                proc_terminate($process, 9);
                proc_close($process);
            }
            if (!$completed) {
                Diagnostics::record(array('kind' => 'native-aborted', 'id' => $id,
                    'stdout_base64' => is_file($temporary . '/stdout') ? base64_encode(file_get_contents($temporary . '/stdout')) : null,
                    'stderr_base64' => is_file($temporary . '/stderr') ? base64_encode(file_get_contents($temporary . '/stderr')) : null));
            }
            foreach (array('stdout', 'stderr') as $stream) {
                if (is_file($temporary . '/' . $stream)) {
                    unlink($temporary . '/' . $stream);
                }
            }
            rmdir($temporary);
            Diagnostics::record(array('kind' => 'native-cleanup', 'id' => $id, 'completed' => $completed,
                'removed' => !file_exists($temporary)));
        }
    }
}
