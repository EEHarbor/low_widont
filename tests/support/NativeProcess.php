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
        $encodedTool = \get_cfg_var('low_widont.phpunit_tool');
        $scriptIndex = $options['script']['index'] ?? null;
        $scriptOffset = $options['script']['offset'] ?? 0;
        if (!$options['compile_only'] && !$options['execute'] && $scriptIndex !== null && isset($command[$scriptIndex])
            && \realpath(\substr($command[$scriptIndex], $scriptOffset))
            === \realpath(\dirname(__DIR__) . '/.phpunit/phpunit-9.phar')) {
            $tool = \is_string($encodedTool) ? \json_decode(\base64_decode($encodedTool, true), true, 512, \JSON_THROW_ON_ERROR) : null;
            $path = \is_array($tool) ? ($tool['target'] ?? '') : '';
            $named = \is_string($path) && \is_file($path) && !\is_link($path) ? \lstat($path) : false;
            if ($named === false || $named['dev'] !== ($tool['device'] ?? null)
                || $named['ino'] !== ($tool['inode'] ?? null) || $named['nlink'] !== 1
                || ($named['mode'] & 0777) !== 0600 || \hash_file('sha256', $path) !== ($tool['target_sha256'] ?? null)
                || \hash_file('sha256', __DIR__ . '/BodyOutput.php') !== ($tool['hook_sha256'] ?? null)) {
                throw new \RuntimeException('The native PHPUnit output-capture tool changed identity.');
            }
            $command[$scriptIndex] = \substr($command[$scriptIndex], 0, $scriptOffset) . $path;
        }

        foreach ($options['inline'] as $operand) {
            // Native begin code precedes line/end programs regardless of argument order.
            if ($options['begin'] !== null && $operand['index'] !== $options['begin']) {
                continue;
            }
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
        $id = bin2hex(random_bytes(8));
        $auxiliary = array();
        try {
            foreach (array('engine', 'case-stdout') as $name) {
                $handle = fopen($temporary . '/' . $name, 'x+b');
                if (is_resource($handle)) {
                    $auxiliary[$name] = $handle;
                }
                if ($handle === false || !chmod($temporary . '/' . $name, 0600)) {
                    throw new \RuntimeException('Cannot allocate the private native engine/body capture.');
                }
            }
        } catch (\Throwable $error) {
            foreach ($auxiliary as $name => $handle) {
                fclose($handle);
                unlink($temporary . '/' . $name);
            }
            rmdir($temporary);
            throw $error;
        }
        $engineIdentity = lstat($temporary . '/engine');
        $caseIdentity = lstat($temporary . '/case-stdout');
        $caseCapability = base64_encode(json_encode(array('path' => $temporary . '/case-stdout',
            'device' => $caseIdentity['dev'], 'inode' => $caseIdentity['ino'], 'nonce' => $id), JSON_THROW_ON_ERROR));
        $prepend = __DIR__ . '/Diagnostics.php';
        $preludeCode = null;
        if ($callerPrelude !== '' && \strcasecmp($callerPrelude, 'none') !== 0) {
            $prepend = $temporary . '/caller-prepend.php';
            $preludeCode = '<?php require_once ' . \var_export(__DIR__ . '/Diagnostics.php', true)
                . '; require_once ' . \var_export($callerPrelude, true) . ';';
        }
        $strict = array('-d', 'error_reporting=-1', '-d', 'display_errors=stderr',
            '-d', 'display_startup_errors=1', '-d', 'log_errors=0', '-d', 'error_log=' . $temporary . '/engine',
            '-d', 'low_widont.case_stdout="' . $caseCapability . '"',
            '-d', 'low_widont.phpunit_tool="' . (\is_string($encodedTool) ? $encodedTool : '') . '"', '-d', 'html_errors=0');
        if (!$options['inline']) {
            $strict = array_merge($strict, array('-d', 'auto_prepend_file=' . $prepend));
        }
        array_splice($command, $options['boundary'], 0, $strict);
        Diagnostics::record(array('kind' => 'native-start', 'id' => $id, 'command' => $command, 'temporary' => $temporary,
            'cwd' => $directory, 'case' => Diagnostics::currentCase(), 'compile_only' => $options['compile_only'],
            'engine_identity' => $engineIdentity, 'case_stdout_identity' => $caseIdentity));
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
            foreach (array('engine' => $engineIdentity, 'case-stdout' => $caseIdentity) as $name => $identity) {
                clearstatcache(true, $temporary . '/' . $name);
                $named = lstat($temporary . '/' . $name);
                if ($named === false || is_link($temporary . '/' . $name) || $named['dev'] !== $identity['dev']
                    || $named['ino'] !== $identity['ino'] || $named['nlink'] !== 1 || ($named['mode'] & 0777) !== 0600) {
                    throw new \RuntimeException('The native engine/body capture changed identity.');
                }
            }
            rewind($auxiliary['engine']);
            rewind($auxiliary['case-stdout']);
            $engineBytes = stream_get_contents($auxiliary['engine']);
            $caseBytes = stream_get_contents($auxiliary['case-stdout']);
            Diagnostics::record(array('kind' => 'native-end', 'id' => $id, 'pid' => $status['pid'], 'exit' => $exit,
                'stdout_base64' => base64_encode($stdout), 'stderr_base64' => base64_encode($stderr),
                'engine_base64' => base64_encode($engineBytes),
                'case_stdout_base64' => base64_encode($caseBytes),
                'engine_identity' => lstat($temporary . '/engine'),
                'case_stdout_identity' => lstat($temporary . '/case-stdout')));
            $completed = true;
            return array($exit, $stdout, $stderr);
        } finally {
            if (is_resource($process)) {
                proc_terminate($process, 9);
                proc_close($process);
            }
            if (!$completed) {
                rewind($auxiliary['engine']);
                rewind($auxiliary['case-stdout']);
                $engineBytes = stream_get_contents($auxiliary['engine']);
                $caseBytes = stream_get_contents($auxiliary['case-stdout']);
                Diagnostics::record(array('kind' => 'native-aborted', 'id' => $id,
                    'stdout_base64' => is_file($temporary . '/stdout') ? base64_encode(file_get_contents($temporary . '/stdout')) : null,
                    'stderr_base64' => is_file($temporary . '/stderr') ? base64_encode(file_get_contents($temporary . '/stderr')) : null,
                    'engine_base64' => base64_encode($engineBytes),
                    'case_stdout_base64' => base64_encode($caseBytes)));
            }
            foreach ($auxiliary as $handle) {
                fclose($handle);
            }
            foreach (array('stdout', 'stderr', 'engine', 'case-stdout', 'caller-prepend.php') as $stream) {
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
        $script = null;
        $lint = false;
        $execute = false;
        $inline = array();
        $begin = null;
        $settings = array();
        $longValues = array('--php-ini', '--define', '--zend-extension', '--file', '--run',
            '--process-begin', '--process-code', '--process-file', '--process-end', '--docroot',
            '--server', '--repeat', '--rf', '--rc', '--re', '--rz', '--ri');
        for ($index = 1; $index < \count($command); $index++) {
            $option = $command[$index];
            if ($option === '--' || $option === '' || $option === '-' || $option[0] !== '-') {
                $boundary = $index;
                if ($script === null) {
                    $script = array('index' => $option === '--' ? $index + 1 : $index, 'offset' => 0);
                }
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
                if ($name === '--file') {
                    $script = array('index' => $argumentIndex, 'offset' => $valueOffset);
                }
                if (\in_array($name, array('--php-ini', '--define', '--zend-extension'), true)) {
                    $settings[] = $name;
                    $settings[] = \substr($command[$argumentIndex], $valueOffset);
                }
                if (\in_array($name, array('--run', '--process-begin', '--process-code', '--process-end'), true)) {
                    $execute = true;
                    $inline[] = array('index' => $argumentIndex, 'offset' => $valueOffset);
                }
                if ($name === '--process-begin') {
                    $begin = $argumentIndex;
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
                if ($flag === 'f') {
                    $script = array('index' => $argumentIndex, 'offset' => $valueOffset);
                }
                if (\strpos('cdz', $flag) !== false) {
                    $settings[] = '-' . $flag;
                    $settings[] = \substr($command[$argumentIndex], $valueOffset);
                }
                if (\strpos('rBRE', $flag) !== false) {
                    $execute = true;
                    $inline[] = array('index' => $argumentIndex, 'offset' => $valueOffset);
                }
                if ($flag === 'B') {
                    $begin = $argumentIndex;
                }
                if ($flag === 'F') {
                    $execute = true;
                }
                break;
            }
        }
        return array('boundary' => $boundary, 'compile_only' => $lint && !$execute,
            'inline' => $inline, 'begin' => $begin, 'settings' => $settings, 'script' => $script, 'execute' => $execute);
    }

    /**
     * Start authority before live inline statements while retaining declaration and namespace syntax.
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
        $guard = 'require_once ' . \var_export(__DIR__ . '/Diagnostics.php', true) . ';';
        $insertions = array();
        $hasGuard = false;
        $scan = null;
        $scan = static function ($index, $limit, $boundary, $namespace) use (&$scan, &$insertions, &$hasGuard, $significant) {
            while ($index < $limit) {
                $token = $significant[$index];
                if ($token['text'] === ';') {
                    $boundary = $token['end'];
                    $index++;
                    continue;
                }
                if ($token['id'] === \T_DECLARE) {
                    $declaration = self::declaration($significant, $index, $limit);
                    if ($declaration === null) {
                        break;
                    }
                    if ($declaration['body'] !== null) {
                        $scan($declaration['body'], $declaration['close'],
                            $significant[$declaration['body'] - 1]['end'], false);
                        if ($hasGuard) {
                            return;
                        }
                    }
                    $boundary = $significant[$declaration['end']]['end'];
                    $index = $declaration['end'] + 1;
                    continue;
                }
                if ($namespace && $token['id'] === \T_NAMESPACE
                    && isset($significant[$index + 1]) && $significant[$index + 1]['text'] !== '\\') {
                    for ($cursor = $index + 1; $cursor < $limit; $cursor++) {
                        if ($significant[$cursor]['text'] === ';' || $significant[$cursor]['text'] === '{') {
                            $boundary = $significant[$cursor]['end'];
                            $index = $cursor + 1;
                            break 2;
                        }
                    }
                }
                break;
            }
            if ($index >= $limit) {
                if ($namespace) {
                    $insertions[] = $boundary;
                    $hasGuard = true;
                }
                return;
            }
            if (\in_array($significant[$index]['id'], array(\T_REQUIRE, \T_REQUIRE_ONCE), true)
                && isset($significant[$index + 1], $significant[$index + 2])
                && $significant[$index + 1]['text'] === \var_export(__DIR__ . '/Diagnostics.php', true)
                && $significant[$index + 2]['text'] === ';') {
                $hasGuard = true;
                return;
            }
            $insertions[] = $boundary;
            $hasGuard = true;
        };
        $scan(0, \count($significant), 0, true);
        $insertions = \array_unique($insertions);
        \rsort($insertions);
        foreach ($insertions as $boundary) {
            $code = \substr($code, 0, $boundary) . $guard . \substr($code, $boundary);
        }
        return $code;
    }

    /**
     * Find a leading declaration body without moving executable statements before it.
     *
     * @param array $tokens Significant native inline tokens with original byte offsets.
     * @param int $index Declaration keyword offset in the token array.
     * @param int $limit Exclusive enclosing declaration-body boundary.
     * @return array|null Body bounds and final token, or null for incomplete syntax.
     */
    private static function declaration(array $tokens, $index, $limit)
    {
        $depth = 0;
        for ($cursor = $index + 1; $cursor < $limit; $cursor++) {
            $text = $tokens[$cursor]['text'];
            if ($text === '(') {
                $depth++;
            }
            if ($text === ')') {
                $depth--;
            }
            if ($depth === 0 && \in_array($text, array(';', '{', ':'), true)) {
                break;
            }
        }
        if ($cursor === $limit) {
            return null;
        }
        if ($tokens[$cursor]['text'] === ';') {
            return array('body' => null, 'close' => null, 'end' => $cursor);
        }
        $body = $cursor + 1;
        $braced = $tokens[$cursor]['text'] === '{';
        $depth = 1;
        for ($cursor = $body; $cursor < $limit; $cursor++) {
            $token = $tokens[$cursor];
            if (!$braced && $token['id'] === \T_DECLARE) {
                $nested = self::declaration($tokens, $cursor, $limit);
                if ($nested === null) {
                    return null;
                }
                $cursor = $nested['end'];
                continue;
            }
            if ($braced && (($token['id'] === null && $token['text'] === '{')
                || \in_array($token['id'], array(\T_CURLY_OPEN, \T_DOLLAR_OPEN_CURLY_BRACES), true))) {
                $depth++;
            }
            if (($braced && $token['id'] === null && $token['text'] === '}') || (!$braced && $token['id'] === \T_ENDDECLARE)) {
                $depth--;
                if ($depth === 0) {
                    $end = !$braced && isset($tokens[$cursor + 1]) && $tokens[$cursor + 1]['text'] === ';'
                        ? $cursor + 1 : $cursor;
                    return array('body' => $body, 'close' => $cursor, 'end' => $end);
                }
            }
        }
        return null;
    }
}
