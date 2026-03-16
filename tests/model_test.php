<?php
/**
 * Model Tests — CRUD, reflection helpers, hydration
 * Run standalone: php tests/model_test.php
 * Or via:        php tests/TestSuite.php
 */

require_once __DIR__ . '/../framework/attributes/Entity.php';
require_once __DIR__ . '/../framework/attributes/Column.php';
require_once __DIR__ . '/../framework/attributes/Id.php';
require_once __DIR__ . '/../framework/ORM/Model.php';
require_once __DIR__ . '/test_helpers.php';

use PointStart\ORM\Model;
use PointStart\Attributes\Entity;
use PointStart\Attributes\Column;
use PointStart\Attributes\Id;

// ─── Fixture ─────────────────────────────────────────────────────────────────

#[Entity('users')]
class ModelTestUser extends Model{
    #[Id]
    public ?int $id = null;

    #[Column('name', 'varchar')]
    public string $name;

    #[Column('email', 'varchar')]
    public string $email;
}

// ─── SQLite in-memory setup ───────────────────────────────────────────────────

$sqlite = new PDO('sqlite::memory:');
$sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$sqlite->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$sqlite->exec("CREATE TABLE users (
    id    INTEGER PRIMARY KEY AUTOINCREMENT,
    name  TEXT NOT NULL,
    email TEXT NOT NULL
)");

// Inject — bypasses config.php entirely
Model::$pdo = $sqlite;

// ─── 1. Reflection helpers ────────────────────────────────────────────────────

echo "── reflection helpers ──\n";

assert_equals('getTableName()', 'users', ModelTestUser::getTableName());

$cols = ModelTestUser::getColumns();
assert_equals('getColumns() has 3 entries', 3, count($cols));
assert_equals('id maps to id',     'id',    $cols['id']);
assert_equals('name maps to name', 'name',  $cols['name']);
assert_equals('email maps to email','email', $cols['email']);

assert_equals('getPrimaryKey()', 'id', ModelTestUser::getPrimaryKey());

// ─── 2. save() — INSERT ───────────────────────────────────────────────────────

echo "\n── save (insert) ──\n";

$u = new ModelTestUser();
$u->name  = 'Alice';
$u->email = 'alice@example.com';
$u->save();

assert_true('id assigned after insert', $u->id !== null && $u->id > 0);
assert_equals('id is 1', 1, (int)$u->id);

// ─── 3. find() ───────────────────────────────────────────────────────────────

echo "\n── find ──\n";

$found = ModelTestUser::find(1);
assert_true('find returns instance', $found instanceof ModelTestUser);
assert_equals('find name', 'Alice', $found->name);
assert_equals('find email', 'alice@example.com', $found->email);

$notFound = ModelTestUser::find(999);
assert_equals('find missing returns null', null, $notFound);

// ─── 4. findAll() ────────────────────────────────────────────────────────────

echo "\n── findAll ──\n";

$u2 = new ModelTestUser();
$u2->name  = 'Bob';
$u2->email = 'bob@example.com';
$u2->save();

$all = ModelTestUser::findAll();
assert_equals('findAll returns 2 rows', 2, count($all));
assert_true('findAll items are ModelTestUser', $all[0] instanceof ModelTestUser);

// ─── 5. findBy() ─────────────────────────────────────────────────────────────

echo "\n── findBy ──\n";

$byName = ModelTestUser::findBy(['name' => 'Alice']);
assert_equals('findBy name count', 1, count($byName));
assert_equals('findBy name value', 'Alice', $byName[0]->name);

$byEmail = ModelTestUser::findBy(['email' => 'nobody@example.com']);
assert_equals('findBy no match returns empty', 0, count($byEmail));

$ordered = ModelTestUser::findBy([], 'name ASC');
assert_equals('findBy order by name[0]', 'Alice', $ordered[0]->name);
assert_equals('findBy order by name[1]', 'Bob',   $ordered[1]->name);

$limited = ModelTestUser::findBy([], null, 1);
assert_equals('findBy limit 1', 1, count($limited));

// ─── 6. findOne() ────────────────────────────────────────────────────────────

echo "\n── findOne ──\n";

$one = ModelTestUser::findOne(['name' => 'Bob']);
assert_true('findOne returns instance', $one instanceof ModelTestUser);
assert_equals('findOne name', 'Bob', $one->name);

$none = ModelTestUser::findOne(['name' => 'Nobody']);
assert_equals('findOne no match returns null', null, $none);

// ─── 7. save() — UPDATE ──────────────────────────────────────────────────────

echo "\n── save (update) ──\n";

$alice = ModelTestUser::find(1);
$alice->name = 'Alice Updated';
$alice->save();

$reloaded = ModelTestUser::find(1);
assert_equals('update persisted', 'Alice Updated', $reloaded->name);
assert_equals('email unchanged after update', 'alice@example.com', $reloaded->email);

// ─── 8. delete() ─────────────────────────────────────────────────────────────

echo "\n── delete ──\n";

$bob = ModelTestUser::find(2);
$bob->delete();

$afterDelete = ModelTestUser::find(2);
assert_equals('deleted record returns null', null, $afterDelete);

$remaining = ModelTestUser::findAll();
assert_equals('one record remains', 1, count($remaining));
