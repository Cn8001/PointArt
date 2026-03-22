<?php
/*
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */
namespace PointStart\Core{
    class Csrf{
        public static function validate(): bool{
            if(session_status() === PHP_SESSION_NONE) session_start();
            $sessionToken = $_SESSION['_csrf_token'] ?? '';
            if(empty($sessionToken)) return false;

            $requestToken = $_POST['_csrf_token']
                ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');

            return hash_equals($sessionToken, $requestToken);
        }
    }
}

namespace {     // Global helper functions for CSRF token generation and form field output
    function csrf_token(): string{
        if(session_status() === PHP_SESSION_NONE) session_start();
        if(empty($_SESSION['_csrf_token'])){
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf_token'];
    }

    function csrf_field(): string{
        return '<input type="hidden" name="_csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
    }
}

?>
