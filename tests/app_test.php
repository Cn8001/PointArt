<?php
/**
 * App Tests — bootstrap, wiring, request dispatch
 * Run: php tests/app_test.php
 */

require_once __DIR__ . '/test_helpers.php';
require_once __DIR__ . '/../framework/attributes/Route.php';
require_once __DIR__ . '/../framework/attributes/Router.php';
require_once __DIR__ . '/../framework/attributes/Service.php';
require_once __DIR__ . '/../framework/attributes/Wired.php';
require_once __DIR__ . '/../framework/attributes/RequestParam.php';
require_once __DIR__ . '/../framework/core/ClassLoader.php';
require_once __DIR__ . '/../framework/core/Container.php';
require_once __DIR__ . '/../framework/core/HttpResponses.php';
require_once __DIR__ . '/../framework/core/RouteHandler.php';
require_once __DIR__ . '/../framework/core/App.php';

use PointStart\Core\App;

// ─── 1. App construction ────────────────────────────────────────────────────

echo "── App construction ──\n";

$app = new App();
$appRef = new ReflectionClass($app);

$containerProp = $appRef->getProperty('container');
$containerProp->setAccessible(true);
$container = $containerProp->getValue($app);
assert_true('Container is created', $container !== null);
assert_true('Container is correct type', $container instanceof \PointStart\Core\Container);

$classLoaderProp = $appRef->getProperty('classLoader');
$classLoaderProp->setAccessible(true);
$classLoader = $classLoaderProp->getValue($app);
assert_true('ClassLoader is created', $classLoader !== null);
assert_true('ClassLoader is correct type', $classLoader instanceof \PointStart\Core\ClassLoader);

$routeHandlerProp = $appRef->getProperty('routeHandler');
$routeHandlerProp->setAccessible(true);
$routeHandler = $routeHandlerProp->getValue($app);
assert_true('RouteHandler is created', $routeHandler !== null);
assert_true('RouteHandler is correct type', $routeHandler instanceof \PointStart\Core\RouteHandler);

// ─── 2. onRequest dispatches to RouteHandler ────────────────────────────────

echo "\n── onRequest dispatch ──\n";

// onRequest should not throw on unknown route (returns 404 via return404())
ob_start();
$app->onRequest('/nonexistent-route', 'GET');
$output = ob_get_clean();
assert_true('Unknown route returns 404 output', str_contains($output, '404'));

// ─── 3. run() does not throw ────────────────────────────────────────────────

echo "\n── run() ──\n";

$app2 = new App();
$threw = false;
try {
    $app2->run();
} catch (\Throwable $e) {
    $threw = true;
}
assert_true('run() executes without error', !$threw);

// ─── 4. Lazy instantiation — nothing in Container before dispatch ───────────

echo "\n── Lazy instantiation ──\n";

$app3 = new App();
$app3Ref = new ReflectionClass($app3);
$containerProp3 = $app3Ref->getProperty('container');
$containerProp3->setAccessible(true);
$container3 = $containerProp3->getValue($app3);

$containerRef3 = new ReflectionClass($container3);
$instancesProp3 = $containerRef3->getProperty('instances');
$instancesProp3->setAccessible(true);

$app3->run();

// After run(), no instances should be created yet
$instancesBeforeDispatch = $instancesProp3->getValue($container3);
assert_equals('No instances before dispatch', 0, count($instancesBeforeDispatch));

// Dispatch to / — only TestController should be instantiated
ob_start();
$app3->onRequest('/', 'GET');
ob_end_clean();

$instancesAfterDispatch = $instancesProp3->getValue($container3);
assert_true('TestController is instantiated after dispatch', isset($instancesAfterDispatch['TestController']));
assert_true('UserController is NOT instantiated (not dispatched)', !isset($instancesAfterDispatch['UserController']));
assert_true('UserService is NOT instantiated (not dispatched)', !isset($instancesAfterDispatch['UserService']));
assert_equals('Only 1 instance created after single dispatch', 1, count($instancesAfterDispatch));
