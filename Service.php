<?php

namespace Core;

require_once realpath(__DIR__.'/validators/default-validators.php');


Service::initDefaultValidators();

// Se decidio que modules tuviera que ser proveido explicitamente como 
// argumento de los metodos de esta clase en lugar de hacerlo un atributo de la 
// misma porque, como la creacion de la instancia de esta clase esta oculta 
// (vea el cuerpo de ServiceProvider::executeService), no queremos que el 
// lector se confunda y tenga que buscar en varios archivos para saber de 
// donde proviene exactamente la instancia que se provee en modules
abstract class Service {
  private static $validators;
  private $inputDataDescriptor;

  function __construct($inputDataDescriptor) {
    $this->inputDataDescriptor = $inputDataDescriptor;
  }

  public abstract function execute($modules, $data);

  public static function addInputDataValidator($name, $validator) {
    self::$validators[$name] = $validator;
  }

  public static function initDefaultValidators() {
    self::$validators = [
      'number' => new \Core\Validators\NumberValidator(),
      'int' => new \Core\Validators\IntegerValidator(),
      'float' => new \Core\Validators\FloatValidator(),
      'bool' => new \Core\Validators\BooleanValidator(),
      'string' => new \Core\Validators\StringValidator(),
      'email' => new \Core\Validators\EmailValidator(),
      'phone' => new \Core\Validators\PhoneValidator(),
      'datetime' => new \Core\Validators\DateTimeValidator(),
      'array' => new \Core\Validators\ArrayValidator(),
      'files' => new \Core\Validators\FilesValidator()
    ];

    self::$validators['array']->setValidators(self::$validators);
  }

  public function validateInputData($modules, $data) {
    foreach ($inputDataDescriptor as $inputField => $attributes) {
      $isOptional = 
        isset($attributes['optional'])
        && array_key_exists('optional', $attributes);
      $hasTypeAttribute = 
        isset($attributes['type'])
        && array_key_exists('type', $attributes);
      $wasInputValueProvided = 
        isset($data[$inputField])
        && array_key_exists($inputField, $data);
      $isFileType = $attributes['type'] == 'files';

      if ($wasInputValueProvided) {
        if ($hasTypeAttribute) {
          $validatorIdx = $attributes['type'];
          $inputValue = $data[$inputField];
        } else {
          $validatorIdx = $inputField;
          $inputValue = NULL;
        }
      } else if (!$isOptional && !$isFileType) {
        throw new \Exception("Input value '$inputField' is undefined.", 101);
      }

      $validator = self::$validators[$validatorIdx];
      $validator->execute($modules, $inputField, $inputValue, $attributes);
    }
  }
}

?>