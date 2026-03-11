<?php
/**
 * Container Tests
 * Run: php tests/container_test.php
 */

require_once __DIR__ . '/../framework/attributes/Route.php';
require_once __DIR__ . '/../framework/attributes/Router.php';
require_once __DIR__ . '/../framework/attributes/Service.php';
require_once __DIR__ . '/../framework/core/ClassLoader.php';
require_once __DIR__ . '/../framework/core/Container.php';

use PointStart\Core\Container;

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

$container = new Container();
$ref = new ReflectionClass($container);
$instancesProp = $ref->getProperty('instances');
$instancesProp->setAccessible(true);

// ─── Tests ────────────────────────────────────────────────────────────────────

// 1. Container instantiates
assert_true('Container instantiates', $container instanceof Container);

// 2. loadClassLoader() registers autoloader — classes NOT in memory yet (lazy)
assert_true('UserController NOT in memory after construct', !class_exists('UserController', false));
assert_true('UserService NOT in memory after construct',    !class_exists('UserService', false));

// 3. $instances is empty before loadContainer()
assert_equals('$instances is empty before loadContainer()', [], $instancesProp->getValue($container));

// 4. generateInstances() stores instances in $this->instances
$generateInstances = $ref->getMethod('generateInstances');
$generateInstances->setAccessible(true);
$generateInstances->invoke($container, ['UserService' => 'UserService']);

$instances = $instancesProp->getValue($container);
assert_true(
    'generateInstances() creates and stores a UserService instance',
    isset($instances['UserService']) && $instances['UserService'] instanceof UserService
);

// ─── Summary ──────────────────────────────────────────────────────────────────

echo "\n" . ($failed === 0 ? "All tests passed." : "$failed test(s) failed.")
   . " ($passed passed, $failed failed)\n";
exit($failed > 0 ? 1 : 0);
