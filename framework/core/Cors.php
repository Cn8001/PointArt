<?php
/*
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */
namespace PointStart\Core{

    class Cors{
        // Returns the CORS headers that should be sent for the given config.
        // Keeps header-building logic testable without relying on PHP's header() in CLI.
        public static function buildHeaders(array $config): array{
            if(!($config['cors']['enabled'] ?? false)) return [];

            $cors = $config['cors'];
            $result = [];
            $allowedOrigins = $cors['allowed_origins'] ?? ['*'];

            if(in_array('*', $allowedOrigins)){
                $result[] = 'Access-Control-Allow-Origin: *';
            } else {
                $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
                if(in_array($origin, $allowedOrigins)){
                    $result[] = "Access-Control-Allow-Origin: $origin";
                    $result[] = 'Vary: Origin';
                }
            }

            $methods = implode(', ', $cors['allowed_methods'] ?? ['GET', 'POST', 'OPTIONS']);
            $result[] = "Access-Control-Allow-Methods: $methods";

            $hdrs = implode(', ', $cors['allowed_headers'] ?? ['Content-Type']);
            $result[] = "Access-Control-Allow-Headers: $hdrs";

            if($cors['allow_credentials'] ?? false){
                $result[] = 'Access-Control-Allow-Credentials: true';
            }

            if(isset($cors['max_age'])){
                $result[] = 'Access-Control-Max-Age: ' . $cors['max_age'];
            }

            return $result;
        }

        public static function apply(array $config): void{
            foreach(self::buildHeaders($config) as $h){
                header($h);
            }
        }

        public static function isPreflightRequest(): bool{
            return ($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS';
        }
    }
}
?>
