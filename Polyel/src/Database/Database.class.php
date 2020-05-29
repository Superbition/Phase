<?php

namespace Polyel\Database;

class Database
{
    private $dbManager;

    private $queryBuilder;

    public function __construct(DatabaseManager $dbManager, QueryBuilder $queryBuilder)
    {
        $this->dbManager = $dbManager;
        $this->queryBuilder = $queryBuilder;
    }

    public function table($table)
    {
        return $this->queryBuilder->from($table);
    }
}