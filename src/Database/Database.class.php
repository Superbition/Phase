<?php

namespace Polyel\Database;

use Closure;
use Polyel\Database\Query\QueryBuilder;

class Database implements DatabaseInteraction
{
    private $dbManager;

    public function __construct(DatabaseManager $dbManager)
    {
        $this->dbManager = $dbManager;
    }

    public function raw($query, $data = null, $type = 'write', $database = null)
    {
        return $this->dbManager->execute($type, $query, $data, false, $database);
    }

    public function select($query, $data = null, $database = null)
    {
        return $this->raw($query, $data, "read", $database);
    }

    public function insert($query, $data = null, $database = null)
    {
        return $this->raw($query, $data, "write", $database);
    }

    public function update($query, $data = null, $database = null)
    {
        return $this->raw($query, $data, "write", $database);
    }

    public function delete($query, $data = null, $database = null)
    {
        return $this->raw($query, $data, "write", $database);
    }

    /*
     * This function allows you to setup an auto-commit transaction.
     * Allows you to perform raw SQL statements and also use the same
     * query builder like normal, difference being, the same connection is
     * used throughout the entire transaction and is a write-only connection.
     */
    public function transaction(Closure $callback, $attempts = 1, $database = null)
    {
        // Grab a write-only connection to be used for our transaction
        $connection = $this->dbManager->getConnection('write', $database);

        // Setup a new transaction instance, setting the connection to use and the max attempts to re-try
        $transaction = new Transaction($connection, $attempts, false);

        // Initiate and run the callback to perform the transaction statements...
        $callbackResult = $transaction->run($callback);

        // Finally after the transaction has completed or failed, return the connection to its pool
        $this->dbManager->returnConnection($connection);

        unset($transaction);

        return $callbackResult;
    }

    /*
     * This function allows you to setup a manual transaction callback.
     * Allowing you to perform raw SQL statements and also use the same
     * query builder like normal, difference being, the same connection is
     * used throughout the entire transaction and is a write-only connection.
     * You must use the transaction functions start(), rollBack() & commit() to
     * perform transactional operations yourself, you are in control when in manual mode.
     */
    public function manualTransaction(Closure $callback, $database = null)
    {
        // Grab a write-only connection to be used for our transaction
        $connection = $this->dbManager->getConnection('write', $database);

        // Setup a new transaction instance, setting the connection to use and the max attempts to re-try
        $transaction = new Transaction($connection, 1, true);

        // Initiate and run the callback to perform the transaction statements...
        $callbackResult = $transaction->run($callback);

        // Finally after the transaction has completed or failed, return the connection to its pool
        $this->dbManager->returnConnection($connection);

        unset($transaction);

        return $callbackResult;
    }

    public function connection($database, $table)
    {
        return $this->table($table, $database);
    }

    public function table($table, $database = null)
    {
        $queryBuilder = new QueryBuilder($this->dbManager);

        if(!is_null($database))
        {
            $queryBuilder->setDatabase($database);

            $prefix = config("database.connections.$database.prefix");
        }
        else
        {
            $defaultDatabase = config('database.default');
            $prefix = config("database.connections.$defaultDatabase.prefix");
        }

        return $queryBuilder->from($table, $prefix);
    }
}