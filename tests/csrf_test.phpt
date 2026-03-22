--TEST--
CSRF token generation, validation, and RouteHandler enforcement
--FILE--
<?php
require_once __DIR__ . '/test_helpers.php';
require_once __DIR__ . '/../framework/core/Csrf.php';
require_once __DIR__ . '/../framework/attributes/Route.php';
require_once __DIR__ . '/../framework/attributes/Router.php';
require_once __DIR__ . '/../framework/attributes/Service.php';
require_once __DIR__ . '/../framework/attributes/Wired.php';
require_once __DIR__ . '/../framework/attributes/RequestParam.php';
require_once __DIR__ . '/../framework/core/ClassLoader.php';
require_once __DIR__ . '/../framework/core/Container.php';
require_once __DIR__ . '/../framework/core/HttpResponses.php';
require_once __DIR__ . '/../framework/core/RouteHandler.php';

use PointStart\Core\Csrf;
use PointStart\Core\ClassLoader;
use PointStart\Core\Container;
use PointStart\Core\RouteHandler;

// ─── Fixture controller ───────────────────────────────────────────────────────

class CsrfTestController {
    public function submit(): string { return 'submitted'; }
    public function exempt(): string  { return 'exempt'; }
}

// Container must be created FIRST: its constructor calls loadClassLoader() which
// resets ClassLoader::$routes. We override routes afterwards.
$container = new Container();

$clRef = new ReflectionClass(ClassLoader::class);
$routesProp = $clRef->getProperty('routes');
$routesProp->setAccessible(true);
$routesProp->setValue(null, [
    'POST' => [
        '/form/submit' => ['class' => 'CsrfTestController', 'method' => 'submit', 'csrfExempt' => false],
        '/form/exempt' => ['class' => 'CsrfTestController', 'method' => 'exempt',  'csrfExempt' => true],
    ],
]);

$classLoader = new ClassLoader();

// ─── 1. csrf_token() ─────────────────────────────────────────────────────────

echo "── csrf_token() ──\n";

$token = csrf_token();
assert_true('Returns a non-empty string',  is_string($token) && !empty($token));
assert_equals('Token is 64 hex characters', 64, strlen($token));
assert_true('Token contains only hex characters', ctype_xdigit($token));
assert_equals('Repeated calls return the same token', $token, csrf_token());

// ─── 2. csrf_field() ─────────────────────────────────────────────────────────

echo "\n── csrf_field() ──\n";

$field = csrf_field();
assert_true('Output is a hidden input element',   str_contains($field, '<input type="hidden"'));
assert_true('Input name is _csrf_token',          str_contains($field, 'name="_csrf_token"'));
assert_true('Input value contains the token',     str_contains($field, $token));

// ─── 3. Csrf::validate() ─────────────────────────────────────────────────────

echo "\n── Csrf::validate() ──\n";

// Correct token in POST body
$_POST['_csrf_token'] = $token;
assert_true('Accepts correct token from $_POST',   Csrf::validate());

// Wrong token in POST body
$_POST['_csrf_token'] = 'wrongtoken';
assert_true('Rejects wrong token',                 !Csrf::validate());

// Empty token in POST body
$_POST['_csrf_token'] = '';
assert_true('Rejects empty token',                 !Csrf::validate());

// Correct token via X-CSRF-Token request header
unset($_POST['_csrf_token']);
$_SERVER['HTTP_X_CSRF_TOKEN'] = $token;
assert_true('Accepts correct token from header',   Csrf::validate());
unset($_SERVER['HTTP_X_CSRF_TOKEN']);

// No token at all
$_POST = [];
assert_true('Rejects missing token',               !Csrf::validate());

// ─── 4. RouteHandler — POST without token ────────────────────────────────────

echo "\n── RouteHandler — POST without token ──\n";

$handler = new RouteHandler($container, $classLoader, ['csrf' => ['enabled' => true]]);
$_SERVER['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';
$_POST = [];

ob_start();
$handler->dispatch('/form/submit', 'POST');
$out = ob_get_clean();
assert_true('POST without token triggers 403', str_contains($out, '403'));

// ─── 5. RouteHandler — POST with valid token ─────────────────────────────────

echo "\n── RouteHandler — POST with valid token ──\n";

$_POST['_csrf_token'] = $token;
ob_start();
$result = $handler->dispatch('/form/submit', 'POST');
ob_get_clean();
assert_equals('POST with valid token reaches controller', 'submitted', $result);
$_POST = [];

// ─── 6. csrfExempt route ─────────────────────────────────────────────────────

echo "\n── csrfExempt route ──\n";

$_POST = [];
ob_start();
$result = $handler->dispatch('/form/exempt', 'POST');
ob_get_clean();
assert_equals('csrfExempt route skips CSRF check', 'exempt', $result);

// ─── 7. JSON requests bypass CSRF ────────────────────────────────────────────

echo "\n── JSON POST bypasses CSRF ──\n";

$_POST = [];
$_SERVER['CONTENT_TYPE'] = 'application/json';
ob_start();
$result = $handler->dispatch('/form/submit', 'POST');
ob_get_clean();
assert_equals('application/json request skips CSRF check', 'submitted', $result);
$_SERVER['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';

// ─── 8. No session token — POST passes (API / curl clients) ──────────────────

echo "\n── No session token — POST passes ──\n";

// Simulate a request with no established session token (e.g. API client)
$savedToken = $_SESSION['_csrf_token'] ?? null;
unset($_SESSION['_csrf_token']);
$_POST = [];
$_SERVER['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';
ob_start();
$result = $handler->dispatch('/form/submit', 'POST');
ob_get_clean();
assert_equals('POST without session token passes (API client)', 'submitted', $result);
// Restore for subsequent tests
if($savedToken !== null) $_SESSION['_csrf_token'] = $savedToken;

// ─── 9. CSRF globally disabled ───────────────────────────────────────────────

echo "\n── CSRF globally disabled ──\n";

$handlerOff = new RouteHandler($container, $classLoader, ['csrf' => ['enabled' => false]]);
$_SESSION['_csrf_token'] = $token; // session token is set, but CSRF is off
$_POST = [];
ob_start();
$result = $handlerOff->dispatch('/form/submit', 'POST');
ob_get_clean();
assert_equals('POST with session token passes when CSRF disabled', 'submitted', $result);

// ─── Cleanup ──────────────────────────────────────────────────────────────────

ClassLoader::clearCache();
--EXPECT--
── csrf_token() ──
[PASS] Returns a non-empty string
[PASS] Token is 64 hex characters
[PASS] Token contains only hex characters
[PASS] Repeated calls return the same token

── csrf_field() ──
[PASS] Output is a hidden input element
[PASS] Input name is _csrf_token
[PASS] Input value contains the token

── Csrf::validate() ──
[PASS] Accepts correct token from $_POST
[PASS] Rejects wrong token
[PASS] Rejects empty token
[PASS] Accepts correct token from header
[PASS] Rejects missing token

── RouteHandler — POST without token ──
[PASS] POST without token triggers 403

── RouteHandler — POST with valid token ──
[PASS] POST with valid token reaches controller

── csrfExempt route ──
[PASS] csrfExempt route skips CSRF check

── JSON POST bypasses CSRF ──
[PASS] application/json request skips CSRF check

── No session token — POST passes ──
[PASS] POST without session token passes (API client)

── CSRF globally disabled ──
[PASS] POST with session token passes when CSRF disabled
