<?php

namespace Core\Utilities;

/*
El siguiente código pretende abstraer un patrón específico que surge muy 
seguido al trabajar con los proyectos de Jacobs Farm/Del Cabo; a este patron le 
llamo "categorías lineales". Las categorías lineales son una estructura de 
datos que surge en la base de datos donde se tiene una tabla que esta 
relacionada a uno o mas hijos, formando una jerarquía lineal similar a una 
lista enlazada. Un ejemplo puede perse con las bitácoras, donde se tienen 
programas hasta el tope (GMP o GAP), los cuales pueden contener cada uno una 
cantidad arbitraria de módulos (Packing, Fields, etc.), y cáda módulo puede 
contener una cantidad arbitraria de bitácoras (Pre-Op Inspection, Thermometers, 
etc.). Esto crea una jerarquía lineal que va así: Programa -> Módulo -> 
Bitácora. Cuando se pide a la base de datos listar todos los elementos de esta 
jerarquía, lo devuelve en forma de tabla, siguiendo una estructura similar a la 
siguiente:

  | Programa | Módulo | Bitácora |
  |----------|--------|----------|
  |    P1    |    M1  |    B1    |
  |    P1    |    M1  |    B2    |
  |    P1    |    M2  |    B3    |
  |    P1    |    M2  |    B4    |
  |    P2    |    M3  |    B5    |

Y así sucesivamente. Sin embargo, al cliente le es más conveniente recibir 
estos datos estructurados en un JSON que tenga una forma similar a la siguiente:

  [
    {
      name: P1,
      modules: [
        {
          name: M1,
          logs: [
            { name: B1 },
            { name: B2 }
          ]
        },
        {
          name: M2,
          logs: [
            { name: B3 },
            { name: B4 }
          ]
        }
      ]
    },
    {
      name: P2,
      modules: [
        {
          name: M3,
          logs: [
            { name: B5 }
          ]
        }
      ]
    }
  ]

El código mostrado en este archivo describe la rutina que permite convertir los 
datos en forma de tabla en el JSON deseado y al mismo tiempo, ofrece una 
interfaz sencilla con la cual se puede describir de forma declarativa las 
características de la categoría lineal que se va a traducir. 
*/


// [in]  rows (dictionary): los datos de las categorías lineales obtenidos de 
//       la base de datos y organizados en renglones y columnas
// [in]  hierarchyDescriptor (dictionary): la descripción de la jerarquía de    
//       las categorías lineales a traducir. Cada elemento del descriptor debe 
//       tener los siguientes valores:
//       * idColumn (string): el nombre de la columna que alberga el id de la 
//         categoría 
//       * parseDescriptor (dictionary): descriptor que define cómo se van a 
//         traducir los valores de la tabla al JSON; cada llave del decriptor 
//         corresponde al nombre de cada columna de la tabla y cada valor 
//         corresponde al nombre de cada llave del JSON para cada valor 
//         respectivo. Adicionalmente, se puede declarar un campo con '@' al 
//         principio de su llave para indicar el nombre de la llave en el JSON 
//         que corresponde al arreglo donde se albergaran los hijos de esta 
//         categoría
//       * [@name] (string): el nombre de la llave donde se almacenaran los 
//         datos de la categoría en el padre una vez que se hayan leído 
//         completamente de la base de datos
//       * [child] (dictionary): el descriptor de jerarquía correspondiente a 
//         la categoría hija
//       Un ejemplo de un descriptor de jerarquía apropiado sería el siguiente:
//
//        [
//          'idColumn' => 'program_id',
//          'parseDescriptor' => [
//            'program_id' => 'id',
//            'program_name' => 'name',
//            '@modules' => []
//          ],
//          'child' => [
//            '@name' => 'modules',
//            'idColumn' => 'module_id',
//            'parseDescriptor' => [
//              'module_id' => 'id',
//              'module_name' => 'name',
//              '@sections' => []
//            ],
//            'child' => [
//              '@name' => 'sections',
//              'idColumn' => 'section_id',
//              'parseDescriptor' => [
//                'section_id' => 'id',
//                'section_name' => 'name'
//              ]
//            ]
//          ]
//        ]
function parseCategoryHierarchyFromTableRows($rows, $hierarchyDescriptor)
{
  $head = getCategoryHierarchy($hierarchyDescriptor);
  foreach ($rows as $row)
    $head->parse($row);
  $head->storeLastValue();
  return $head->getParent();
}

function getCategoryHierarchy($hierarchyDescriptor) 
{
  $hasChild = 
    isset($hierarchyDescriptor['child'])
    && array_key_exists('child', $hierarchyDescriptor);

  if ($hasChild) {
    $isAlsoAChild =
      isset($hierarchyDescriptor['@name'])
      && array_key_exists('@name', $hierarchyDescriptor);

    $child = getCategoryHierarchy($hierarchyDescriptor['child']);
    $parent = new ContinuingCategory(
      $hierarchyDescriptor['idColumn'],
      ($isAlsoAChild) ? $hierarchyDescriptor['@name'] : NULL,
      $hierarchyDescriptor['parseDescriptor'],
      $child
    );
    return $parent;
  } else {
    return new EndingCategory(
      $hierarchyDescriptor['idColumn'], 
      $hierarchyDescriptor['@name'],
      $hierarchyDescriptor['parseDescriptor']
    );
  }
}

function parse($data, $descriptor) 
{
  $parsedData = [];
  foreach ($descriptor as $inputKey => $outputKey) {
    $reverseIndicatorStart = strpos($inputKey, '@');
    if ($reverseIndicatorStart === FALSE) {
      $parsedData[$outputKey] = $data[$inputKey];
    } else {
      $key = substr($inputKey, $reverseIndicatorStart + 1);
      $parsedData[$key] = $outputKey;
    }
  }
  return $parsedData;
}


class ContinuingCategory extends CategoryNode
{
  private $child;

  function __construct($idColumn, $parentIdx, $parseDescriptor, &$child) {
    parent::__construct($idColumn, $parentIdx, $parseDescriptor);
    $this->child = $child;
    $this->child->setParent($this);
  }

  // Override CategoryNode
  function parse($row) {
    if ($this->hasValueChanged($row)) {
      $this->storeLastValue();
      $this->updateLastValue($row);
    } else {
      $this->child->parse($row); 
    }
  }

  // Override CategoryNode
  function storeLastValue() {
    if ($this->isLastValueValid()) {
      $this->child->storeLastValue();
      $this->pushLastValueToParent();
    }
  }

  // Override CategoryNode
  protected function updateLastValue($row) {
    $this->child->updateLastValue($row);
    parent::updateLastValue($row);
    $this->child->setParent($this);
  }
}


class EndingCategory extends CategoryNode
{
  function __construct($idColumn, $parentIdx, $parseDescriptor) {
    parent::__construct($idColumn, $parentIdx, $parseDescriptor);
  }

  // Override CategoryNode
  function parse($row) {
    if ($this->hasValueChanged($row)) {
      $this->storeLastValue();
      $this->updateLastValue($row);
    }
  }

  // Override CategoryNode
  function storeLastValue() {
    if ($this->isLastValueValid())
      $this->pushLastValueToParent();
  }
}


abstract class CategoryNode
{
  private $parent;
  private $idColumn;
  private $parentIdx;
  private $lastValue;
  private $parseDescriptor;
  
  function __construct($idColumn, $parentIdx, $parseDescriptor) {
    $this->parent = [];
    $this->idColumn = $idColumn;
    $this->parentIdx = $parentIdx;
    $this->lastValue = [ 'id' => 0 ];
    $this->parseDescriptor = $parseDescriptor;
  }

  abstract function parse($row);
  abstract function storeLastValue();

  function getParent() {
    return $this->parent;
  }

  protected function setParent($category) {
    $this->parent = &$category->lastValue;
  }

  protected function hasValueChanged($row) {
    return $row[$this->idColumn] != $this->lastValue['id'];
  }

  protected function updateLastValue($row) {
    $this->lastValue = parse($row, $this->parseDescriptor);
  }

  protected function isLastValueValid() {
    return $this->lastValue['id'] > 0;
  }

  protected function pushLastValueToParent() {
    if (isset($this->parentIdx))
      array_push($this->parent[$this->parentIdx], $this->lastValue);
    else
      array_push($this->parent, $this->lastValue);
  }
}

?>