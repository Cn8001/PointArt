<?php
/**
 * ClassLoader Tests — actual UserController, no mocks
 * Run: php tests/classloader_test.php
 */

require_once __DIR__ . '/../framework/attributes/Route.php';
require_once __DIR__ . '/../framework/attributes/Router.php';
require_once __DIR__ . '/../framework/attributes/Service.php';
require_once __DIR__ . '/../framework/core/ClassLoader.php';

use PointStart\Core\ClassLoader;

// ─── Helpers ──────────────────────────────────────────────────────────────────

require_once __DIR__ . '/test_helpers.php';

// ─── Boot ─────────────────────────────────────────────────────────────────────

$controllersDir = __DIR__ . '/../app/components';

$loader = new ClassLoader();
$loader->loadClasses($controllersDir);

$routes   = ClassLoader::getRoutes();
$services = ClassLoader::getServices();

// ─── Tests ────────────────────────────────────────────────────────────────────

// 1. Route table is not empty
assert_true('Route table is populated', !empty($routes));

// 2. UserController is NOT loaded into memory — autoloader not triggered
assert_true(
    'UserController is NOT in memory (lazy)',
    !class_exists('UserController', false)
);

// 3. Routes are registered correctly
assert_true(
    'GET /user-list maps to UserController::index',
    isset($routes['GET']['/user-list']) &&
    $routes['GET']['/user-list']['class']  === 'UserController' &&
    $routes['GET']['/user-list']['method'] === 'index'
);

assert_true(
    'GET /user-show maps to UserController::show',
    isset($routes['GET']['/user-show']) &&
    $routes['GET']['/user-show']['class']  === 'UserController' &&
    $routes['GET']['/user-show']['method'] === 'show'
);

// 4. helper() is not in the route table
$allMethods = array_merge(...array_values($routes));
assert_true(
    'helper() is not registered as a route',
    !in_array('helper', array_column($allMethods, 'method'))
);

// 5. UserService is registered
assert_true(
    'UserService is registered',
    isset($services['UserService'])
);

// 6. Dispatch — only now load the class
$match = $routes['GET']['/user-list'];
$controller = new $match['class']();

assert_true(
    'UserController is loaded after dispatch',
    class_exists('UserController', false)
);
assert_true(
    'TestController is NOT loaded because it was never dispatched',
    !class_exists('TestController', false)
);
assert_equals('index() returns "user.list"', 'user.list', $controller->{$match['method']}());
