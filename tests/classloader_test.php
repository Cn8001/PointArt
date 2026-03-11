<?php
/**
 * ClassLoader (RouteLoader) Tests — actual UserController, no mocks
 * Run: php tests/classloader_test.php
 */

require_once __DIR__ . '/../framework/attributes/Route.php';
require_once __DIR__ . '/../framework/attributes/Router.php';
require_once __DIR__ . '/../framework/attributes/Service.php';
require_once __DIR__ . '/../framework/core/ClassLoader.php';

use PointStart\Core\RouteLoader;

// ─── Helpers ──────────────────────────────────────────────────────────────────

$passed = 0;
$failed = 0;

function assert_true(string $label, bool $condition): void {
    global $passed, $failed;
    if ($condition) { echo "[PASS] $label\n"; $passed++; }
    else             { echo "[FAIL] $label\n"; $failed++; }
}

function assert_equals(string $label, mixed $expected, mixed $actual): void {
    global $passed, $failed;
    if ($expected === $actual) {
        echo "[PASS] $label\n";
        $passed++;
    } else {
        echo "[FAIL] $label — expected " . var_export($expected, true)
           . ", got " . var_export($actual, true) . "\n";
        $failed++;
    }
}

// ─── Boot ─────────────────────────────────────────────────────────────────────

$controllersDir = __DIR__ . '/../app/components';

$loader = new RouteLoader();
$loader->loadClasses($controllersDir);

// ─── Generic: all .php files in controllers/ must be loaded ───────────────────

$phpFiles = array_filter(
    scandir($controllersDir),
    fn($f) => str_ends_with($f, '.php')
);

foreach ($phpFiles as $file) {
    $className = basename($file, '.php');
    assert_true(
        "$className is defined after loadClasses()",
        class_exists($className, false)
    );
}

// ─── Tests ────────────────────────────────────────────────────────────────────

// 1. Class loaded into memory
assert_true(
    'UserController is defined after loadClasses()',
    class_exists('UserController', false)
);

$ref = new ReflectionClass('UserController');

// 2. #[Router] on class
assert_true(
    'UserController has #[Router] attribute',
    count($ref->getAttributes(PointStart\Attributes\Router::class)) === 1
);

// 3. No #[Service] on class
assert_true(
    'UserController has no #[Service] attribute',
    count($ref->getAttributes(PointStart\Attributes\Service::class)) === 0
);

// 4. Exactly 2 methods have #[Route]
$routedMethods = [];
foreach ($ref->getMethods() as $method) {
    if (!empty($method->getAttributes(PointStart\Attributes\Route::class))) {
        $routedMethods[] = $method->getName();
    }
}

assert_equals(
    'UserController has exactly 2 #[Route] methods',
    ['index', 'show'],
    $routedMethods
);

// 5. helper() has no #[Route]
assert_true(
    'UserController::helper() has no #[Route]',
    !in_array('helper', $routedMethods)
);

// 6. Methods return expected values
$controller = new UserController();

assert_equals('index() returns "user.list"', 'user.list', $controller->index());
assert_equals('show() returns "user.show"',  'user.show', $controller->show());

// ─── Summary ──────────────────────────────────────────────────────────────────

echo "\n" . ($failed === 0 ? "All tests passed." : "$failed test(s) failed.")
   . " ($passed passed, $failed failed)\n";
exit($failed > 0 ? 1 : 0);
