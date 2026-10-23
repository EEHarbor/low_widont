<?php

declare(strict_types=1);

namespace LowWidontTests;

final class PaginatingMcp extends \Low\Widont\FluxCapacitor\Base\Mcp
{
    public $data = [];

    /**
     * Exercise the shared paginator from a normal addon controller method.
     *
     * @param ModelQuery $query
     * @param string|null $url
     * @return ModelQuery
     */
    public function listing($query, $url = null)
    {
        return $this->paginateModel($query, $url, 10, 3, 'p');
    }
}
