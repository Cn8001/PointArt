--TEST--
Repository dynamic __call dispatch
--FILE--
<?php
require_once __DIR__ . '/../framework/attributes/Entity.php';
require_once __DIR__ . '/../framework/attributes/Column.php';
require_once __DIR__ . '/../framework/attributes/Id.php';
require_once __DIR__ . '/../framework/ORM/Model.php';
require_once __DIR__ . '/../framework/ORM/Repository.php';
require_once __DIR__ . '/test_helpers.php';

use PointStart\ORM\Model;
use PointStart\ORM\Repository;
use PointStart\Attributes\Entity;
use PointStart\Attributes\Column;
use PointStart\Attributes\Id;

// ─── Fixtures ─────────────────────────────────────────────────────────────────

#[Entity('members')]
class RepoTestMember extends Model{
    #[Id]
    public ?int $id = null;

    #[Column('name', 'varchar')]
    public string $name;

    #[Column('email', 'varchar')]
    public string $email;

    #[Column('age', 'int')]
    public int $age;

    #[Column('status', 'varchar')]
    public string $status;
}

class MemberRepository extends Repository{
    protected string $entityClass = RepoTestMember::class;
}

// ─── SQLite in-memory setup ───────────────────────────────────────────────────

$sqlite = new PDO('sqlite::memory:');
$sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$sqlite->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$sqlite->exec("CREATE TABLE members (
    id     INTEGER PRIMARY KEY AUTOINCREMENT,
    name   TEXT    NOT NULL,
    email  TEXT    NOT NULL,
    age    INTEGER NOT NULL,
    status TEXT    NOT NULL
)");

Model::$pdo = $sqlite;

// Seed data
$rows = [
    ['Alice', 'alice@example.com', 30, 'active'],
    ['Bob',   'bob@example.com',   25, 'inactive'],
    ['Carol', 'carol@example.com', 35, 'active'],
    ['Dave',  'dave@example.com',  25, 'active'],
];
$ins = $sqlite->prepare("INSERT INTO members (name, email, age, status) VALUES (?, ?, ?, ?)");
foreach($rows as $r) $ins->execute($r);

$repo = new MemberRepository();

// ─── Built-in delegates ───────────────────────────────────────────────────────

echo "── built-in delegates ──\n";

$all = $repo->findAll();
assert_equals('findAll returns 4', 4, count($all));

$found = $repo->find(1);
assert_true('find returns instance', $found instanceof RepoTestMember);
assert_equals('find by id name', 'Alice', $found->name);

// save (insert via repository)
$m = new RepoTestMember();
$m->name   = 'Eve';
$m->email  = 'eve@example.com';
$m->age    = 28;
$m->status = 'active';
$repo->save($m);
assert_true('save assigns id', $m->id !== null && $m->id > 0);

// delete via repository
$repo->delete($m);
assert_equals('delete removes record', null, $repo->find($m->id));

// deleteById
$repo->deleteById(4); // Dave
assert_equals('deleteById removes record', null, $repo->find(4));
$all = $repo->findAll();
assert_equals('3 records remain after deleteById', 3, count($all));

// ─── Dynamic: findBy ─────────────────────────────────────────────────────────

echo "\n── findByName ──\n";

$results = $repo->findByName('Alice');
assert_equals('findByName count', 1, count($results));
assert_equals('findByName name', 'Alice', $results[0]->name);

echo "\n── findByNameAndEmail ──\n";

$results = $repo->findByNameAndEmail('Alice', 'alice@example.com');
assert_equals('findByNameAndEmail count', 1, count($results));

$results = $repo->findByNameAndEmail('Alice', 'wrong@example.com');
assert_equals('findByNameAndEmail no match', 0, count($results));

echo "\n── findByAgeGreaterThan ──\n";

$results = $repo->findByAgeGreaterThan(25);
assert_equals('findByAgeGreaterThan count', 2, count($results)); // Alice(30), Carol(35)

echo "\n── findByAgeLessThan ──\n";

$results = $repo->findByAgeLessThan(30);
assert_equals('findByAgeLessThan count', 1, count($results)); // Bob(25)

echo "\n── findByAgeGreaterThanEqual ──\n";

$results = $repo->findByAgeGreaterThanEqual(30);
assert_equals('findByAgeGreaterThanEqual count', 2, count($results)); // Alice(30), Carol(35)

echo "\n── findByStatusNot ──\n";

$results = $repo->findByStatusNot('inactive');
assert_equals('findByStatusNot count', 2, count($results)); // Alice, Carol

echo "\n── findByNameLike ──\n";

$results = $repo->findByNameLike('%li%'); // Alice
assert_equals('findByNameLike count', 1, count($results));
assert_equals('findByNameLike name', 'Alice', $results[0]->name);

// ─── Dynamic: findOneBy ───────────────────────────────────────────────────────

echo "\n── findOneByEmail ──\n";

$one = $repo->findOneByEmail('carol@example.com');
assert_true('findOneByEmail returns instance', $one instanceof RepoTestMember);
assert_equals('findOneByEmail name', 'Carol', $one->name);

$none = $repo->findOneByEmail('nobody@example.com');
assert_equals('findOneByEmail no match returns null', null, $none);

// ─── Dynamic: countBy ────────────────────────────────────────────────────────

echo "\n── countByStatus ──\n";

$count = $repo->countByStatus('active');
assert_equals('countByStatus active', 2, $count);

$count = $repo->countByStatus('inactive');
assert_equals('countByStatus inactive', 1, $count);

// ─── Dynamic: existsBy ────────────────────────────────────────────────────────

echo "\n── existsByEmail ──\n";

assert_equals('existsByEmail true',  true,  $repo->existsByEmail('alice@example.com'));
assert_equals('existsByEmail false', false, $repo->existsByEmail('ghost@example.com'));

// ─── Dynamic: deleteBy ────────────────────────────────────────────────────────

echo "\n── deleteByStatus ──\n";

$repo->deleteByStatus('inactive'); // removes Bob
$remaining = $repo->findAll();
assert_equals('deleteByStatus removes records', 2, count($remaining));

$allActive = $repo->findByStatus('active');
assert_equals('all remaining are active', 2, count($allActive));
--EXPECT--
── built-in delegates ──
[PASS] findAll returns 4
[PASS] find returns instance
[PASS] find by id name
[PASS] save assigns id
[PASS] delete removes record
[PASS] deleteById removes record
[PASS] 3 records remain after deleteById

── findByName ──
[PASS] findByName count
[PASS] findByName name

── findByNameAndEmail ──
[PASS] findByNameAndEmail count
[PASS] findByNameAndEmail no match

── findByAgeGreaterThan ──
[PASS] findByAgeGreaterThan count

── findByAgeLessThan ──
[PASS] findByAgeLessThan count

── findByAgeGreaterThanEqual ──
[PASS] findByAgeGreaterThanEqual count

── findByStatusNot ──
[PASS] findByStatusNot count

── findByNameLike ──
[PASS] findByNameLike count
[PASS] findByNameLike name

── findOneByEmail ──
[PASS] findOneByEmail returns instance
[PASS] findOneByEmail name
[PASS] findOneByEmail no match returns null

── countByStatus ──
[PASS] countByStatus active
[PASS] countByStatus inactive

── existsByEmail ──
[PASS] existsByEmail true
[PASS] existsByEmail false

── deleteByStatus ──
[PASS] deleteByStatus removes records
[PASS] all remaining are active
