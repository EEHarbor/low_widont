<?php

declare(strict_types=1);

namespace LowWidontTests;

final class SourceWarningObservation
{
    private $source;
    private $sha256;
    private $diagnostics = [];

    /**
     * Verify the observed source while retaining strict diagnostic rejection.
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
     * Retain and reject diagnostics before their exceptions can be caught.
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
        return call_user_func_array(Diagnostics::handler(), func_get_args());
    }

    /**
     * Return the retained legacy observation field; strict diagnostics throw first.
     *
     * @return array
     */
    public function diagnostics(): array
    {
        return $this->diagnostics;
    }
}
