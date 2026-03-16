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

// 2. Route table has UserController registered (class may or may not be in memory depending on cache)
assert_true(
    'UserController is registered in route table',
    isset($routes['GET']['/user/user-list']) && $routes['GET']['/user/user-list']['class'] === 'UserController'
);

// 3. Routes are registered correctly
assert_true(
    'GET /user/user-list maps to UserController::index',
    isset($routes['GET']['/user/user-list']) &&
    $routes['GET']['/user/user-list']['class']  === 'UserController' &&
    $routes['GET']['/user/user-list']['method'] === 'index'
);

assert_true(
    'GET /user/user-show/{id} maps to UserController::show',
    isset($routes['GET']['/user/user-show/{id}']) &&
    $routes['GET']['/user/user-show/{id}']['class']  === 'UserController' &&
    $routes['GET']['/user/user-show/{id}']['method'] === 'show'
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
$match = $routes['GET']['/user/user-list'];
new $match['class']();

assert_true(
    'UserController is loaded after dispatch',
    class_exists('UserController', false)
);
// TestController is in the route table but not instantiated until dispatched
assert_true(
    'TestController is registered in route table',
    isset($routes['GET']) && in_array('TestController', array_column(array_merge(...array_values($routes)), 'class'))
);
assert_equals('index() route method name', 'index', $match['method']);
