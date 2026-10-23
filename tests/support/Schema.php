<?php

declare(strict_types=1);

namespace LowWidontTests;

final class Schema
{
    public $calls = [];
    private $database;
    private $fields = [];

    /**
     * Share schema changes with the isolated Active Record connection.
     *
     * @param Database $database
     * @return void
     */
    public function __construct(Database $database)
    {
        $this->database = $database;
    }

    /**
     * Record the column declarations used for the next table creation.
     *
     * @param array $fields
     * @return void
     */
    public function add_field(array $fields)
    {
        $this->fields = $fields;
        $this->calls[] = ['add_field', $fields];
    }

    /**
     * Record primary and secondary index declarations.
     *
     * @param string|array $key
     * @param bool $primary
     * @return void
     */
    public function add_key($key, $primary = false)
    {
        $this->calls[] = ['add_key', $key, $primary];
    }

    /**
     * Make the newly created table visible to subsequent fixture queries.
     *
     * @param string $table
     * @return void
     */
    public function create_table($table)
    {
        $this->calls[] = ['create_table', $table];
        $this->database->tables[$table] = [];
        $this->database->columns[$table] = array_keys($this->fields);
    }

    /**
     * Apply the legacy ignore-site column upgrade.
     *
     * @param string $table
     * @param array $fields
     * @return void
     */
    public function add_column($table, array $fields)
    {
        $this->calls[] = ['add_column', $table, $fields];
        $this->database->columns[$table] = array_merge($this->database->columns[$table] ?? [], array_keys($fields));
    }
}
