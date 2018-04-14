<?php

namespace DataBase;

abstract class Table 
{ 
  private $tableName;
  private $dbConnection;
  private $cachedQueries = [];
  
  function __construct($dbConnection, $tableName) {
    $this->dbConnection = $dbConnection;
    $this->tableName = $tableName;

    if ($this->hasDataBaseUserRootPrivileges()) {
      $this->initTable();
    }
  }

  protected abstract function getCreationQuery();

  // virtual
  protected function getInitialDataInsertionQuery() {
    return "";
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

  private function hasDataBaseUserRootPrivileges() {
    $checkPrivilegesQuery = $this->getStatement("SHOW GRANTS FOR CURRENT_USER");
    $checkPrivilegesQuery->execute();
    $privileges = $checkPrivilegesQuery->fetchAll(\PDO::FETCH_BOTH);
    return strpos($privileges[0][0], "GRANT ALL PRIVILEGES") !== FALSE;
  }

  private function initTable() {
    $checkTableExistsQuery = 
      $this->getStatement("SHOW TABLES LIKE '{$this->tableName}'");
    $checkTableExistsQuery->execute();
    $rows = $checkTableExistsQuery->fetchAll();
    if (count($rows) == 0) {
      $this->createTable();  
      $this->insertInitialData();
    }
  }

  private function isStatementCached($query) {
    return 
      isset($this->cachedQueries[$query])
      && array_key_exists($query, $this->cachedQueries);
  }

  private function createTable() {
    $queryString = $this->getCreationQuery();
    if (strlen($queryString) > 0) {
      $creationQuery = $this->getStatement();
      $creationQuery->execute();
    }
  }

  private function insertInitialData() {
    $queryString = $this->getInitialDataInsertionQuery();
    if (strlen($queryString) > 0) {
      $initialDataInsertionQuery = $this->getStatement($queryString);
      $initialDataInsertionQuery->execute();
    }
  }
}

?>