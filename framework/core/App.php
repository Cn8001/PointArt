<?php
/*
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */
namespace PointStart\Core{
    require_once __DIR__ . "/Env.php";
    require_once __DIR__ . "/../../config.php";
    require_once __DIR__ . "/Container.php";
    require_once __DIR__ . "/ClassLoader.php";
    require_once __DIR__ . "/Cors.php";
    require_once __DIR__ . "/Csrf.php";
    require_once __DIR__ . "/RouteHandler.php";
    require_once __DIR__ . "/Renderer.php";
    require_once __DIR__ . "/Updater.php";
    require_once __DIR__ . "/../ORM/Model.php";
    require_once __DIR__ . "/../ORM/Repository.php";

    class App{
        private Container $container;
        private RouteHandler $routeHandler;
        private ClassLoader $classLoader;
        private bool $debug;
        private array $config;

        public function __construct(){
            Env::load(__DIR__ . '/../../.env');
            $this->config = require __DIR__ . '/../../config.php';
            $this->debug = $this->config['app']['debug'] ?? false;

            set_exception_handler(function(\Throwable $e){
                $this->handleError($e);
            });

            $this->container = new Container();
            $this->classLoader = new ClassLoader();
            $this->routeHandler = new RouteHandler($this->container, $this->classLoader, $this->config);
        }

        public function run(){
            $this->container->loadContainer();
        }

        public function onRequest($requestUri, $requestMethod){
            Cors::apply($this->config);
            if(Cors::isPreflightRequest()){
                http_response_code(204);
                return;
            }

            // Built-in updater route — handled before user routes
            $path = strtok($requestUri, '?');
            if($this->handleUpdaterRoute($path, $requestMethod)){
                return;
            }

            $returnValue = $this->routeHandler->dispatch($requestUri, $requestMethod);
            if(isset($returnValue)){
                if(is_array($returnValue) || is_object($returnValue)){
                    header('Content-Type: application/json');
                    echo json_encode($returnValue);
                } else {
                    echo $returnValue;
                }
            }
        }

        private function handleUpdaterRoute(string $path, string $method): bool {
            if(!str_starts_with($path, '/pointart/update')) return false;

            $enabled = filter_var($this->config['updater']['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
            if(!$enabled) return false;

            $secret = $this->config['updater']['secret'] ?? '';
            $updater = new Updater($secret);

            if($path === '/pointart/update' && $method === 'GET'){
                $updater->handleLoginForm();
                return true;
            }
            if($path === '/pointart/update' && $method === 'POST'){
                $updater->handleLogin();
                return true;
            }
            if($path === '/pointart/update/run' && $method === 'POST'){
                $updater->handleRunUpdate();
                return true;
            }

            return false;
        }

        private function handleError(\Throwable $e): void {
            http_response_code(500);
            if($this->debug){
                echo '<pre style="font-family:monospace;padding:20px;background:#fff3f3;border:1px solid #f00">';
                echo '<strong>' . get_class($e) . '</strong>: ' . htmlspecialchars($e->getMessage()) . "\n\n";
                echo htmlspecialchars($e->getTraceAsString());
                echo '</pre>';
            } else {
                httpError(500);
            }
        }
    }
}
?>
