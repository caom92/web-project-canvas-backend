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
    // TODO: usar la interfaz de Slim en lugar de $_FILES
    // https://www.slimframework.com/docs/v3/objects/request.html#uploaded-files
    // https://www.slimframework.com/docs/v3/cookbook/uploading-files.html
    $filename = $attributes['name'];
    $wasFileReceived = 
      isset($_FILES[$filename]) 
      && array_key_exists($filename, $_FILES);
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
          $this->validateDocumentFile($filename);
        break;

        case 'bitmap':
          $this->validateBitmapFile($filename);
        break;
      }
    }
  }

  private function validateDocumentFile($filename) {
    if (is_array($_FILES[$filename])) {
      foreach ($_FILES[$filename]['tmp_name'] as $file) {
        if (!isPdfFile($file)) {
          throw new Exception(
            "A file in '$filename' is not a document file",
            -310
          );
        }
      }
    } else if (!isPdfFile($_FILES[$filename]['tmp_name'])) {
      throw new Exception(
        "The file '{$_FILES[$filename]['name']}' is not a document file",
        -310
      );
    }
  }

  private function validateBitmapFile($filename) {
    if (is_array($_FILES[$filename]['tmp_name'])) {
      foreach ($_FILES[$filename]['tmp_name'] as $file) {
        if (!isBitmapFile($file)) {
          throw new Exception(
            "A file in '$name' is not a bitmap file",
            -311
          );
        }
      }
    } else if (!isBitmapFile($_FILES[$filename]['tmp_name'])) {
      throw new Exception(
        "The file '{$_FILES[$filename]['name']}' is not a bitmap file",
        -311
      );
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