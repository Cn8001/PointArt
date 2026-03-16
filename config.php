<?php
/*
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */
defined('VIEW_DIRECTORY') || define('VIEW_DIRECTORY', __DIR__ . "/app/views/");
return [
    'db' => [
        'driver' => 'sqlite',          // sqlite | mysql | pgsql

        // SQLite
        'path'     => __DIR__ . '/database.sqlite',

        // MySQL / PostgreSQL
        'host'     => 'localhost',
        'port'     => 3306,            // 5432 for pgsql
        'database' => 'pointstart',
        'username' => 'root',
        'password' => '',
        'charset'  => 'utf8mb4',       // mysql only
    ],
    'app' => [
          'debug' => true,
    ],
];
?>