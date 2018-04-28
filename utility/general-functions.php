<?php

namespace Core\Utilities;

function arrayHasStringKeys($array) 
{
  $keys = array_keys($array);
  $stringKeys = array_filter($keys, 'is_string');
  return count($stringKeys) > 0;
}

// Retorna el nombre asignado al archivo una vez que fue almacenado en el 
// servidor
function storeUploadedFileInServer($file, $destinationDir) {
  $s = getFileSysSlash();
  $extension = \pathinfo($file->getClientFilename(), \PATHINFO_EXTENSION);
  $basename = uniqid(rand(), TRUE);
  $file->moveTo("$destinationDir$s$basename.$extension");
  return "$basename.$extension";
}

function getFileSysSlash() 
{
  $osName = substr(PHP_OS, 0, 3);
  $osName = strtoupper($osName);
  return ($osName === 'WIN') ? '\\' : '/';
}

function resetSessionId($session, $segment) 
{
  $userID = $segment->get('user_id');
  $segment->set('user_id', NULL);
  $session->regenerateId();
  $segment->set('user_id', $userID);
}

function getValueFromArrayIfExists($array, $key) {
  return (isset($array[$key]) && array_key_exists($key, $array)) ?
    $array[$key] : NULL;
}

?>