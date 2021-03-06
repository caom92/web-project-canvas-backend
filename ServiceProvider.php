<?php

namespace Core;

require_once realpath(__DIR__.'/../config/site.php');
require_once realpath(__DIR__.'/../vendor/autoload.php');
require_once realpath(__DIR__.'/utility/multipart.php');

use \Slim\App;
use \Monolog\Logger;
use \Monolog\Handler\StreamHandler;
use \Aura\Session\SessionFactory;
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use \Exception;
use \Core\Utilities as util;


class ServiceProvider
{
  private $slimApp;

  function __construct($customModules, $serviceDefinitionFilePaths) {
    $this->initSlimApp();
    $this->initSlimAppHandlers();
    $this->initSlimAppModules($customModules);
    $this->initSlimAppMiddleware();
    $this->initSlimAppServices($serviceDefinitionFilePaths);
  }

  function serveRemoteClient() {
    $this->slimApp->run();
  }

  private static function parseVerboseValues($inputData) {
    $outputData = $inputData;
    if (isset($inputData)) {
      foreach ($inputData as $key => $value) {
        if ($value === 'null' || $value === 'NULL' || $value === 'undefined') {
          $outputData[$key] = NULL;
        }

        if ($value === 'true') {
          $outputData[$key] = TRUE;
        }

        if ($value === 'false') {
          $outputData[$key] = FALSE;
        }
      }
    }
    return $outputData;
  }

  private static function executeService(
    $slimAppModules, $filePath, $inputData
  ) {
    $isFilePathValid = isset($filePath) && strlen($filePath) > 0;
    if (!$isFilePathValid) {
      throw new Exception(
        "Failed to import the service definition file '$filePath', ".
        "the file could not be found. Check that the file exists and ".
        "that the file path is spelled correctly.",
        -100
      );
    }

    $service = ServiceProvider::getService($filePath);
    $service->validateInputData($slimAppModules, $inputData);
    $result = $service->execute($slimAppModules, $inputData);
    return ServiceProvider::createResponseJson($result);
  }

  private static function handleException($slimAppModules, $exception) {
    $errorCode = $exception->getCode();
    if ($errorCode == 0) {
      $errorCode = -1;
    }

    $errorMessage = $exception->getMessage();
    $isErrorMessageValid =
      isset($errorMessage) && strlen($errorMessage) > 0;
    if (!$isErrorMessageValid) {
      $errorMessage = 'Unknown exception ocurred.';
    }

    $slimAppModules->log->error($errorMessage);

    return ServiceProvider::createResponseJson(
      NULL, $errorCode, $errorMessage
    );
  }

  private static function getService($filePath) {
    $service = NULL;
    require_once $filePath;
    return $service;
  }

  private static function createResponseJson(
    $data = NULL, $code = 0, $message = 'Success'
  ) {
    return json_encode([
      'returnCode' => $code,
      'message' => $message,
      'data' => $data
    ], JSON_NUMERIC_CHECK);
  }

  private function initSlimApp() {
    $this->slimApp = new App([
      'settings' => [
        'displayErrorDetails' => true
      ]
    ]);
  }

  private function initSlimAppHandlers() {
    $slimAppContainer = $this->slimApp->getContainer();
    $slimAppContainer['notFoundHandler'] = $this->getNotFoundHandler();
    $slimAppContainer['notAllowedHandler'] = $this->getNotAllowedHandler();
  }

  private function initSlimAppModules($customModules) {
    $slimAppContainer = $this->slimApp->getContainer();
    $slimAppContainer['log'] = $this->getLoggerModule();
    $slimAppContainer['session'] = $this->getSessionModule();

    foreach ($customModules as $name => $module) {
      $slimAppContainer[$name] = $module;
    }
  }

  private function initSlimAppMiddleware() {
    if (CORS_REQUESTS['allowed']) {
      $this->slimApp->add((CORS_REQUESTS['with_credentials']) ?
        $this->getInitCorsWithCredentialsMiddleware()
        : $this->getInitCorsMiddleware()
      );
      $this->addCorsPreflightService();
    } else {
      $this->slimApp->add($this->getInitResponseHeadersMiddleware());
    }
  }

  private function initSlimAppServices($serviceDefinitionFilePaths) {
    foreach ($serviceDefinitionFilePaths as $httpMethod => $services) {
      foreach ($services as $serviceName => $serviceFilePath) {
        $serviceName = SERVICES_ROOT_URL.$serviceName;
        $serviceCallback = $this->getServiceCallback($serviceFilePath);

        // https://www.slimframework.com/docs/v3/objects/router.html
        switch ($httpMethod) {
          case 'GET':
            $this->slimApp->get($serviceName, $serviceCallback);
            break;

          case 'PUT':
            $this->slimApp->put($serviceName, $serviceCallback);
            break;

          case 'PATCH':
            $this->slimApp->patch($serviceName, $serviceCallback);
            break;

          case 'POST':
            $this->slimApp->post($serviceName, $serviceCallback);
            break;

          case 'DELETE':
            $this->slimApp->delete($serviceName, $serviceCallback);
            break;

          default:
            throw new Exception(
              "Failed to configure the Slim application; the specified HTTP ".
              "method '$httpMethod' for service '$serviceName' is not valid ".
              "or is not supported.",
              -100
            );
            break;
        } // switch ($httpMethod)
      } // foreach ($services as $serviceName => $serviceFilePath)
    } // foreach ($serviceDefinitionFilePaths as $httpMethod => $services)
  } // private function initSlimAppServices($serviceDefinitionFilePaths)

  private function getNotFoundHandler() {
    return function($config) {
      return function (Request $request, Response $response) use ($config) {
        return $config['response']
          ->withStatus(404)
          ->getBody()
          ->write(ServiceProvider::createResponseJson(
            [], 404, 'The requested service could not be found'
          ));
      };
    };
  }

  private function getNotAllowedHandler() {
    return function($config) {
      return function(Request $request, Response $response, $allowedHttpMethods)
        use ($config)
      {
        $allowedHttpMethods = implode(', ', $allowedHttpMethods);
        return $config['response']
          ->withStatus(405)
          ->withHeader('Allow', $allowedHttpMethods)
          ->getBody()->write(ServiceProvider::createResponseJson(
            [], 405, "HTTP Method must be one of: $allowedHttpMethods"
          ));
        return $result;
      };
    };
  }

  private function getLoggerModule() {
    return function($config) {
      $loggerModule = new Logger('AppLog');
      $loggerModule->pushHandler(new StreamHandler(LOG_FILE_PATH));
      return $loggerModule;
    };
  }

  private function getSessionModule() {
    return function($config) {
      ini_set('session.name', 'SessionCookie');
      ini_set('session.hash_function', 'sha512');
      ini_set('session.use_strict_mode', '1');

      // Siempre y cuando estas 2 banderas estén activadas, podemos confiar en
      // que nuestra cookie de sesión no ha sido comprometida al ser recibida
      // desde el cliente
      // https://developer.mozilla.org/en-US/docs/Web/HTTP/Cookies
      // http://resources.infosecinstitute.com/securing-cookies-httponly-secure-flags/#gref
      ini_set('session.cookie_httponly', '1');
      if (USE_HTTPS) {
        ini_set('session.cookie_secure', '1');
      }

      $factory = new SessionFactory;
      return $factory->newInstance($_COOKIE);
    };
  }

  private function getInitCorsMiddleware() {
    return function(Request $request, Response $response, $next) {
      $response = $response->withHeader(
        'Content-Type', 'application/json;charset=utf8'
      );
      $response = $response->withHeader(
        'Access-Control-Allow-Headers',
        'X-Requested-With, Content-Type, Accept, Origin, Authorization'
      );
      $response = $response->withHeader(
        'Access-Control-Allow-Methods', 'GET, PUT, PATCH, POST, DELETE, OPTIONS'
      );
      $response = $response->withHeader('Access-Control-Allow-Origin', '*');
      return $next($request, $response);
    };
  }

  private function getInitCorsWithCredentialsMiddleware() {
    return function(Request $request, Response $response, $next) {
      $response = $response->withHeader(
        'Content-Type', 'application/json;charset=utf8'
      );
      $response = $response->withHeader(
        'Access-Control-Allow-Headers',
        'X-Requested-With, Content-Type, Accept, Origin, Authorization'
      );
      $response = $response->withHeader(
        'Access-Control-Allow-Methods', 'GET, PUT, PATCH, POST, DELETE, OPTIONS'
      );

      $currentOrigin = $_SERVER['HTTP_ORIGIN'];
      $isOriginAllowed = FALSE;
      foreach (CORS_REQUESTS['allowed_origins'] as $origin) {
        if ($currentOrigin == $origin) {
          $isOriginAllowed = TRUE;
          break;
        }
      }

      if ($isOriginAllowed) {
        $response = $response->withHeader(
          'Access-Control-Allow-Credentials', 'true'
        );
        $response = $response->withHeader(
          'Access-Control-Allow-Origin', $currentOrigin
        );
      }
      return $next($request, $response);
    };
  }

  private function addCorsPreflightService() {
    $this->slimApp->options(
      SERVICES_ROOT_URL.'{routes:.+}',
      function(Request $request, Response $response) {
        return $response;
      }
    );
  }

  private function getInitResponseHeadersMiddleware() {
    return function(Request $request, Response $response, $next) {
      $response = $response->withHeader(
        'Content-Type',
        'application/json;charset=utf8'
      );
      return $next($request, $response);
    };
  }

  private function getServiceCallback($serviceFilePath) {
    return function(Request $request, Response $response, $args)
      use ($serviceFilePath)
    {
      // Resulta que PHP no procesa los datos del cuerpo de una petición PATCH
      // o PUT con el encabezado Content-Type: multipart/form-data para que
      // puedan ser accedidos fácilmente de un arreglo asociativo, como sucede
      // con $_POST. Debido a esto, getParsedBody tampoco procesa estos datos y
      // por lo tanto siempre retorna NULL. Para evitar esto, debemos procesar
      // nosotros los datos manualmente
      $result = NULL;
      $serviceInputData =
        ServiceProvider::parseVerboseValues(
          ($request->isPatch() || $request->isPut()) ?
            util\getParsedMultipartInput() : $request->getParsedBody()
        );

      if (count($args) > 0) {
        $serviceInputData = array_merge(
          (isset($serviceInputData)) ? $serviceInputData : [], $args
        );
      }

      $queryParams = $request->getQueryParams();
      if (count($queryParams) > 0) {
        $queryParams = ServiceProvider::parseVerboseValues($queryParams);
        $serviceInputData = array_merge(
          (isset($serviceInputData)) ? $serviceInputData : [], $queryParams
        );
      }

      $serviceInputData['uploadedFiles'] = $request->getUploadedFiles();

      try {
        $result = ServiceProvider::executeService(
          $this, $serviceFilePath, $serviceInputData
        );
      } catch (Exception $e) {
        $result = ServiceProvider::handleException($this, $e);
      } finally {
        $response->getBody()->write($result);
      }

      return $response;
    };
  }
}

?>