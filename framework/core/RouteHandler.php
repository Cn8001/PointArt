<?php
/*
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */


namespace PointStart\Core{
    require_once __DIR__ . "/Container.php";
    require_once __DIR__ . "/ClassLoader.php";
    require_once __DIR__ . "/HttpResponses.php";
    require_once __DIR__ . "/Csrf.php";
    require_once __DIR__ . "/../attributes/Route.php";
    require_once __DIR__ . "/../attributes/RequestParam.php";

    use PointStart\Attributes\Router;
    use PointStart\Attributes\Route;
    use PointStart\Attributes\RequestParam;
    use ReflectionMethod; // Get method prototype and inject parameters from request

    class RouteHandler{
        // Route requests and invoke controller methods with parameters via route table from ClassLoader
       public function __construct(
            protected Container $container,
            protected ClassLoader $classLoader,
            protected array $config = []
        ){
        }

        // Resolve route and invoke controller method, return 404 if route not found or controller instantiation fails
        public function dispatch($requestUri, $requestMethod){
            $routes = ClassLoader::getRoutes();

            // Strip query string
            $path = strtok($requestUri, '?');
            
            // Try exact match first, then pattern match for routes with {param}
            $destination = $routes[$requestMethod][$path] ?? null;
            $matchedRoutePath = $path;
            if(!isset($destination) && isset($routes[$requestMethod])){
                $requestParts = explode('/', $path);
                foreach($routes[$requestMethod] as $routePath => $route){
                    
                    $routeParts = explode('/', $routePath);
                    if(count($routeParts) !== count($requestParts)) continue;
                    $matches = true;
                    foreach($routeParts as $i => $part){
                        if(str_starts_with($part, '{') && str_ends_with($part, '}')) continue;
                        if($part !== $requestParts[$i]){ $matches = false; break; }
                    }
                    if($matches){
                        $destination = $route;
                        $matchedRoutePath = $routePath;
                        break;
                    }
                }
            }

            if(!isset($destination)){
                httpError(404);
                return;
            }

            $controllerClass = $destination['class'];
            $method = $destination['method'];

            // CSRF validation — only enforced when a session token exists.
            // Requests without an established session token (API clients, curl, etc.) pass through.
            if(($this->config['csrf']['enabled'] ?? true)
                && $requestMethod === 'POST'
                && !($destination['csrfExempt'] ?? false)
                && !str_starts_with($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')
                && !empty($_SESSION['_csrf_token'] ?? null)
            ){
                if(!Csrf::validate()){
                    httpError(403);
                    return;
                }
            }

            $classInstance = $this->container->getInstance($controllerClass);
            if(!isset($classInstance)){
                httpError(404);
                return;
            }

            // Get methods and inject parameters
            $methodInstance = new \ReflectionMethod($controllerClass, $method);

            if(!str_contains($matchedRoutePath, '{')){
                //Plain url without path parameters, still inject query/post/file parameters
                return $this->invokeMethodWithParameters($methodInstance, $classInstance, []);
            }

            $routeParts = explode('/', $matchedRoutePath);
            $pathParameters = $this->parseUrl($requestUri);
            $parameterValues = $this->matchParameterNamesWithValues($routeParts, $pathParameters);
            return $this->invokeMethodWithParameters($methodInstance, $classInstance, $parameterValues);

        }

        // Returns path parameters from values. For example, for /users/123/profile it will return [users 123 profile]
        private function parseUrl($url){
            // Strip query string if present
            $parameterPart = strstr($url, '?', true);
            if($parameterPart === false){
                $parameterPart = $url;
            }

            return explode('/', $parameterPart);
        }

        // Matches path parameters with data from request by index and returns an associative array of parameter names and values
        /*
        Request: /users/123/profile
        Route: /users/{id}/profile
        Result: ['id' => 123]
        */
        private function matchParameterNamesWithValues($routeParts, $datas){
                $values = [];
                foreach($routeParts as $index => $part){
                    if(str_starts_with($part, '{') && str_ends_with($part, '}')){
                        $parameterName = substr($part, 1, -1);
                        $values[$parameterName] = $datas[$index] ?? null;
                    }
                }
    
                return $values;
        }

        // Inject parameters into method and invoke it
        // Path params and $_GET are always available
        // $_POST and $_FILES only injected into parameters marked with #[RequestParam]
        // #[RequestParam] $name = $_post["name"]
        private function invokeMethodWithParameters($methodInstance, $classInstance, $parameterValues){
            $args = [];
            $parameters = $methodInstance->getParameters();

            foreach($parameters as $param){
                $name = $param->getName();
                $hasRequestParam = !empty($param->getAttributes(RequestParam::class));

                if(array_key_exists($name, $parameterValues)){
                    $args[] = $parameterValues[$name];
                } elseif(isset($_GET[$name])){
                    $args[] = $_GET[$name];
                } elseif($hasRequestParam && isset($_POST[$name])){
                    $args[] = $_POST[$name];
                } elseif($hasRequestParam && isset($_FILES[$name])){
                    $args[] = $_FILES[$name];
                } elseif($param->isDefaultValueAvailable()){
                    $args[] = $param->getDefaultValue();
                } else {
                    $args[] = null;
                }
            }

            return $methodInstance->invokeArgs($classInstance, $args);
        }

    }
}
?>