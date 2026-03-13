<?php
/*
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */
require_once __DIR__ . '/framework/core/App.php';
use PointStart\Core\App;

$app = new App();
$app->run();
$app->onRequest($_SERVER['REQUEST_URI'], $_SERVER['REQUEST_METHOD']);

?>