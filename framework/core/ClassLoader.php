<?php
/*
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */
namespace PointStart\Core{
    use PointStart\Attributes\Service;
    use ReflectionClass;

    class ClassLoader{
        private static array $routes = [];
        private static array $services = [];
        private static string $cacheFile = __DIR__ . '/../../cache/registry.ser';

        // Register autoloader for a directory
        private function registerAutoloader(string $dir): void {
            spl_autoload_register(function(string $class) use ($dir) {
                $file = $dir . '/' . $class . '.php';
                if (file_exists($file)) {
                    require $file;
                }
            });
        }

        // Load classes — use cache if available, scan if not
        public function loadClasses(string $dir): void {
            $this->registerAutoloader($dir);

            if (file_exists(self::$cacheFile)) {
                // Cache hit — no scanning, no Reflection
                $cache = unserialize(file_get_contents(self::$cacheFile));
                self::$routes   = $cache['routes'];
                self::$services = $cache['services'];
                return;
            }

            // Cache miss — scan all files, load via autoloader, read attributes
            foreach (scandir($dir) as $file) {
                if ($file === '.' || $file === '..') continue;

                $path = $dir . '/' . $file;
                if (is_dir($path)) {
                    $this->loadClasses($path);
                } else {
                    $this->register(basename($file, '.php'));
                }
            }

            // Persist to cache
            if (!is_dir(dirname(self::$cacheFile))) {
                mkdir(dirname(self::$cacheFile), 0777, true);
            }
            file_put_contents(self::$cacheFile, serialize([
                'routes'   => self::$routes,
                'services' => self::$services,
            ]));
        }

        // Clear cache (call after deploying new controllers)
        public static function clearCache(): void {
            if (file_exists(self::$cacheFile)) {
                unlink(self::$cacheFile);
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

                    $this->registerRoutes($reflection);
                }
            }
        }

        // Get all routes inside a router class and register
        private function registerRoutes($reflection){
            
            //Get all methods inside class
            $methods = $reflection->getMethods();

            //Check for attributes in each method
            foreach($methods as $method){
                $attributes = $method->getAttributes();
                foreach($attributes as $attribute){
                    $instance = $attribute->newInstance();
                    if($instance instanceof \PointStart\Attributes\Route){
                        $this->register_route($instance->httpMethod, $instance->path, $reflection->getName(), $method->getName());
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

        public static function getRoutes(){
            return self::$routes;
        }

        public static function getServices(){
            return self::$services;
        }
    }
}

?>