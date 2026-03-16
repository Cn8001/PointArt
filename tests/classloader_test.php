<?php
/**
 * ClassLoader Tests — route scanning, service registration, lazy loading
 * Run: php tests/classloader_test.php
 */

require_once __DIR__ . '/../framework/attributes/Route.php';
require_once __DIR__ . '/../framework/attributes/Router.php';
require_once __DIR__ . '/../framework/attributes/Service.php';
require_once __DIR__ . '/../framework/core/ClassLoader.php';

use PointStart\Core\ClassLoader;

require_once __DIR__ . '/test_helpers.php';

// ─── Fixture — lightweight controller with no dependencies ───────────────────

$fixtureFile = __DIR__ . '/../app/components/_ScanTestController.php';
file_put_contents($fixtureFile, '<?php
use PointStart\Attributes\Router;
use PointStart\Attributes\Route;
use PointStart\Attributes\HttpMethod;
use PointStart\Attributes\Service;
#[Router(path: "/scantest", name: "_scantest")]
#[Service(name: "_ScanTestService")]
class _ScanTestController {
    #[Route("/hello", HttpMethod::GET)]
    public function hello(): string { return "hello"; }
    #[Route("/world", HttpMethod::POST)]
    public function world(): string { return "world"; }
    public function notARoute(): string { return "nope"; }
}
');
ClassLoader::clearCache();

// ─── Boot ─────────────────────────────────────────────────────────────────────

$loader = new ClassLoader();
$loader->loadClasses(__DIR__ . '/../app/components');

$routes   = ClassLoader::getRoutes();
$services = ClassLoader::getServices();

// ─── Tests ────────────────────────────────────────────────────────────────────

// 1. Route table is populated
assert_true('Route table is populated', !empty($routes));

// 2. Fixture routes are registered correctly
assert_true(
    'GET /scantest/hello maps to _ScanTestController::hello',
    isset($routes['GET']['/scantest/hello']) &&
    $routes['GET']['/scantest/hello']['class']  === '_ScanTestController' &&
    $routes['GET']['/scantest/hello']['method'] === 'hello'
);

assert_true(
    'POST /scantest/world maps to _ScanTestController::world',
    isset($routes['POST']['/scantest/world']) &&
    $routes['POST']['/scantest/world']['class']  === '_ScanTestController' &&
    $routes['POST']['/scantest/world']['method'] === 'world'
);

// 3. Non-route methods are not registered
$allMethods = array_merge(...array_values($routes));
assert_true(
    'notARoute() is not registered',
    !in_array('notARoute', array_column($allMethods, 'method'))
);

// 4. UserController is still registered (existing app controller)
assert_true(
    'UserController GET /user/list is registered',
    isset($routes['GET']['/user/list']) &&
    $routes['GET']['/user/list']['class'] === 'UserController'
);

// 5. Service is registered
assert_true(
    'Fixture service is registered',
    isset($services['_ScanTestService'])
);

// 6. Lazy loading — on a cache-hit request (normal production case), classes
//    must NOT be loaded until the route is dispatched.
//    Tested in a subprocess so we get a fresh PHP process with a warm cache.

$lazyScript = __DIR__ . '/_lazy_check.php';
file_put_contents($lazyScript, '<?php
require_once "' . __DIR__ . '/../framework/attributes/Route.php";
require_once "' . __DIR__ . '/../framework/attributes/Router.php";
require_once "' . __DIR__ . '/../framework/attributes/Service.php";
require_once "' . __DIR__ . '/../framework/core/ClassLoader.php";
use PointStart\Core\ClassLoader;
$loader = new ClassLoader();
$loader->loadClasses("' . __DIR__ . '/../app/components");
// Before dispatch: should NOT be loaded (cache read, no require fired)
echo class_exists("_ScanTestController", false) ? "loaded" : "not_loaded";
echo "|";
// After dispatch: autoloader fires, class is loaded
$routes = ClassLoader::getRoutes();
$match  = $routes["GET"]["/scantest/hello"];
new $match["class"]();
echo class_exists("_ScanTestController", false) ? "loaded" : "not_loaded";
');

$output = shell_exec('php ' . escapeshellarg($lazyScript));
[$beforeDispatch, $afterDispatch] = explode('|', trim($output));

assert_equals('Class not loaded before dispatch (cache-hit)', 'not_loaded', $beforeDispatch);
assert_equals('Class loaded after dispatch', 'loaded', $afterDispatch);

unlink($lazyScript);

// ─── Cleanup ─────────────────────────────────────────────────────────────────

unlink($fixtureFile);
ClassLoader::clearCache();
