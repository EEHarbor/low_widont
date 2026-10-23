<?php

declare(strict_types=1);

namespace LowWidontTests;

final class ModelQuery
{
    public $limit;
    public $offset;
    private $total;

    /**
     * Seed the total result count exposed by a model query.
     *
     * @param int $total
     * @return void
     */
    public function __construct($total)
    {
        $this->total = $total;
    }

    /**
     * Return the complete collection without changing the original query.
     *
     * @return \ArrayObject
     */
    public function all()
    {
        return new \ArrayObject(array_fill(0, $this->total, null));
    }

    /**
     * Apply a result limit and preserve the query for fluent calls.
     *
     * @param int $limit
     * @return self
     */
    public function limit($limit)
    {
        $this->limit = $limit;
        return $this;
    }

    /**
     * Apply a result offset and preserve the query for fluent calls.
     *
     * @param int $offset
     * @return self
     */
    public function offset($offset)
    {
        $this->offset = $offset;
        return $this;
    }
}
