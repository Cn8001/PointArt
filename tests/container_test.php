<?php
/**
 * Container Tests — dependency resolution, instance creation, injection
 * Run: php tests/container_test.php
 */

require_once __DIR__ . '/../framework/attributes/Route.php';
require_once __DIR__ . '/../framework/attributes/Router.php';
require_once __DIR__ . '/../framework/attributes/Service.php';
require_once __DIR__ . '/../framework/attributes/Wired.php';
require_once __DIR__ . '/../framework/core/ClassLoader.php';
require_once __DIR__ . '/../framework/core/Container.php';

use PointStart\Core\Container;
use PointStart\Attributes\Wired;
use PointStart\Attributes\Service;

// ─── Test Fixtures ───────────────────────────────────────────────────────────

#[Service(name: "Logger")]
class Logger {
    public function log(string $msg): string {
        return "logged: $msg";
    }
}

#[Service(name: "Repository")]
class Repository {
    #[Wired]
    public Logger $logger;

    public function find(): string {
        return "found";
    }
}

#[Service(name: "AppService")]
class AppService {
    #[Wired]
    public Repository $repository;

    #[Wired]
    public Logger $logger;
}

#[Service(name: "OptionalDeps")]
class OptionalDeps {
    #[Wired(required: false)]
    public ?Logger $logger = null;

    #[Wired(required: true)]
    public Repository $repository;
}

#[Service(name: "NoDeps")]
class NoDeps {
    public string $value = "plain";
}

// ─── Helpers ─────────────────────────────────────────────────────────────────

require_once __DIR__ . '/test_helpers.php';

// ─── Boot ────────────────────────────────────────────────────────────────────

$container = new Container();
$ref = new ReflectionClass($container);

$instancesProp = $ref->getProperty('instances');
$instancesProp->setAccessible(true);

$resolveDeps = $ref->getMethod('resolve_dependencies');
$resolveDeps->setAccessible(true);

$generateInstances = $ref->getMethod('generateInstances');
$generateInstances->setAccessible(true);

// ─── 1. resolve_dependencies ─────────────────────────────────────────────────

echo "── resolve_dependencies ──\n";

// Class with no #[Wired] properties
$deps = $resolveDeps->invoke($container, 'Logger');
assert_equals('Logger has no dependencies', [], $deps);

$deps = $resolveDeps->invoke($container, 'NoDeps');
assert_equals('NoDeps has no wired dependencies', [], $deps);

// Class with one #[Wired] property
$deps = $resolveDeps->invoke($container, 'Repository');
assert_equals('Repository has 1 dependency', 1, count($deps));
assert_equals('Repository depends on Logger (property)', 'logger', $deps[0]['property']);
assert_equals('Repository depends on Logger (type)', 'Logger', $deps[0]['type']);
assert_equals('Repository dependency is required', true, $deps[0]['required']);

// Class with multiple #[Wired] properties
$deps = $resolveDeps->invoke($container, 'AppService');
assert_equals('AppService has 2 dependencies', 2, count($deps));

$depTypes = array_column($deps, 'type');
assert_true('AppService depends on Repository', in_array('Repository', $depTypes));
assert_true('AppService depends on Logger', in_array('Logger', $depTypes));

// Class with optional dependency
$deps = $resolveDeps->invoke($container, 'OptionalDeps');
assert_equals('OptionalDeps has 2 wired properties', 2, count($deps));

$loggerDep = array_values(array_filter($deps, fn($d) => $d['type'] === 'Logger'))[0];
assert_equals('OptionalDeps Logger is not required', false, $loggerDep['required']);

$repoDep = array_values(array_filter($deps, fn($d) => $d['type'] === 'Repository'))[0];
assert_equals('OptionalDeps Repository is required', true, $repoDep['required']);

// ─── 2. generateInstances — no dependencies ─────────────────────────────────

echo "\n── generateInstances (no deps) ──\n";

// Reset instances
$instancesProp->setValue($container, []);

$generateInstances->invoke($container, ['Logger']);
$instances = $instancesProp->getValue($container);

assert_true('Logger instance created', isset($instances['Logger']));
assert_true('Logger is correct type', $instances['Logger'] instanceof Logger);

// ─── 3. generateInstances — single dependency ───────────────────────────────

echo "\n── generateInstances (single dep) ──\n";

$instancesProp->setValue($container, []);

$generateInstances->invoke($container, ['Repository']);
$instances = $instancesProp->getValue($container);

assert_true('Repository instance created', isset($instances['Repository']));
assert_true('Repository is correct type', $instances['Repository'] instanceof Repository);

// Logger should be created as a sub-dependency
assert_true('Logger created as sub-dependency', isset($instances['Logger']));
assert_true('Logger sub-dep is correct type', $instances['Logger'] instanceof Logger);

// Verify injection — Repository.logger should be the Logger instance
assert_true(
    'Repository.logger is injected',
    isset($instances['Repository']->logger) && $instances['Repository']->logger instanceof Logger
);
assert_true(
    'Repository.logger is the same instance from container',
    $instances['Repository']->logger === $instances['Logger']
);

// ─── 4. generateInstances — chained dependencies ────────────────────────────

echo "\n── generateInstances (chained deps) ──\n";

$instancesProp->setValue($container, []);

$generateInstances->invoke($container, ['AppService']);
$instances = $instancesProp->getValue($container);

assert_true('AppService instance created', isset($instances['AppService']));
assert_true('Repository created for AppService', isset($instances['Repository']));
assert_true('Logger created for Repository chain', isset($instances['Logger']));

// AppService should have both dependencies injected
assert_true(
    'AppService.repository is injected',
    isset($instances['AppService']->repository) && $instances['AppService']->repository instanceof Repository
);
assert_true(
    'AppService.logger is injected',
    isset($instances['AppService']->logger) && $instances['AppService']->logger instanceof Logger
);

// Repository inside AppService should also have its Logger injected
assert_true(
    'AppService.repository.logger is injected (nested)',
    isset($instances['AppService']->repository->logger) && $instances['AppService']->repository->logger instanceof Logger
);

// All Logger references should be the same singleton instance
assert_true(
    'Logger is shared across AppService and Repository',
    $instances['AppService']->logger === $instances['Repository']->logger
);

// ─── 5. generateInstances — skips already-created instances ─────────────────

echo "\n── generateInstances (no duplicates) ──\n";

$instancesProp->setValue($container, []);

$generateInstances->invoke($container, ['Logger']);
$firstLogger = $instancesProp->getValue($container)['Logger'];

$generateInstances->invoke($container, ['Logger']);
$secondLogger = $instancesProp->getValue($container)['Logger'];

assert_true('Same Logger instance on second call (not recreated)', $firstLogger === $secondLogger);

// ─── 6. generateInstances — optional deps not resolved eagerly ──────────────

echo "\n── generateInstances (optional deps) ──\n";

$instancesProp->setValue($container, []);

$generateInstances->invoke($container, ['OptionalDeps']);
$instances = $instancesProp->getValue($container);

assert_true('OptionalDeps instance created', isset($instances['OptionalDeps']));
assert_true('Repository created (required dep)', isset($instances['Repository']));

// ─── 7. generateInstances — multiple classes at once ────────────────────────

echo "\n── generateInstances (batch) ──\n";

$instancesProp->setValue($container, []);

$generateInstances->invoke($container, ['NoDeps', 'Logger', 'Repository']);
$instances = $instancesProp->getValue($container);

assert_equals('Batch creates 3 instances', 3, count($instances));
assert_true('NoDeps in batch', $instances['NoDeps'] instanceof NoDeps);
assert_true('Logger in batch', $instances['Logger'] instanceof Logger);
assert_true('Repository in batch', $instances['Repository'] instanceof Repository);

