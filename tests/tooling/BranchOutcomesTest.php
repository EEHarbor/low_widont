<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/fixtures/BranchOutcomes.php';

final class BranchOutcomesTest extends PHPUnit\Framework\TestCase
{
    /**
     * Execute distinct paths to reveal lossy outgoing-counter aggregation.
     *
     * @dataProvider limits
     * @param int $limit
     * @return void
     */
    public function testControlFlow($limit): void
    {
        $this->assertSame($limit, low_widont_branch_fixture($limit));
    }

    /**
     * Keep the breaking loop path before the condition-exit path.
     *
     * @return array
     */
    public function limits(): array
    {
        return [[2], [1]];
    }
}
