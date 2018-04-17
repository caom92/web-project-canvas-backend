<?php

namespace Tests;

class TestSuite
{
  private $servicePath;
  private $unitTests;

  function __construct($servicePath, $unitTests) {
    $this->servicePath = $servicePath;
    $this->unitTests = $unitTests;
  }

  public function runTests($modules) {
    echo "<h1>{$this->servicePath}</h1>\n<ul>\n";
    $service = NULL;
    require_once $this->servicePath;
    foreach ($this->unitTests as $test) {
      $test->run($service, $modules);
    }
    echo "</ul>\n";
  }
}

?>