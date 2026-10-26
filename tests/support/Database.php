<?php

declare(strict_types=1);

namespace LowWidontTests;

final class Database
{
    public $data_cache = ['table_names' => ['stale']];
    public $calls = [];
    public $tables;
    public $columns = [];
    public $primaryKeys = [['Column_name' => 'license_key', 'Non_unique' => '0']];
    public $dbprefix = 'exp_';
    private $conditions = [];
    private $selectedTable;
    private $storage;

    /**
     * Seed isolated records keyed by unprefixed EE table name.
     *
     * @param array $tables
     * @return void
     */
    public function __construct(array $tables = [])
    {
        $this->storage = (object) ['tables' => $tables];
        $this->tables = &$this->storage->tables;
    }

    /**
     * Create a fresh DI query builder sharing this fixture's database connection.
     *
     * @return self
     */
    public function newQuery()
    {
        $query = clone $this;
        $query->conditions = [];
        $query->selectedTable = null;
        $query->calls = [];
        $query->data_cache = [];
        return $query;
    }

    /**
     * Record selected columns while retaining complete rows for this query double.
     *
     * @param string $columns
     * @return self
     */
    public function select($columns)
    {
        $this->calls[] = ['select', $columns];
        return $this;
    }

    /**
     * Add one equality condition using EE's Active Record method signature.
     *
     * @param string $column
     * @param mixed $value
     * @return self
     */
    public function where($column, $value)
    {
        $this->calls[] = ['where', $column, $value];
        $parts = explode('.', $column);
        $this->conditions[end($parts)] = $value;
        return $this;
    }

    /**
     * Retrieve matching rows and clear pending query conditions.
     *
     * @param string $table
     * @param int|null $limit
     * @return Result
     */
    public function get($table = null, $limit = null)
    {
        $table = $table === null ? $this->selectedTable : $table;
        $this->selectedTable = null;
        $this->calls[] = ['get', $table, $limit];
        $conditions = $this->conditions;
        $this->conditions = [];
        $rows = array_values(array_filter($this->tables[$table] ?? [], static function ($row) use ($conditions) {
            foreach ($conditions as $key => $value) {
                if (!array_key_exists($key, $row) || $row[$key] != $value) {
                    return false;
                }
            }
            return true;
        }));
        return new Result($limit === null ? $rows : array_slice($rows, 0, $limit));
    }

    /**
     * Select the table consumed by a subsequent argument-free query.
     *
     * @param string $table
     * @return self
     */
    public function from($table)
    {
        $this->selectedTable = $table;
        $this->calls[] = ['from', $table];
        return $this;
    }

    /**
     * Check the explicit fixture schema rather than assuming a populated record.
     *
     * @param string $field
     * @param string $table
     * @return bool
     */
    public function field_exists($field, $table)
    {
        $this->calls[] = ['field_exists', $field, $table];
        return in_array($field, $this->columns[$table] ?? [], true);
    }

    /**
     * Return SHOW KEYS metadata only for a table with the configured EE prefix.
     *
     * @param string $sql
     * @return Result
     * @throws \RuntimeException
     */
    public function query($sql)
    {
        $this->calls[] = ['query', $sql];
        if ($sql !== "SHOW KEYS FROM " . $this->dbprefix . "eeharbor_licenses WHERE Key_name = 'PRIMARY'") {
            throw new \RuntimeException('Unexpected physical database table in query: ' . $sql);
        }
        return new Result($this->primaryKeys);
    }

    /**
     * Fetch rows with an explicit equality map.
     *
     * @param string $table
     * @param array $conditions
     * @param int|null $limit
     * @return Result
     */
    public function get_where($table, array $conditions, $limit = null)
    {
        foreach ($conditions as $column => $value) {
            $this->where($column, $value);
        }
        return $this->get($table, $limit);
    }

    /**
     * Report table existence using the fixture schema.
     *
     * @param string $table
     * @return bool
     */
    public function table_exists($table)
    {
        $this->calls[] = ['table_exists', $table];
        if (isset($this->data_cache['table_names'])) {
            return in_array($table, $this->data_cache['table_names'], true);
        }
        return array_key_exists($table, $this->tables);
    }

    /**
     * Count rows and reset query state as CodeIgniter Active Record does.
     *
     * @param string $table
     * @return int
     */
    public function count_all_results($table)
    {
        return $this->get($table)->num_rows();
    }

    /**
     * Insert a record and expose exact write arguments for assertions.
     *
     * @param string $table
     * @param array $data
     * @return bool
     */
    public function insert($table, array $data)
    {
        $this->calls[] = ['insert', $table, $data];
        $this->tables[$table][] = $data;
        return true;
    }

    /**
     * Update matching rows and reset query conditions.
     *
     * @param string $table
     * @param array $data
     * @param array $where
     * @return bool
     */
    public function update($table, array $data, array $where = [])
    {
        $this->calls[] = ['update', $table, $data, $where];
        $conditions = array_merge($this->conditions, $where);
        $this->conditions = [];
        foreach ($this->tables[$table] as &$row) {
            if (array_intersect_assoc($row, $conditions) == $conditions) {
                $row = array_merge($row, $data);
            }
        }
        unset($row);
        return true;
    }

    /**
     * Delete matching rows and reset query conditions.
     *
     * @param string $table
     * @param array $where
     * @return bool
     */
    public function delete($table, array $where = [])
    {
        $this->calls[] = ['delete', $table, $where];
        $conditions = array_merge($this->conditions, $where);
        $this->conditions = [];
        $this->tables[$table] = array_values(array_filter($this->tables[$table] ?? [], static function ($row) use ($conditions) {
            return array_intersect_assoc($row, $conditions) != $conditions;
        }));
        return true;
    }
}

final class Result
{
    public $num_rows;
    private $rows;

    /**
     * Store rows using the same property and method count shapes exposed by EE.
     *
     * @param array $rows
     * @return void
     */
    public function __construct(array $rows)
    {
        $this->rows = $rows;
        $this->num_rows = count($rows);
    }

    /**
     * Count result records.
     *
     * @return int
     */
    public function num_rows()
    {
        return $this->num_rows;
    }

    /**
     * Retrieve the first record or one field using the legacy database contract.
     *
     * @param string|null $column
     * @return mixed
     */
    public function row($column = null)
    {
        if (!$this->rows) {
            return null;
        }
        return $column === null ? (object) $this->rows[0] : ($this->rows[0][$column] ?? null);
    }

    /**
     * Retrieve an associative first record, including an empty result.
     *
     * @return array
     */
    public function row_array()
    {
        return $this->rows[0] ?? [];
    }

    /**
     * Retrieve result records as legacy row objects.
     *
     * @return array
     */
    public function result()
    {
        return array_map(static function ($row) { return (object) $row; }, $this->rows);
    }

    /**
     * Retrieve all selected records as associative rows.
     *
     * @return array
     */
    public function result_array()
    {
        return $this->rows;
    }
}
