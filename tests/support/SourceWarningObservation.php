<?php

declare(strict_types=1);

namespace LowWidontTests;

final class SourceWarningObservation
{
    private $source;
    private $sha256;
    private $diagnostics = [];

    /**
     * Limit recoverable observation to one verified immutable source file.
     *
     * @param string $source
     * @param string $sha256
     * @return void
     * @throws \RuntimeException
     */
    public function __construct($source, $sha256)
    {
        if (hash_file('sha256', $source) !== $sha256) {
            throw new \RuntimeException('Observation source digest differs.');
        }
        $this->source = $source;
        $this->sha256 = $sha256;
    }

    /**
     * Emit every source diagnostic immediately and retain PHP's native continuation.
     *
     * @param int $severity
     * @param string $message
     * @param string $file
     * @param int $line
     * @return bool
     * @throws \ErrorException
     * @throws \RuntimeException
     */
    public function __invoke($severity, $message, $file, $line)
    {
        if ($file !== $this->source || hash_file('sha256', $file) !== $this->sha256) {
            throw new \ErrorException($message, 0, $severity, $file, $line);
        }
        $diagnostic = ['severity' => $severity, 'message' => $message, 'file' => $file, 'line' => $line,
            'source_sha256' => $this->sha256, 'masked_by_source' => ($severity & error_reporting()) === 0];
        $this->diagnostics[] = $diagnostic;
        $emitted = json_encode(['source_diagnostic' => $diagnostic], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
        if (fwrite(STDERR, $emitted) !== strlen($emitted)) {
            throw new \RuntimeException('Cannot emit the observed source diagnostic.');
        }
        return false;
    }

    /**
     * Return the complete ordered diagnostic list for an explicitly red observation.
     *
     * @return array
     */
    public function diagnostics(): array
    {
        return $this->diagnostics;
    }
}
