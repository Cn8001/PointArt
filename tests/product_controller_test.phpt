--TEST--
ProductController GET and POST request simulation
--FILE--
<?php
require_once __DIR__ . '/test_helpers.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../framework/attributes/Entity.php';
require_once __DIR__ . '/../framework/attributes/Column.php';
require_once __DIR__ . '/../framework/attributes/Id.php';
require_once __DIR__ . '/../framework/attributes/Query.php';
require_once __DIR__ . '/../framework/attributes/Route.php';
require_once __DIR__ . '/../framework/attributes/Router.php';
require_once __DIR__ . '/../framework/attributes/Service.php';
require_once __DIR__ . '/../framework/attributes/Wired.php';
require_once __DIR__ . '/../framework/attributes/RequestParam.php';
require_once __DIR__ . '/../framework/core/ClassLoader.php';
require_once __DIR__ . '/../framework/core/Container.php';
require_once __DIR__ . '/../framework/core/HttpResponses.php';
require_once __DIR__ . '/../framework/core/RouteHandler.php';
require_once __DIR__ . '/../framework/core/Renderer.php';
require_once __DIR__ . '/../framework/ORM/Model.php';
require_once __DIR__ . '/../framework/ORM/Repository.php';
require_once __DIR__ . '/../app/models/Product.php';
require_once __DIR__ . '/../app/repositories/ProductRepository.php';
require_once __DIR__ . '/../app/components/ProductController.php';

use PointStart\Core\{ClassLoader, Container, RouteHandler};
use PointStart\ORM\Model;

// ─── SQLite in-memory setup ───────────────────────────────────────────────────

$sqlite = new PDO('sqlite::memory:');
$sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$sqlite->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$sqlite->exec("CREATE TABLE products (
    id      INTEGER PRIMARY KEY AUTOINCREMENT,
    name    TEXT    NOT NULL,
    price   REAL    NOT NULL,
    stock   INTEGER NOT NULL,
    active  INTEGER NOT NULL DEFAULT 1
)");
Model::$pdo = $sqlite;

// ─── Bootstrap ───────────────────────────────────────────────────────────────

ClassLoader::clearCache();
$loader = new ClassLoader();
$loader->loadClasses(__DIR__ . '/../app/components');

$container   = new Container();
$routeHandler = new RouteHandler($container, $loader);

// Helper: simulate a request, return output
function dispatch(RouteHandler $rh, string $uri, string $method): string {
    ob_start();
    $result = $rh->dispatch($uri, $method);
    if (isset($result)) echo $result;
    return ob_get_clean();
}

// ─── POST /product/create ─────────────────────────────────────────────────────

echo "── POST /product/create ──\n";

$_POST = ['name' => 'Widget', 'price' => '9.99', 'stock' => '100'];
$out = dispatch($routeHandler, '/product/create', 'POST');
assert_true('create renders product name', str_contains($out, 'Widget'));
assert_true('create renders price', str_contains($out, '9.99'));

$_POST = ['name' => 'Gadget', 'price' => '49.99', 'stock' => '3'];
dispatch($routeHandler, '/product/create', 'POST');

$_POST = ['name' => 'Doohickey', 'price' => '99.99', 'stock' => '0'];
dispatch($routeHandler, '/product/create', 'POST');
$_POST = [];

// Verify records in DB
$all = Product::findAll();
assert_equals('3 products in DB', 3, count($all));

// ─── GET /product/list ────────────────────────────────────────────────────────

echo "\n── GET /product/list ──\n";

$out = dispatch($routeHandler, '/product/list', 'GET');
assert_true('list renders Widget', str_contains($out, 'Widget'));
assert_true('list renders Gadget', str_contains($out, 'Gadget'));
assert_true('list has add form', str_contains($out, '/product/create'));

// ─── GET /product/show/{id} ───────────────────────────────────────────────────

echo "\n── GET /product/show/{id} ──\n";

$out = dispatch($routeHandler, '/product/show/1', 'GET');
assert_true('show renders product name', str_contains($out, 'Widget'));
assert_true('show has update form', str_contains($out, '/product/update/1'));

$out = dispatch($routeHandler, '/product/show/999', 'GET');
assert_true('show missing renders not found', str_contains($out, 'not found'));

// ─── GET /product/search?name=Widget ─────────────────────────────────────────

echo "\n── GET /product/search ──\n";

$_GET = ['name' => 'Widget'];
$out = dispatch($routeHandler, '/product/search', 'GET');
assert_true('search renders Widget', str_contains($out, 'Widget'));
assert_true('search excludes Gadget', !str_contains($out, 'Gadget'));
$_GET = [];

// ─── GET /product/affordable ─────────────────────────────────────────────────

echo "\n── GET /product/affordable (#[Query]) ──\n";

$repo = ProductRepository::make();
$affordable = $repo->findAffordable(50.0);
assert_equals('findAffordable returns 2 products', 2, count($affordable));
assert_equals('Widget is affordable', 'Widget', $affordable[0]->name);
assert_equals('Gadget is affordable', 'Gadget', $affordable[1]->name);

$_GET = ['maxPrice' => '50'];
$out = dispatch($routeHandler, '/product/affordable', 'GET');
assert_true('affordable renders Widget', str_contains($out, 'Widget'));
assert_true('affordable excludes Doohickey', !str_contains($out, 'Doohickey'));
$_GET = [];

// ─── GET /product/low-stock ───────────────────────────────────────────────────

echo "\n── GET /product/low-stock (__call) ──\n";

$lowStock = $repo->findByStockLessThan(1);
assert_equals('findByStockLessThan returns 1 product', 1, count($lowStock));
assert_equals('Doohickey has 0 stock', 'Doohickey', $lowStock[0]->name);

$_GET = ['threshold' => '1'];
$out = dispatch($routeHandler, '/product/low-stock', 'GET');
assert_true('low-stock renders Doohickey', str_contains($out, 'Doohickey'));
$_GET = [];

// ─── POST /product/update/{id} ────────────────────────────────────────────────

echo "\n── POST /product/update/{id} ──\n";

$_POST = ['price' => '7.99', 'stock' => '200'];
$out = dispatch($routeHandler, '/product/update/1', 'POST');
assert_true('update renders updated price', str_contains($out, '7.99'));

$updated = Product::find(1);
assert_equals('price updated in DB', 7.99, (float)$updated->price);
assert_equals('stock updated in DB', 200, (int)$updated->stock);
assert_equals('name unchanged', 'Widget', $updated->name);

$_POST = ['price' => '1.00', 'stock' => '1'];
$out = dispatch($routeHandler, '/product/update/999', 'POST');
assert_true('update missing renders not found', str_contains($out, 'not found'));
$_POST = [];

// ─── POST /product/delete/{id} ────────────────────────────────────────────────

echo "\n── POST /product/delete/{id} ──\n";

$out = dispatch($routeHandler, '/product/delete/3', 'POST');
assert_true('delete renders remaining list', str_contains($out, 'Widget'));
assert_true('deleted product gone from list', !str_contains($out, 'Doohickey'));

$deleted = Product::find(3);
assert_equals('product gone from DB', null, $deleted);

$remaining = Product::findAll();
assert_equals('2 products remain', 2, count($remaining));
--EXPECT--
── POST /product/create ──
[PASS] create renders product name
[PASS] create renders price
[PASS] 3 products in DB

── GET /product/list ──
[PASS] list renders Widget
[PASS] list renders Gadget
[PASS] list has add form

── GET /product/show/{id} ──
[PASS] show renders product name
[PASS] show has update form
[PASS] show missing renders not found

── GET /product/search ──
[PASS] search renders Widget
[PASS] search excludes Gadget

── GET /product/affordable (#[Query]) ──
[PASS] findAffordable returns 2 products
[PASS] Widget is affordable
[PASS] Gadget is affordable
[PASS] affordable renders Widget
[PASS] affordable excludes Doohickey

── GET /product/low-stock (__call) ──
[PASS] findByStockLessThan returns 1 product
[PASS] Doohickey has 0 stock
[PASS] low-stock renders Doohickey

── POST /product/update/{id} ──
[PASS] update renders updated price
[PASS] price updated in DB
[PASS] stock updated in DB
[PASS] name unchanged
[PASS] update missing renders not found

── POST /product/delete/{id} ──
[PASS] delete renders remaining list
[PASS] deleted product gone from list
[PASS] product gone from DB
[PASS] 2 products remain
