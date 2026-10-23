<?php

declare(strict_types=1);

namespace LowWidontTests;

final class Evidence
{
    /**
     * Reject occupied outputs and aliases into shipped source before filesystem writes.
     *
     * @param string $path
     * @return void
     * @throws \RuntimeException
     */
    public static function assertFreshOutput($path)
    {
        if (!is_string($path) || $path === '' || $path[0] !== '/'
            || preg_match('~(?:^|/)\.\.?(?:/|$)~', $path)) {
            throw new \RuntimeException('Evidence requires an absolute path without dot segments.');
        }
        if (is_link($path) || file_exists($path)) {
            throw new \RuntimeException('Refusing to replace evidence: ' . $path);
        }
        $directory = dirname($path);
        while (!is_dir($directory)) {
            if (is_link($directory) || file_exists($directory)) {
                throw new \RuntimeException('Evidence parent is not a usable directory: ' . $directory);
            }
            $directory = dirname($directory);
        }
        $resolved = realpath($directory);
        $source = realpath(dirname(__DIR__, 2) . '/low_widont');
        if ($source === false || $resolved === false) {
            throw new \RuntimeException('Cannot resolve the evidence/source boundary.');
        }
        $sourceIdentity = stat($source);
        while (true) {
            $identity = stat($resolved);
            if ($identity['dev'] === $sourceIdentity['dev'] && $identity['ino'] === $sourceIdentity['ino']) {
                throw new \RuntimeException('Refusing to publish evidence within shipped source: ' . $path);
            }
            $parent = dirname($resolved);
            if ($parent === $resolved) {
                return;
            }
            $resolved = $parent;
        }
    }

    /**
     * Publish complete JSON atomically inside a newly allocated report directory.
     *
     * @param string $path
     * @param array $data
     * @return void
     * @throws \RuntimeException
     */
    public static function write($path, array $data)
    {
        self::assertFreshOutput($path);
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0700, true);
        }
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $temporary = tempnam(dirname($path), '.publishing-');
        try {
            if (file_put_contents($temporary, $json) !== strlen($json)) {
                throw new \RuntimeException('Incomplete evidence write: ' . $path);
            }
            if (!rename($temporary, $path)) {
                throw new \RuntimeException('Cannot publish evidence: ' . $path);
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }

    /**
     * Retain terminal native child coverage without modifying archive/source bytes.
     *
     * @param string $root
     * @param string $label
     * @return void
     */
    public static function nativeChild($root, $label)
    {
        $directory = getenv('LOW_WIDONT_CONTEXT_REPORT_DIR') ?: getenv('LOW_WIDONT_REPORT_DIR');
        if (!$directory || !function_exists('xdebug_start_code_coverage')) {
            return;
        }
        $prefixes = [$root . '/low_widont/', 'phar://' . $root . '/low_widont/low_widont.phar/'];
        xdebug_set_filter(XDEBUG_FILTER_CODE_COVERAGE, XDEBUG_PATH_INCLUDE, $prefixes);
        xdebug_start_code_coverage(XDEBUG_CC_UNUSED | XDEBUG_CC_DEAD_CODE | XDEBUG_CC_BRANCH_CHECK);
        register_shutdown_function(static function () use ($root, $label, $directory, $prefixes) {
            $source = [];
            foreach (xdebug_get_code_coverage() as $file => $coverage) {
                if (strpos($file, $prefixes[0]) !== 0 && strpos($file, $prefixes[1]) !== 0) {
                    continue;
                }
                $coverage['sha256'] = hash_file('sha256', $file);
                $source[$file] = $coverage;
            }
            xdebug_stop_code_coverage();
            $dispatch = null;
            if (class_exists('Low\\Widont\\FluxCapacitor\\Bridge\\EE', false)) {
                $class = new \ReflectionClass('Low\\Widont\\FluxCapacitor\\Bridge\\EE');
                $dispatch = ['parent' => $class->getParentClass()->getName(), 'constructor_start_line' => $class->getMethod('__construct')->getStartLine()];
            }
            self::write($directory . '/children/' . $label . '-' . bin2hex(random_bytes(4)) . '.json', [
                'mode' => $label, 'php' => PHP_VERSION, 'xdebug' => phpversion('xdebug'),
                'ee' => defined('APP_VER') ? APP_VER : null,
                'flags' => XDEBUG_CC_UNUSED | XDEBUG_CC_DEAD_CODE | XDEBUG_CC_BRANCH_CHECK,
                'archive_sha256' => hash_file('sha256', $root . '/low_widont/low_widont.phar'),
                'opcache_enable_cli' => ini_get('opcache.enable_cli'),
                'opcache_validate_permission' => ini_get('opcache.validate_permission'),
                'terminal_error' => error_get_last(), 'dispatch' => $dispatch, 'source' => $source,
            ]);
        });
    }
}
