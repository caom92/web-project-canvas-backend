<?php

namespace Core\Validations;
require_once realpath(__DIR__.'/Validator.php');
use \Exception;


class NumberValidator implements Validator 
{
  // Override Validator
  function execute($modules, $name, $value, $attributes) {
    if (!isNumeric($value)) {
      throw new Exception(
        "Input argument '$name' is not a numeric value.",
        -301
      );
    }
  }
}

class IntegerValidator implements Validator 
{
  // Override Validator
  function execute($modules, $name, $value, $attributes) {
    $min = (isset($attributes['min'])) ? 
      $attributes['min'] : INT_MIN;
    $max = (isset($attributes['max'])) ? 
      $attributes['max'] : PHP_INT_MAX;

    if (!isInteger($value, $min, $max)) {
      throw new Exception(
        "Input argument '$name' is not an integer value within [$min, $max]",
        -302
      );
    }
  }
}

class FloatValidator implements Validator 
{
  // Override Validator
  function execute($modules, $name, $value, $attributes) {
    if (!isFloat($value)) {
      throw new Exception(
        "Input argument '$name' is not a floating-point value.",
        -303
      );
    }
  }
}

class BooleanValidator implements Validator 
{
  // Override Validator
  function execute($modules, $name, $value, $attributes) {
    if (!isBoolean($value)) {
      throw new Exception(
        "Input argument '$name' is not a boolean value",
        -304
      );
    }
  }
}

class StringValidator implements Validator 
{
  // Override Validator
  function execute($modules, $name, $value, $attributes) {
    $hasLengthAttribute = isset($attributes['length']);
    $hasMinLengthAttribute = isset($attributes['min_length']);
    $hasMaxLengthAttribute = isset($attributes['max_length']);

    if ($hasLengthAttribute) {
      $minLength = $maxLength = $attributes['length'];
    } else {
      $minLength = ($hasMinLengthAttribute) ? $attributes['min_length'] : 0;
      $maxLength = ($hasMaxLengthAttribute) ? 
        $attributes['max_length'] : PHP_INT_MAX;
    }

    if (!isString($value, $minLength, $maxLength)) {
      throw new Exception(
        "Input argument '$name' is not a string with character length within "
        ."[$minLength, $maxLength]",
        -305
      );
    }
  }
}

class EmailValidator implements Validator 
{
  // Override Validator
  function execute($modules, $name, $value, $attributes) {
    if (!isEmailString($value)) {
      throw new Exception(
        "Input argument '$name' is not an email string.",
        -306
      );
    }
  }
}

class PhoneValidator implements Validator 
{
  // Override Validator
  function execute($modules, $name, $value, $attributes) {
    if (!isPhoneNumber($value)) {
      throw new Exception(
        "Input argument '$name' is not a phone number.",
        -307
      );
    }
  }
}

class DateTimeValidator implements Validator 
{
  // Override Validator
  function execute($modules, $name, $value, $attributes) {
    if (!isDateTime($value, $attributes['format'])) {
      throw new Exception(
        "Input argument '$name' is not a date and/or time literal of ".
        "format '{$attributes['format']}'",
        -308
      );
    }
  }
}

class FilesValidator implements Validator 
{
  // Override Validator
  function execute($modules, $name, $value, $attributes) {
    $filename = $attributes['filename'];
    $wasFileReceived = 
      isset($value[$filename]) 
      && array_key_exists($filename, $value);
    $isOptional =
      isset($attributes['optional'])
      && array_key_exists('optional', $attributes);
    $hasFormatAttribute = 
      isset($attributes['format'])
      && array_key_exists('format', $attributes);

    if (!$wasFileReceived && !$isOptional) {
      throw new Exception("File '$filename' is undefined.", -309);
    }

    if ($hasFormatAttribute) {
      switch ($attributes['format']) {
        case 'document':
          $this->validateFileFormat(
            $value[$filename], 'document', function($filename) {
              return isPdfFile($filename);
            }
          );
          break;

        case 'bitmap':
        $this->validateFileFormat(
          $value[$filename], 'bitmap', function($filename) {
            return isBitmapFile($filename);
          }
        );
          break;
      }
    } else {
      $this->validateFileFormat(
        $value[$filename], 'bitmap nor a document', function($filename) {
          $isBitmap = isBitmapFile($filename);
          $isPdf = isPdfFile($filename);
          return $isBitmap || $isPdf;
        }
      );
    }
  }

  private function validateFileFormat($files, $format, $validator) {
    if (is_array($files)) {
      foreach ($files as $file) {
        $filename = $file->file;
        $clientFilename = $file->getClientFilename();
        $errorCode = $file->getError();

        if ($errorCode !== \UPLOAD_ERR_OK) {
          throw new Exception(
            "Failed to upload file '$clientFilename'", $errorCode
          );
        } else if (!$validator($filename)) {
          throw new Exception(
            "File '$clientFilename' is not a $format file", -310
          );
        }
      }
    } else {
      $filename = $files->file;
      $clientFilename = $files->getClientFilename();
      $errorCode = $files->getError();

      if ($errorCode !== \UPLOAD_ERR_OK) {
        throw new Exception(
          "Failed to upload file '$clientFilename'", $errorCode
        );
      } else if (!$validator($filename)) {
        throw new Exception(
          "File '$clientFilename' is not a $format file", -310
        );
      }
    }
  }
}

// Se decidio duplicar la funcion Service::validateInputData y el arreglo 
// Service::validators debido a que estos se invocan de forma recursiva al 
// validar arreglos, pero, como estan declarados en Service y Service importa 
// ArrayValidator, se genera una dependencia circular que no puede resolverse. 
// La unica alternativa es que ArrayValidator tenga su propia copia de estos 
// atributos, que es lo que hacemos aqui
class ArrayValidator implements Validator 
{
  private $validators;
  
  function setValidators($validators) {
    $this->validators = $validators;
  }

  // Override Validator
  function execute($modules, $name, $value, $attributes) {
    $isOptional = 
      isset($attributes['optional']) 
      && array_key_exists('optional', $attributes);

    $isSimpleArray = 
      isset($attributes['values']['type'])
      && array_key_exists('type', $attributes['values']);

    $length = count($value);
    if ($length > 0) {
      if ($isSimpleArray) {
        $validatorIdx = $attributes['type'];
        $validator = $this->$validators[$validatorIdx];

        for ($i = 0; $i < $length; $i++) {
          $validator->execute(
            $modules, "$name[$i]", $value[$i], $attributes['values']
          );
        }
      } else {
        foreach ($value as $element) {
          $this->validateArrayElement(
            $modules, $element, $attributes['values']
          );
        }
      }
    } else if (!$isOptional) {
      throw new Exception("Input argument '$name' is an empty array", -312);
    }
  }

  private function validateArrayElement(
    $modules, $element, $elementDescriptor
  ) {
    foreach ($elementDescriptor as $name => $attributes) {
      $isOptional = 
        isset($attributes['optional'])
        && array_key_exists('optional', $attributes);
      $hasTypeAttribute = 
        isset($attributes['type'])
        && array_key_exists('type', $attributes);
      $wasValueProvided = 
        isset($element[$name])
        && array_key_exists($name, $element);
      $isFileType = $attributes['type'] == 'files';

      if ($wasValueProvided) {
        if ($hasTypeAttribute) {
          $validatorIdx = $attributes['type'];
          $value = $element[$name];
        } else {
          $validatorIdx = $name;
          $value = NULL;
        }
      } else if (!$isOptional && !$isFileType) {
        throw new Exception("Input value '$name' is undefined.", -300);
      }

      $validator = $this->validators[$validatorIdx];
      $validator->execute($modules, $name, $value, $attributes);
    }
  }
}

?>