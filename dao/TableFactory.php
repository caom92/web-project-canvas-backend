<?php

namespace DataBase;

require_once realpath(__DIR__."/../../config/db.php");


class TableFactory {
  private $baseNamespace;
  private $dbConnection;
  private $cachedTables = [];
  private $tableClassDefinitionFilePaths = [];
  
  function __construct(
    $baseNamespace, $profileName, $tablestableClassDefinitionFilePaths
  ) {
    $this->baseNamespace = $baseNamespace;
    $this->dbConnection = new \PDO(
      'mysql:host='.DB_PROFILES[$profileName]['host'].';'.
      'dbname='.DB_PROFILES[$profileName]['db'].';charset=utf8mb4',
      DB_PROFILES[$profileName]['user'],
      DB_PROFILES[$profileName]['password'],
      [
        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        \PDO::ATTR_EMULATE_PREPARES => FALSE,
        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC
      ]   
    );
    $this->tableClassDefinitionFilePaths = $tablestableClassDefinitionFilePaths;
  }

  function isDataBaseConnectionEstablished() {
    return isset($this->dbConnection);
  }

  function get($tableClass) {
    if (!$this->isTableCached($tableClass)) {
      if ($this->hasDefinitionFilePath($tableClass)) {
        if (strlen($this->tableClassDefinitionFilePaths[$tableClass]) == 0) {
          throw new \Exception(
            "Failed to create an instance of '$tableClass', ".
            "the file path associated to this class name could not be ".
            "resolved to any file. Maybe the file path is misspelled.",
            200
          );
        }

        require_once $this->tableClassDefinitionFilePaths[$tableClass];
        $tableClass = $this->baseNamespace.$tableClass;
        $this->cachedTables[$tableClass] = new $tableClass($this->dbConnection);
      } else {
        throw new \Exception(
          "Failed to create an instance of '$tableClass', no class definition "
          ."file is associated to this class name. Maybe the class name is "
          ."misspelled.",
          200
        );
      }
    }

    return $this->cachedTables[$tableClass];
  }

  private function isTableCached($tableClass) {
    return
      isset($this->cachedTables[$tableClass])
      && array_key_exists($tableClass, $this->cachedTables);
  }

  private function hasDefinitionFilePath($tableClass) {
    return 
      isset($this->tableClassDefinitionFilePaths[$tableClass])
      && array_key_exists($tableClass, $this->tableClassDefinitionFilePaths);
  }
}

?>