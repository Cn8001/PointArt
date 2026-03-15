<?php 
/*
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */
namespace PointStart\Core{
    

    class Renderer{ 
        
        public static function render($view, $data = []){
            
            if(!file_exists(VIEW_DIRECTORY . $view . ".php")){
                throw new \Exception("View not found: $view");
            }
            extract($data); //Extract variables from the data array to be used in the view.
            ob_start(); //Do not send output directly to the browser, store it in a buffer instead.
            require VIEW_DIRECTORY . $view . ".php"; //require_once skips in second call, view go blank.
            return ob_get_clean();
        }
    }
}
?>