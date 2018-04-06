<?php

namespace Core;
require_once realpath(__DIR__.'/validators/default-validators.php');


// Se decidio que modules tuviera que ser proveido explicitamente como 
// argumento de los metodos de esta clase en lugar de hacerlo un atributo de la 
// misma porque, como la creacion de la instancia de esta clase esta oculta 
// (vea el cuerpo de ServiceProvider::executeService), no queremos que el 
// lector se confunda y tenga que buscar en varios archivos para saber de 
// donde proviene exactamente la instancia que se provee en modules
abstract class Service 
{
  private static $validators;
  private $inputDataDescriptor;
  
  function __construct($inputDataDescriptor) {
    $this->inputDataDescriptor = $inputDataDescriptor;
  }

  static function initDefaultValidators() {
    self::$validators = [
      'number' => new \Core\Validations\NumberValidator(),
      'int' => new \Core\Validations\IntegerValidator(),
      'float' => new \Core\Validations\FloatValidator(),
      'bool' => new \Core\Validations\BooleanValidator(),
      'string' => new \Core\Validations\StringValidator(),
      'email' => new \Core\Validations\EmailValidator(),
      'phone' => new \Core\Validations\PhoneValidator(),
      'datetime' => new \Core\Validations\DateTimeValidator(),
      'array' => new \Core\Validations\ArrayValidator(),
      'files' => new \Core\Validations\FilesValidator()
    ];

    self::$validators['array']->setValidators(self::$validators);
  }  

  static function addInputDataValidator($name, $validator) {
    self::$validators[$name] = $validator;
  }
  
  abstract function execute($modules, $data);

  function validateInputData($modules, $data) {
    foreach ($this->inputDataDescriptor as $inputField => $attributes) {
      $wasInputValueProvided = 
        isset($data[$inputField]) && array_key_exists($inputField, $data);

      $hasTypeAttribute = 
        isset($attributes['type']) && array_key_exists('type', $attributes);
      
      $isOptional = 
        isset($attributes['optional'])
        && array_key_exists('optional', $attributes);

      $isFileType = ($hasTypeAttribute) ? 
        $attributes['type'] == 'files' : FALSE;

      if ($hasTypeAttribute) {
        if ($wasInputValueProvided) {
          $inputValue = $data[$inputField];
          $validatorIdx = $attributes['type'];  
        } else if (!$isOptional && !$isFileType) {
          throw new \Exception("Input value '$inputField' is undefined.", -101);
        }
      } else {
        $validatorIdx = $inputField;
        $inputValue = NULL;
      }

      $validator = self::$validators[$validatorIdx];
      $validator->execute($modules, $inputField, $inputValue, $attributes);
    }
  }
}

Service::initDefaultValidators();

?>