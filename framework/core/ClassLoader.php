<?php
/*
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */
namespace PointStart\Core{
    use PointStart\Attributes\Service;
    use ReflectionClass;

    class RouteLoader{
        private static array $routes = [];
        private static array $services = [];

        // Load classes from directory and include them
        public function loadClasses($dir){
            $files = scandir($dir);
            foreach($files as $file){
                if($file == ".." || $file == ".") continue;

                $path = $dir . "/" . $file;
                if(is_dir($path)){ // Relative load
                    $this->loadClasses($path);
                } else {

                    require_once $path; // First include
                    $this->register(basename($file,".php")); // Load class
                }
            }
        }

        // Register router classes and services based on attributes
        private function register($className){
            $reflection = new ReflectionClass($className);
            $attributes = $reflection->getAttributes();

            foreach($attributes as $attribute){
                $instance = $attribute->newInstance();
                if($instance instanceof Service){
                    self::$services[$instance->name] = $className;

                }
                if($instance instanceof \PointStart\Attributes\Router){

                    $this->registerRoutes($className);
                }
            }
        }

        // Get all routes inside a router class and register
        private function registerRoutes($routeclass){
            
            //Get all methods inside class
            $reflection = new ReflectionClass($routeclass);
            $methods = $reflection->getMethods();

            //Check for attributes in each method
            foreach($methods as $method){
                $attributes = $method->getAttributes();
                foreach($attributes as $attribute){
                    $instance = $attribute->newInstance();
                    if($instance instanceof \PointStart\Attributes\Route){
                        $this->register_route($instance->httpMethod, $instance->path, $routeclass, $method->getName());
                    }
                }
            }
        }

        // Register single route
        private function register_route($httpMethod, $path, $class, $methodName){
            self::$routes[$httpMethod][$path] = [
                    "class" => $class,
                    "method" => $methodName
            ];
        }
    }
}

?>