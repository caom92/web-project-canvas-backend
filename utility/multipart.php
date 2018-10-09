<?php

namespace Core\Utilities;
require_once realpath(__DIR__.'/../../vendor/autoload.php');
use \Riverline\MultiPartParser\Part;


function getParsedMultipartInput() {
  $rawHttpRequest = 
    $_SERVER['SERVER_PROTOCOL'].' '.
    $_SERVER['REQUEST_METHOD'].' '.
    $_SERVER['REQUEST_URI'].
    PHP_EOL;

  foreach (getallheaders() as $key => $value) {
    $rawHttpRequest .= trim($key).': '.trim($value).PHP_EOL;
  }

  $rawHttpRequest .= PHP_EOL.file_get_contents('php://input');

  $outputData = NULL;
  $formData = new Part($rawHttpRequest);
  if ($formData->isMultiPart()) {
    foreach ($formData->getParts() as $part) {
      $outputData[$part->getName()] = $part->getBody();
    }
  } 

  return $outputData;
}

?>