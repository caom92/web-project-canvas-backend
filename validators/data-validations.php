<?php

namespace Core\Validations;

function equalsValue($data, $value)
{
  if (isset($data)) {
    return $data === $value;
  }
  return false;
}

function isNumeric($data)
{
  return is_numeric($data);
}

const INT_MIN = ~PHP_INT_MAX;
function isInteger($data, $min = INT_MIN, $max = PHP_INT_MAX)
{
  return filter_var(
    $data,
    FILTER_VALIDATE_INT,
    [ 'options' => [
      'min_range' => $min,
      'max_range' => $max
    ]]
  ) !== FALSE;
}

function isFloat($data)
{
  return filter_var(
    $data,
    FILTER_VALIDATE_FLOAT,
    FILTER_FLAG_ALLOW_THOUSAND
  ) !== FALSE;
}

function isString($data, $minLength = 0, $maxLength = PHP_INT_MAX)
{
  if (is_string($data)) {
    $currentLength = strlen($data);
    return $minLength <= $currentLength && $currentLength <= $maxLength;
  }
  return false;
}

function isEmailString($data)
{
  return filter_var($data, FILTER_VALIDATE_EMAIL) !== FALSE;
}

function isPdfFile($data)
{
  if (isset($data)) {
    $fileInfo = new \finfo();
    $fileType = $fileInfo->file($data);
    $pos = \strpos($fileType, 'PDF');
    return ($pos !== FALSE);
  }
  return false;
}

function isBitmapFile($data)
{
  if (isset($data)) {
    $fileType = exif_imagetype($data);
    $isJpeg = $fileType === IMAGETYPE_JPEG;
    $isPng = $fileType === IMAGETYPE_PNG;
    $isGif = $fileType === IMAGETYPE_GIF;
    $isBmp = $fileType === IMAGETYPE_BMP;
    return ($isJpeg || $isPng || $isGif || $isBmp);
  }
  return false;
}

function isDateTime($data, $format)
{
  $dateTime = \DateTime::createFromFormat($format, $data);
  return $dateTime !== FALSE;
}

function isBoolean($data)
{
  if (is_bool($data)) {
    return true;
  }

  if (isInteger($data)) {
    $intData = intval($data);
    return isInteger($intData, 0, PHP_INT_MAX);
  }

  if (isString($data)) {
    return
      $data === 'true'  ||
      $data === 'false' ||
      $data === 'TRUE'  ||
      $data === 'FALSE' ||
      $data === '0'     ||
      $data === '1';
  }

  return false;
}

function isPhoneNumber($data)
{
  $phone = strtolower($data);

  // remueve guiones, espacios, parentesis, puntos y cadenas ext y Ext
  $phone = preg_replace('/\(|\)|\s|\.|\-|ext|Ext|EXT/', '', $phone);

  // revisa si la cadena contiene numeros con un + opcional al principio
  // solamente
  return preg_match_all('/^\+\d{7,15}$|^\d{7,16}$/', $phone) === 1;
}

?>