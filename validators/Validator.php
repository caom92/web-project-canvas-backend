<?php

namespace Core\Validations;
require_once realpath(__DIR__.'/data-validations.php');


// Se decidio que modules tuviera que ser proveido explicitamente como 
// argumento de los metodos de esta clase en lugar de hacerlo un atributo de la 
// misma porque, como la creacion de la instancia de esta clase esta oculta 
// (vea el cuerpo de ServiceProvider::executeService), no queremos que el 
// lector se confunda y tenga que buscar en varios archivos para saber de 
// donde proviene exactamente la instancia que se provee en modules
interface Validator 
{
  function execute($modules, $name, $value, $attributes);
}

?>