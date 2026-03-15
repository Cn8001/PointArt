<?php
/*
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */
namespace PointStart\Core{
    require_once __DIR__ . '/../attributes/Wired.php';
use ReflectionClass;
use PointStart\Attributes\Wired;
class Container{
    private array $instances;
    
    public function __construct(){
        $this->instances = [];
        $this->loadClassLoader();
    }

    public function loadContainer(){
        // No eager instantiation — classes are created on demand via getInstance()
    }

    private function loadClassLoader(){
        // 1. Load all required classes
        $loader = new ClassLoader();
        $loader->loadClasses(__DIR__ . "/../../app/components");

    }

    
    private function resolve_dependencies($className){
        $reflection = new ReflectionClass($className);
        $dependencies = [];
        foreach($reflection->getProperties() as $param){
            $wiredAttribute = $param->getAttributes(Wired::class);

            // If we have a wired property, add all dependencies array with property name and type (class name)
            /*
            [
                ["property" => "userRepository", "type" => "UserRepository"],
                ["property" => "logger", "type" => "Logger"]
                ["property" => "userService", "type" => "UserService"]
            ]
            */
            if(!empty($wiredAttribute)){
               $dependencies[] = [
                "property" => $param->getName(),
                "type" => $param->getType()->getName(),
                "required" => $wiredAttribute[0]->newInstance()->required
               ];
            }
        }
        return $dependencies;
    }

    private function generateInstances($classNames){
        foreach($classNames as $class){

            // If we do not have a dependency for it
            if(!isset($this->instances[$class])){
                $dependencies = $this->resolve_dependencies($class);
                foreach($dependencies as $dependency){
                    if(!$dependency["required"]){
                            continue;
                    }
                    // Go for subdependency if we do not have an instance for it
                    if(!isset($this->instances[$dependency["type"]])){
                        $this->generateInstances([$dependency["type"]]);
                    }
                }
                $reflection = new ReflectionClass($class);
                $instance = $reflection->newInstance();
                $this->injectDependencies($instance, $reflection, $dependencies);
                $this->instances[$class] = $instance;
            }
            
        }
    }

    private function injectDependencies($instance, $reflection, $dependencies){
        foreach($dependencies as $dependency){
            $name = $dependency["property"];
            $type = $dependency["type"];
            $property = $reflection->getProperty($name);
            $property->setAccessible(true); // Make private properties accessible in order to inject dependencies
            $property->setValue($instance, $this->instances[$type]);
        }

    }

    public function getInstance($className){
        if(!isset($this->instances[$className])){
            $this->generateInstances([$className]);
        }
        return $this->instances[$className] ?? null;
    }
}
}
?>