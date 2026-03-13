<?php
/*
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */
namespace PointStart\Core{
    require_once __DIR__ . "/Container.php";
    require_once __DIR__ . "/ClassLoader.php";
    require_once __DIR__ . "/RouteHandler.php";

    class App{
        private Container $container;
        private RouteHandler $routeHandler;
        private ClassLoader $classLoader;

        public function __construct(){
            $this->container = new Container();
            $this->classLoader = new ClassLoader();
            $this->routeHandler = new RouteHandler($this->container,$this->classLoader);
        }

        public function run(){
            $this->container->loadContainer();
        }

        public function onRequest($requestUri, $requestMethod){
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
    }
}
?>