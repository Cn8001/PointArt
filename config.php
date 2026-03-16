<?php
/*
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */
defined('VIEW_DIRECTORY') || define('VIEW_DIRECTORY', __DIR__ . "/app/views/");
return [
    'db' => [
        'driver'   => $_ENV['DB_DRIVER']   ?? 'sqlite',

        // SQLite
        'path'     => $_ENV['DB_PATH']     ?? __DIR__ . '/database.sqlite',

        // MySQL / PostgreSQL
        'host'     => $_ENV['DB_HOST']     ?? 'localhost',
        'port'     => $_ENV['DB_PORT']     ?? 3306,
        'database' => $_ENV['DB_DATABASE'] ?? 'pointstart',
        'username' => $_ENV['DB_USERNAME'] ?? 'root',
        'password' => $_ENV['DB_PASSWORD'] ?? '',
        'charset'  => $_ENV['DB_CHARSET']  ?? 'utf8mb4',
    ],
    'app' => [
        'debug' => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN),
    ],
];
?>
