--TEST--
App bootstrap, wiring, and request dispatch
--FILE--
<?php
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
use PointStart\Core\ClassLoader;

// ─── Fixture — lightweight controller, no DB needed ──────────────────────────

$fixtureFile = __DIR__ . '/../app/components/_PingController.php';
file_put_contents($fixtureFile, '<?php
use PointStart\Attributes\Router;
use PointStart\Attributes\Route;
use PointStart\Attributes\HttpMethod;
#[Router(path: "/", name: "_ping")]
class _PingController {
    #[Route("/ping", HttpMethod::GET)]
    public function ping(): string { return "pong"; }
}
');
ClassLoader::clearCache();

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

$instancesBeforeDispatch = $instancesProp3->getValue($container3);
assert_equals('No instances before dispatch', 0, count($instancesBeforeDispatch));

// Dispatch to /ping — only _PingController should be instantiated
ob_start();
$app3->onRequest('/ping', 'GET');
ob_end_clean();

$instancesAfterDispatch = $instancesProp3->getValue($container3);
assert_equals('Only 1 instance created after single dispatch', 1, count($instancesAfterDispatch));

$instantiatedClass = array_key_first($instancesAfterDispatch);
assert_true('Dispatched controller is instantiated', $instantiatedClass === '_PingController');
assert_true('Other controllers are NOT instantiated', !isset($instancesAfterDispatch['UserController']) && !isset($instancesAfterDispatch['ProductController']));

// ─── Cleanup ─────────────────────────────────────────────────────────────────

unlink($fixtureFile);
ClassLoader::clearCache();
--EXPECT--
── App construction ──
[PASS] Container is created
[PASS] Container is correct type
[PASS] ClassLoader is created
[PASS] ClassLoader is correct type
[PASS] RouteHandler is created
[PASS] RouteHandler is correct type

── onRequest dispatch ──
[PASS] Unknown route returns 404 output

── run() ──
[PASS] run() executes without error

── Lazy instantiation ──
[PASS] No instances before dispatch
[PASS] Only 1 instance created after single dispatch
[PASS] Dispatched controller is instantiated
[PASS] Other controllers are NOT instantiated
