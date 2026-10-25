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
        $options = self::commandOptions($command);
        foreach ($options['inline'] as $operand) {
            $index = $operand['index'];
            $offset = $operand['offset'];
            $command[$index] = \substr($command[$index], 0, $offset)
                . self::guardInline(\substr($command[$index], $offset));
        }
        $callerPrelude = '';
        if (!$options['compile_only'] && !$options['inline']) {
            // Ask the selected native configuration; -r deliberately ignores its prelude.
            $discovery = self::runCommand(array_merge(array(PHP_BINARY), $options['settings'],
                array('-r', 'echo base64_encode(ini_get("auto_prepend_file"));')),
                $directory, $environment, $timeout);
            if ($discovery[0] !== 0 || $discovery[2] !== '') {
                return $discovery;
            }
            $callerPrelude = \base64_decode($discovery[1], true);
            if ($callerPrelude === false || \base64_encode($callerPrelude) !== $discovery[1]) {
                throw new \RuntimeException('Native prelude configuration was not captured exactly.');
            }
        }
        $temporary = sys_get_temp_dir() . '/low-widont-process-' . bin2hex(random_bytes(8));
        mkdir($temporary, 0700);
        $prepend = __DIR__ . '/Diagnostics.php';
        $preludeCode = null;
        if ($callerPrelude !== '' && \strcasecmp($callerPrelude, 'none') !== 0) {
            $prepend = $temporary . '/caller-prepend.php';
            $preludeCode = '<?php require_once ' . \var_export(__DIR__ . '/Diagnostics.php', true)
                . '; require ' . \var_export($callerPrelude, true) . ';';
        }
        $strict = array('-d', 'error_reporting=-1', '-d', 'display_errors=stderr',
            '-d', 'display_startup_errors=1', '-d', 'log_errors=0', '-d', 'html_errors=0');
        if (!$options['inline']) {
            $strict = array_merge($strict, array('-d', 'auto_prepend_file=' . $prepend));
        }
        array_splice($command, $options['boundary'], 0, $strict);
        $id = bin2hex(random_bytes(8));
        Diagnostics::record(array('kind' => 'native-start', 'id' => $id, 'command' => $command, 'temporary' => $temporary,
            'cwd' => $directory, 'case' => Diagnostics::currentCase(), 'compile_only' => $options['compile_only']));
        $environment = $environment === null ? getenv() : $environment;
        $environment['LOW_WIDONT_TEST_CASE'] = Diagnostics::currentCase() ?: '';
        $process = null;
        $completed = false;
        try {
            if ($preludeCode !== null && file_put_contents($prepend, $preludeCode) !== strlen($preludeCode)) {
                throw new \RuntimeException('Cannot retain the owned caller prelude.');
            }
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
            foreach (array('stdout', 'stderr', 'caller-prepend.php') as $stream) {
                if (is_file($temporary . '/' . $stream)) {
                    unlink($temporary . '/' . $stream);
                }
            }
            rmdir($temporary);
            Diagnostics::record(array('kind' => 'native-cleanup', 'id' => $id, 'completed' => $completed,
                'removed' => !file_exists($temporary)));
        }
    }
    /**
     * Find native execution operands and retain the original INI selection options.
     *
     * @param array $command Selected binary followed by native CLI arguments.
     * @return array Option boundary, configuration operands and actual execution mode.
     */
    private static function commandOptions(array $command): array
    {
        $boundary = \count($command);
        $lint = false;
        $execute = false;
        $inline = array();
        $settings = array();
        $longValues = array('--php-ini', '--define', '--zend-extension', '--file', '--run',
            '--process-begin', '--process-code', '--process-file', '--process-end', '--docroot',
            '--server', '--repeat', '--rf', '--rc', '--re', '--rz', '--ri');
        for ($index = 1; $index < \count($command); $index++) {
            $option = $command[$index];
            if ($option === '--' || $option === '' || $option === '-' || $option[0] !== '-') {
                $boundary = $index;
                break;
            }
            if (\substr($option, 0, 2) === '--') {
                $name = \explode('=', $option, 2)[0];
                if ($name === '--syntax-check') {
                    $lint = true;
                }
                if (!\in_array($name, $longValues, true)) {
                    continue;
                }
                $argumentIndex = $index;
                $valueOffset = \strpos($option, '=');
                if ($valueOffset === false) {
                    if ($index + 1 === \count($command)) {
                        $boundary = $index;
                        break;
                    }
                    $argumentIndex = ++$index;
                    $valueOffset = 0;
                } else {
                    $valueOffset++;
                }
                if (\in_array($name, array('--php-ini', '--define', '--zend-extension'), true)) {
                    $settings[] = $name;
                    $settings[] = \substr($command[$argumentIndex], $valueOffset);
                }
                if (\in_array($name, array('--run', '--process-begin', '--process-code', '--process-end'), true)) {
                    $execute = true;
                    $inline[] = array('index' => $argumentIndex, 'offset' => $valueOffset);
                }
                if ($name === '--process-file') {
                    $execute = true;
                }
                continue;
            }
            for ($offset = 1; $offset < \strlen($option); $offset++) {
                $flag = $option[$offset];
                if ($flag === 'n') {
                    $settings[] = '-n';
                }
                if ($flag === 'l') {
                    $lint = true;
                }
                if (\strpos('cdzfrBRFEtS', $flag) === false) {
                    continue;
                }
                $argumentIndex = $index;
                $valueOffset = $offset + 1;
                if ($valueOffset === \strlen($option)) {
                    if ($index + 1 === \count($command)) {
                        $boundary = $index;
                        break 2;
                    }
                    $argumentIndex = ++$index;
                    $valueOffset = 0;
                }
                if (\strpos('cdz', $flag) !== false) {
                    $settings[] = '-' . $flag;
                    $settings[] = \substr($command[$argumentIndex], $valueOffset);
                }
                if (\strpos('rBRE', $flag) !== false) {
                    $execute = true;
                    $inline[] = array('index' => $argumentIndex, 'offset' => $valueOffset);
                }
                if ($flag === 'F') {
                    $execute = true;
                }
                break;
            }
        }
        return array('boundary' => $boundary, 'compile_only' => $lint && !$execute,
            'inline' => $inline, 'settings' => $settings);
    }

    /**
     * Start authority before inline statements while retaining native header syntax.
     *
     * @param string $code Original native inline program.
     * @return string Program with the owned guard after legal leading declarations.
     */
    private static function guardInline($code): string
    {
        $tokens = \token_get_all('<?php ' . $code);
        $significant = array();
        $position = -6;
        foreach ($tokens as $token) {
            $text = \is_array($token) ? $token[1] : $token;
            $id = \is_array($token) ? $token[0] : null;
            $position += \strlen($text);
            if (\in_array($id, array(\T_OPEN_TAG, \T_WHITESPACE, \T_COMMENT, \T_DOC_COMMENT), true)) {
                continue;
            }
            $significant[] = array('id' => $id, 'text' => $text, 'end' => $position);
        }
        $index = 0;
        $boundary = 0;
        while (isset($significant[$index]) && $significant[$index]['id'] === \T_DECLARE) {
            $cursor = $index + 1;
            $depth = 0;
            for (; isset($significant[$cursor]); $cursor++) {
                $text = $significant[$cursor]['text'];
                if ($text === '(') {
                    $depth++;
                }
                if ($text === ')') {
                    $depth--;
                }
                if ($depth === 0 && ($text === ';' || $text === '{')) {
                    break;
                }
            }
            if (!isset($significant[$cursor]) || $significant[$cursor]['text'] !== ';') {
                break;
            }
            $boundary = $significant[$cursor]['end'];
            $index = $cursor + 1;
        }
        if (isset($significant[$index]) && $significant[$index]['id'] === \T_NAMESPACE
            && isset($significant[$index + 1]) && $significant[$index + 1]['text'] !== '\\') {
            for ($cursor = $index + 1; isset($significant[$cursor]); $cursor++) {
                if ($significant[$cursor]['text'] === ';' || $significant[$cursor]['text'] === '{') {
                    $boundary = $significant[$cursor]['end'];
                    break;
                }
            }
        }
        foreach ($significant as $index => $token) {
            if ($token['end'] <= $boundary) {
                continue;
            }
            if (\in_array($token['id'], array(\T_REQUIRE, \T_REQUIRE_ONCE), true)
                && isset($significant[$index + 1], $significant[$index + 2])
                && $significant[$index + 1]['text'] === \var_export(__DIR__ . '/Diagnostics.php', true)
                && $significant[$index + 2]['text'] === ';') {
                return $code;
            }
            break;
        }
        return \substr($code, 0, $boundary) . 'require_once '
            . \var_export(__DIR__ . '/Diagnostics.php', true) . ';' . \substr($code, $boundary);
    }
}
