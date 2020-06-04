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

    private function execute($type, $query, $data)
    {
        return $this->dbManager->execute($type, $query, $data);
    }

    public function select($query, $data = null)
    {
        return $this->execute("read", $query, $data);
    }

    public function insert($query, $data = null)
    {
        return $this->execute("write", $query, $data);
    }

    public function update($query, $data = null)
    {
        return $this->execute("write", $query, $data);
    }

    public function delete($query, $data = null)
    {
        return $this->execute("write", $query, $data);
    }

    public function raw($statement, $data = null, $type = 'write')
    {
        return $this->execute($type, $statement, $data);
    }

    public function table($table)
    {

        $queryBuilder = new QueryBuilder($this->dbManager);

        return $queryBuilder->from($table);
    }
}