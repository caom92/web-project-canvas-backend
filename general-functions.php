<?php

namespace Core;

function arrayHasStringKeys($array) {
  $keys = array_keys($array);
  $stringKeys = array_filter($keys, 'is_string');
  return count($stringKeys) > 0;
}

// Retorna el nombre asignado al archivo una vez que fue almacenado en el 
// servidor
function storeUploadedFileInServer(
  $fileOriginalName, $fileTmpName, $destinationDir
) {
  $fileFormat = getFileFormatFromName($fileOriginalName);
  $uploadTimestamp = date('Y-m-d_H-i-s'); 
  $destinationFileName = "$uploadTimestamp.$fileFormat";
  $s = getFileSysSlash();
  $destinationFilePath = "$destinationDir$s$destinationFileName";

  $wasMoveSuccessful = move_uploaded_file($fileTmpName, $destinationFilePath);
  if (!$wasMoveSuccessful) {
    throw new \Exception(
      "Failed to store uploaded file: $fileOriginalName", 
      117
    );
  }

  return $destinationFileName;
}

function getFileSysSlash() {
  $osName = substr(PHP_OS, 0, 3);
  $osName = strtoupper($osName);
  return ($osName === 'WIN') ? '\\' : '/';
}

function getFileFormatFromName($filename) {
  $formatStartPos = strpos($filename, '.');
  return substr($filename, $formatStartPos + 1);
}

?>