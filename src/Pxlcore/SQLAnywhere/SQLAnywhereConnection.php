<?php namespace Pxlcore\SQLAnywhere;

use Throwable;
use Illuminate\Database\Connection;
use Illuminate\Support\Str;
use \Pxlcore\SQLAnywhereClient;

class SQLAnywhereConnection extends Connection {

	/**
	 * Create a new database connection instance.
	 *
	 * @param  PDO     $pdo
	 * @param  string  $database
	 * @param  string  $tablePrefix
	 * @param  array   $config
	 * @return void
	 */
	public function __construct(SQLAnywhereClient $pdo, $database = '', $tablePrefix = '', array $config = array())
	{
		$this->pdo = $pdo;

		// First we will setup the default properties. We keep track of the DB
		// name we are connected to since it is needed when some reflective
		// type commands are run such as checking whether a table exists.
		$this->database = $database;

		$this->tablePrefix = $tablePrefix;

		$this->config = $config;

		// We need to initialize a query grammar and the query post processors
		// which are both very important parts of the database abstractions
		// so we initialize these to their default values while starting.
		$this->useDefaultQueryGrammar();
		$this->useDefaultPostProcessor();
	}

	/**
	 * Run a select statement against the database.
	 *
	 * @param  string  $query
	 * @param  array   $bindings
	 * @param  $useReadPdo = true
	 * @return array
	 */
	public function select($query, $bindings = array(), $useReadPdo = true)
	{
		// new version since Laravel 5.4
		// /vendor/laravel/framework/src/Illuminate/Database/Connection.php
		//  --> function: select(...)
		return $this->run($query, $bindings, function($query, $bindings)
		{
			if ($this->pretending()) return array();

			// For select statements, we'll simply execute the query and return an array
			// of the database result set. Each element in the array will be a single
			// row from the database table, and will either be an array or objects.
			$statement = $this->getReadPdo()->prepare($query);
			
			$statement->execute($this->prepareBindings($bindings));

			return $statement->fetchAll();
		});
	}

	/**
	 * Run an SQL statement and get the number of rows affected.
	 *
	 * @param  string  $query
	 * @param  array   $bindings
	 * @return int
	 */
	public function affectingStatement($query, $bindings = array())
	{
		return $this->run($query, $bindings, function($query, $bindings)
		{
			if ($this->pretending()) return 0;

			// For update or delete statements, we want to get the number of rows affected
			// by the statement and return that back to the developer. We'll first need
			// to execute the statement and then we'll use PDO to fetch the affected.
			$statement = $this->getPdo()->prepare($query);

			$statement->execute($this->prepareBindings($bindings));

			return $statement->affectedRows();
		});
	}

	/**
	 * Get the default query grammar instance.
	 *
	 * @return Illuminate\Database\Query\Grammars\Grammars\Grammar
	 */
	protected function getDefaultQueryGrammar()
	{
        return $this->withTablePrefix(new SQLAnywhereQueryGrammar);
	}

	/**
	 * Get the default schema grammar instance.
	 *
	 * @return Illuminate\Database\Schema\Grammars\Grammar
	 */
	protected function getDefaultSchemaGrammar()
	{
        return $this->withTablePrefix(new SQLAnywhereSchemaGrammar);
	}

	/**
     * Determine if the given exception was caused by a lost connection.
     *
     * @param  \Throwable  $e
     * @return bool
     */
    protected function causedByLostConnection(Throwable $e)
    {
        $message = $e->getMessage();
        
        return Str::contains($message, [
        	'Authentication violation',
            'server has gone away',
            'no connection to the server',
            'Lost connection',
            'is dead or not enabled',
            'Error while sending',
            'decryption failed or bad record mac',
            'server closed the connection unexpectedly',
            'SSL connection has been closed unexpectedly',
            'Error writing data to the connection',
            'Resource deadlock avoided',
            'Transaction() on null',
            'child connection forced to terminate due to client_idle_limit',
            'query_wait_timeout',
            'reset by peer',
            'Physical connection is not usable',
            'TCP Provider: Error code 0x68',
            'Name or service not known',
            'Not connected to a database'
        ]);
    }
}
