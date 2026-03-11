<?php
/*
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */
namespace PointStart\Core;
class Container{
    private array $instances;
    
    public function __construct(){
        $this->instances = [];
        $this->loadClassLoader();
    }

    public function loadContainer(){
        $this->generateInstances($routes);
        $this->generateInstances($services);
    }

    private function loadClassLoader(){
        // 1. Load all classes
        $loader = new ClassLoader();
        $loader->loadClasses(__DIR__ . "/../../app/components");

    }

    private function generateInstances($classNames){
        foreach($classNames as $class){
            $this->instances[$class] = new $class;
        }
    }
}
//TODO: Create parameter resolver for constructor injection, and implement it in generateInstances
?>