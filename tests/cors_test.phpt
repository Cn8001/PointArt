--TEST--
Cors preflight detection and header building
--FILE--
<?php
require_once __DIR__ . '/test_helpers.php';
require_once __DIR__ . '/../framework/core/Cors.php';

use PointStart\Core\Cors;

// ─── 1. isPreflightRequest() ─────────────────────────────────────────────────

echo "── isPreflightRequest ──\n";

$_SERVER['REQUEST_METHOD'] = 'OPTIONS';
assert_true('OPTIONS is a preflight request', Cors::isPreflightRequest());

$_SERVER['REQUEST_METHOD'] = 'GET';
assert_true('GET is not a preflight request', !Cors::isPreflightRequest());

$_SERVER['REQUEST_METHOD'] = 'POST';
assert_true('POST is not a preflight request', !Cors::isPreflightRequest());

// ─── 2. buildHeaders() — disabled ────────────────────────────────────────────

echo "\n── buildHeaders() disabled ──\n";

$headers = Cors::buildHeaders(['cors' => ['enabled' => false]]);
assert_true('Returns empty array when CORS is disabled', empty($headers));

// ─── 3. buildHeaders() — wildcard origin ─────────────────────────────────────

echo "\n── buildHeaders() wildcard origin ──\n";

$config = [
    'cors' => [
        'enabled'           => true,
        'allowed_origins'   => ['*'],
        'allowed_methods'   => ['GET', 'POST', 'OPTIONS'],
        'allowed_headers'   => ['Content-Type', 'Authorization'],
        'allow_credentials' => false,
        'max_age'           => 3600,
    ],
];
$headers = Cors::buildHeaders($config);
assert_true(
    'Access-Control-Allow-Origin: * is included',
    in_array('Access-Control-Allow-Origin: *', $headers)
);
assert_true(
    'Access-Control-Allow-Methods is included',
    !empty(array_filter($headers, fn($h) => str_starts_with($h, 'Access-Control-Allow-Methods:')))
);
assert_true(
    'Access-Control-Allow-Headers is included',
    !empty(array_filter($headers, fn($h) => str_starts_with($h, 'Access-Control-Allow-Headers:')))
);
assert_true(
    'Access-Control-Max-Age is included',
    !empty(array_filter($headers, fn($h) => str_starts_with($h, 'Access-Control-Max-Age:')))
);

// ─── 4. buildHeaders() — specific origin match ───────────────────────────────

echo "\n── buildHeaders() specific origin — match ──\n";

$_SERVER['HTTP_ORIGIN'] = 'http://allowed.com';
$config = [
    'cors' => [
        'enabled'         => true,
        'allowed_origins' => ['http://allowed.com', 'http://other.com'],
        'allowed_methods' => ['GET', 'POST'],
        'allowed_headers' => ['Content-Type'],
    ],
];
$headers = Cors::buildHeaders($config);
assert_true(
    'Origin header reflects matched origin',
    in_array('Access-Control-Allow-Origin: http://allowed.com', $headers)
);
assert_true(
    'Vary: Origin is included for specific-origin config',
    in_array('Vary: Origin', $headers)
);

// ─── 5. buildHeaders() — specific origin no match ────────────────────────────

echo "\n── buildHeaders() specific origin — no match ──\n";

$_SERVER['HTTP_ORIGIN'] = 'http://hacker.com';
$headers = Cors::buildHeaders($config);
$hasOriginHeader = !empty(
    array_filter($headers, fn($h) => str_starts_with($h, 'Access-Control-Allow-Origin:'))
);
assert_true('No origin header emitted for unrecognized origin', !$hasOriginHeader);
unset($_SERVER['HTTP_ORIGIN']);

// ─── 6. buildHeaders() — allow_credentials ───────────────────────────────────

echo "\n── buildHeaders() allow_credentials ──\n";

$config = [
    'cors' => [
        'enabled'           => true,
        'allowed_origins'   => ['*'],
        'allowed_methods'   => ['GET'],
        'allowed_headers'   => ['Content-Type'],
        'allow_credentials' => true,
    ],
];
$headers = Cors::buildHeaders($config);
assert_true(
    'Access-Control-Allow-Credentials: true is included',
    in_array('Access-Control-Allow-Credentials: true', $headers)
);
--EXPECT--
── isPreflightRequest ──
[PASS] OPTIONS is a preflight request
[PASS] GET is not a preflight request
[PASS] POST is not a preflight request

── buildHeaders() disabled ──
[PASS] Returns empty array when CORS is disabled

── buildHeaders() wildcard origin ──
[PASS] Access-Control-Allow-Origin: * is included
[PASS] Access-Control-Allow-Methods is included
[PASS] Access-Control-Allow-Headers is included
[PASS] Access-Control-Max-Age is included

── buildHeaders() specific origin — match ──
[PASS] Origin header reflects matched origin
[PASS] Vary: Origin is included for specific-origin config

── buildHeaders() specific origin — no match ──
[PASS] No origin header emitted for unrecognized origin

── buildHeaders() allow_credentials ──
[PASS] Access-Control-Allow-Credentials: true is included
