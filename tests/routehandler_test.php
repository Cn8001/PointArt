<?php
/**
 * RouteHandler Tests — URL parsing, parameter matching, method invocation
 * Run: php tests/routehandler_test.php
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

use PointStart\Core\RouteHandler;
use PointStart\Core\Container;
use PointStart\Core\ClassLoader;
use PointStart\Attributes\Router;
use PointStart\Attributes\Route;
use PointStart\Attributes\HttpMethod;
use PointStart\Attributes\RequestParam;

// ─── Test Fixtures ───────────────────────────────────────────────────────────

#[Router]
class TestRouteController {
    #[Route('/test-plain', HttpMethod::GET)]
    public function plainRoute(): string {
        return 'plain';
    }

    #[Route('/users/{id}', HttpMethod::GET)]
    public function showUser($id): string {
        return "user:$id";
    }

    #[Route('/users/{id}/posts/{postId}', HttpMethod::GET)]
    public function showUserPost($id, $postId): string {
        return "user:$id:post:$postId";
    }

    #[Route('/search', HttpMethod::GET)]
    public function search($q, $page = '1'): string {
        return "q:$q:page:$page";
    }

    #[Route('/submit', HttpMethod::POST)]
    public function submit(#[RequestParam] $name, #[RequestParam] $email): string {
        return "name:$name:email:$email";
    }

    #[Route('/upload', HttpMethod::POST)]
    public function upload(#[RequestParam] $file): string {
        return "file:" . ($file['name'] ?? 'none');
    }

    #[Route('/mixed/{id}', HttpMethod::POST)]
    public function mixed($id, $q, #[RequestParam] $name): string {
        return "id:$id:q:$q:name:$name";
    }

    #[Route('/optional', HttpMethod::GET)]
    public function optional($missing = 'default'): string {
        return "val:$missing";
    }

    #[Route('/no-annotation', HttpMethod::GET)]
    public function noAnnotationParam($plain): string {
        return "plain:$plain";
    }
}

// ─── Setup ───────────────────────────────────────────────────────────────────

$container = new Container();
$ref = new ReflectionClass($container);
$instancesProp = $ref->getProperty('instances');
$instancesProp->setAccessible(true);
$instancesProp->setValue($container, ['TestRouteController' => new TestRouteController()]);

$classLoader = new ClassLoader();

$handler = new RouteHandler($container, $classLoader);
$handlerRef = new ReflectionClass($handler);

$parseUrl = $handlerRef->getMethod('parseUrl');
$parseUrl->setAccessible(true);

$matchParams = $handlerRef->getMethod('matchParameterNamesWithValues');
$matchParams->setAccessible(true);

$invokeMethod = $handlerRef->getMethod('invokeMethodWithParameters');
$invokeMethod->setAccessible(true);

// ─── 1. parseUrl ─────────────────────────────────────────────────────────────

echo "── parseUrl ──\n";

$result = $parseUrl->invoke($handler, '/users/123/profile?foo=bar');
assert_equals('parseUrl splits path segments', ['', 'users', '123', 'profile'], $result);

$result = $parseUrl->invoke($handler, '/users?q=test');
assert_equals('parseUrl single segment with query', ['', 'users'], $result);

// ─── 2. matchParameterNamesWithValues ────────────────────────────────────────

echo "\n── matchParameterNamesWithValues ──\n";

$routeParts = explode('/', '/users/{id}/profile');
$dataParts = ['', 'users', '123', 'profile'];
$result = $matchParams->invoke($handler, $routeParts, $dataParts);
assert_equals('Matches {id} to 123', ['id' => '123'], $result);

$routeParts = explode('/', '/users/{id}/posts/{postId}');
$dataParts = ['', 'users', '42', 'posts', '7'];
$result = $matchParams->invoke($handler, $routeParts, $dataParts);
assert_equals('Matches multiple path params', ['id' => '42', 'postId' => '7'], $result);

$routeParts = explode('/', '/users/list');
$dataParts = ['', 'users', 'list'];
$result = $matchParams->invoke($handler, $routeParts, $dataParts);
assert_equals('No placeholders returns empty', [], $result);

// ─── 3. invokeMethodWithParameters — path params ────────────────────────────

echo "\n── invokeMethodWithParameters (path params) ──\n";

$instance = new TestRouteController();
$method = new ReflectionMethod(TestRouteController::class, 'showUser');
$result = $invokeMethod->invoke($handler, $method, $instance, ['id' => '55']);
assert_equals('Injects path param $id', 'user:55', $result);

$method = new ReflectionMethod(TestRouteController::class, 'showUserPost');
$result = $invokeMethod->invoke($handler, $method, $instance, ['id' => '10', 'postId' => '3']);
assert_equals('Injects multiple path params', 'user:10:post:3', $result);

// ─── 4. invokeMethodWithParameters — $_GET query params ─────────────────────

echo "\n── invokeMethodWithParameters (query params) ──\n";

$_GET = ['q' => 'hello', 'page' => '5'];
$method = new ReflectionMethod(TestRouteController::class, 'search');
$result = $invokeMethod->invoke($handler, $method, $instance, []);
assert_equals('Injects $_GET params', 'q:hello:page:5', $result);

$_GET = ['q' => 'test'];
$result = $invokeMethod->invoke($handler, $method, $instance, []);
assert_equals('Uses default when $_GET param missing', 'q:test:page:1', $result);

$_GET = [];

// ─── 5. invokeMethodWithParameters — #[RequestParam] $_POST ─────────────────

echo "\n── invokeMethodWithParameters (#[RequestParam] POST) ──\n";

$_POST = ['name' => 'John', 'email' => 'john@test.com'];
$method = new ReflectionMethod(TestRouteController::class, 'submit');
$result = $invokeMethod->invoke($handler, $method, $instance, []);
assert_equals('Injects $_POST with #[RequestParam]', 'name:John:email:john@test.com', $result);
$_POST = [];

// Without #[RequestParam], $_POST should NOT be injected
$_POST = ['plain' => 'injected'];
$method = new ReflectionMethod(TestRouteController::class, 'noAnnotationParam');
$result = $invokeMethod->invoke($handler, $method, $instance, []);
assert_equals('$_POST ignored without #[RequestParam]', 'plain:', $result);
$_POST = [];

// ─── 6. invokeMethodWithParameters — #[RequestParam] $_FILES ────────────────

echo "\n── invokeMethodWithParameters (#[RequestParam] FILES) ──\n";

$_FILES = ['file' => ['name' => 'photo.jpg', 'tmp_name' => '/tmp/php123']];
$method = new ReflectionMethod(TestRouteController::class, 'upload');
$result = $invokeMethod->invoke($handler, $method, $instance, []);
assert_equals('Injects $_FILES with #[RequestParam]', 'file:photo.jpg', $result);
$_FILES = [];

// ─── 7. invokeMethodWithParameters — mixed sources ──────────────────────────

echo "\n── invokeMethodWithParameters (mixed sources) ──\n";

$_GET = ['q' => 'search'];
$_POST = ['name' => 'Jane'];
$method = new ReflectionMethod(TestRouteController::class, 'mixed');
$result = $invokeMethod->invoke($handler, $method, $instance, ['id' => '99']);
assert_equals('Mixes path + query + POST params', 'id:99:q:search:name:Jane', $result);
$_GET = [];
$_POST = [];

// ─── 8. invokeMethodWithParameters — default values and null ────────────────

echo "\n── invokeMethodWithParameters (defaults) ──\n";

$method = new ReflectionMethod(TestRouteController::class, 'optional');
$result = $invokeMethod->invoke($handler, $method, $instance, []);
assert_equals('Uses default value when no source matches', 'val:default', $result);

// ─── 9. Priority: path > $_GET > $_POST ─────────────────────────────────────

echo "\n── invokeMethodWithParameters (priority) ──\n";

$_GET = ['id' => 'from_get'];
$method = new ReflectionMethod(TestRouteController::class, 'showUser');
$result = $invokeMethod->invoke($handler, $method, $instance, ['id' => 'from_path']);
assert_equals('Path param takes priority over $_GET', 'user:from_path', $result);
$_GET = [];
