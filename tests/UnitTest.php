<?php

namespace Tests;

class UnitTest 
{
  private $description;
  private $arrangeCallback;
  private $assertCallback;

  function __construct($description, $arrangeCallback, $assertCallback) {
    $this->description = $description;
    $this->arrangeCallback = $arrangeCallback;
    $this->assertCallback = $assertCallback;
  }

  public function run($service, $modules) {
    $arrange = $this->arrangeCallback;
    $assert = $this->assertCallback; 
    
    $inputData = $arrange();
    $result = NULL;
    try {
      $result = $this->act($service, $modules, $inputData);
    } catch (\Exception $e) {
      $result = $this->handleException($e);
    } finally {
      $assertResult = ($assert($result)) ? 
        "<span style='color:green'>Pasó</span>" 
        : "<b style='color:red'>Falló</b>";
      echo "<li>{$this->description}: $assertResult</li>\n";
    }
  }

  private function act($service, $modules, $inputData) {
    $service->validateInputData($modules, $inputData);
    return $service->execute($modules, $inputData);
  }

  private function handleException($exception) {
    return $exception->getCode();
  }
}

?>