<?php

namespace DataBase;

abstract class Table { 
  private $tableName;
  private $dbConnection;
  private $cachedQueries = [];
 
  function __construct($dbConnection, $tableName) {
    $this->dbConnection = $dbConnection;
    $this->tableName = $tableName;
  }

  protected function getTableName() {
    return $this->tableName;
  }

  protected function getDataBaseConnection() {
    return $this->dbConnection;
  }

  protected function getStatement($query) {
    if (!$this->isStatementCached($query)) {
      $this->cachedQueries[$query] = $this->dbConnection->prepare($query);
    }
    return $this->cachedQueries[$query];
  }

  private function isStatementCached($query) {
    return 
      isset($this->cachedQueries[$query])
      && array_key_exists($query, $this->cachedQueries);
  }
}

?>