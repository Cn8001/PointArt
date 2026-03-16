# PointArt

**Ship powerful features with the simplicity of plain PHP.**

A plain PHP micro-framework modelled after Spring Boot's programming model.

- **Attribute-based routing** — `#[Router]` and `#[Route]` replace `@RestController` and `@GetMapping`
- **Dependency injection** — `#[Wired]` replaces `@Autowired`; the container resolves constructor and property dependencies via Reflection
- **ORM** — `#[Entity]`, `#[Column]`, `#[Id]` replace JPA annotations; `Model` gives you `find()`, `findAll()`, `save()`, `delete()` with no SQL
- **Repository pattern** — extend `Repository`, declare abstract methods like `findByNameAndEmail()`, and the framework generates the implementation at runtime — just like Spring Data JPA
- **Services** — `#[Service]` marks a class as a singleton in the container, matching Spring's `@Service`

Views are plain `.php` files — no compilation, no build step, deploy by copying files. Runs on any shared host with PHP 8.1+ and Apache `mod_rewrite`.

**Requires PHP 8.1+**

---

## Getting Started

### 1. Clone and configure

```bash
cp .env.example .env
```

Edit `.env` with your database settings:

```ini
APP_DEBUG=false

DB_DRIVER=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=pointart
DB_USERNAME=your_user
DB_PASSWORD=your_password
DB_CHARSET=utf8mb4
```

### 2. Point your web server at the project root

The included `.htaccess` rewrites all requests to `index.php`. For Apache, ensure `mod_rewrite` is enabled.

### 3. Clear the route cache after any code changes

PointArt scans `app/` on the first request and serializes the route and service registry to `cache/registry.ser`. Every subsequent request reads from that cache — no scanning, no Reflection.

> **If you add a new controller, route, or service and it doesn't appear — clear the cache.**

```php
ClassLoader::clearCache();
```

Or delete `cache/registry.ser` manually. The cache will be rebuilt on the next request.

---

## Directory Structure

```
/
├── index.php              # Entry point
├── .htaccess              # Rewrites all requests to index.php
├── .env                   # Your local config (gitignored)
├── .env.example           # Config template
├── config.php             # Reads from .env, returns config array
│
├── framework/
│   ├── attributes/        # PHP Attributes (Route, Router, Service, Wired, …)
│   ├── core/              # App, Container, ClassLoader, RouteHandler, Renderer
│   └── ORM/               # Model, Repository
│
└── app/
    ├── components/        # Controllers and Services (auto-scanned)
    ├── models/            # Model subclasses
    ├── repositories/      # Repository subclasses
    └── views/             # Plain .php view files
```

---

## Controllers

Place controllers in `app/components/`. They are auto-scanned on first request.

Mark a class with `#[Router]` and its methods with `#[Route]`.

```php
#[Router(name: 'user', path: '/user')]
class UserController {

    #[Route('/list', HttpMethod::GET)]
    public function index(): string {
        $users = User::findAll();
        return Renderer::render('user.list', ['users' => $users]);
    }

    #[Route('/show/{id}', HttpMethod::GET)]
    public function show(int $id): string {
        $user = User::find($id);
        if ($user === null) {
            return Renderer::render('user.notfound');
        }
        return Renderer::render('user.show', ['user' => $user]);
    }

    #[Route('/create', HttpMethod::POST)]
    public function create(
        #[RequestParam] string $name,
        #[RequestParam] string $email
    ): string {
        $user = new User();
        $user->name  = $name;
        $user->email = $email;
        $user->save();
        return Renderer::render('user.show', ['user' => $user]);
    }
}
```

### `#[Router]`

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `path` | `string` | No | URL prefix applied to every route in the class (e.g. `'/user'`). Default: `''` |
| `name` | `string` | No | Logical name for the controller. Default: `''` |

### `#[Route]`

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `path` | `string` | **Yes** | Route path, relative to the controller prefix. Supports `{param}` placeholders |
| `method` | `HttpMethod` | No | `HttpMethod::GET` or `HttpMethod::POST`. Default: `GET` |

### Method parameters

| Source | How to declare | Example |
|--------|---------------|---------|
| URL path segment | Typed parameter matching `{name}` in the route | `int $id` for `/show/{id}` |
| Query string (`$_GET`) | Typed parameter with a default value | `string $name = ''` for `?name=foo` |
| POST body / file upload | `#[RequestParam]` on the parameter | `#[RequestParam] string $email` |

### Return types

| Return value | Response |
|--------------|----------|
| `string` | Echoed as HTML |
| `array` or `object` | JSON-encoded with `Content-Type: application/json` |

---

## Dependency Injection

Use `#[Wired]` on a property to have the container inject it automatically.

```php
#[Router(name: 'user', path: '/user')]
class UserController {
    #[Wired]
    private UserRepository $userRepository;
    // $userRepository is resolved and injected before any method is called
}
```

Mark a class as a singleton with `#[Service]`:

```php
#[Service('myService')]
class MyService {
    // one instance shared across the request
}
```

---

## Models

Place model classes in `app/models/`.

Extend `Model`, annotate with `#[Entity]`, mark columns with `#[Column]` and the primary key with `#[Id]`.

```php
#[Entity('users')]
class User extends Model {
    #[Id]
    public ?int $id = null;

    #[Column('name', 'varchar')]
    public string $name;

    #[Column('email', 'varchar')]
    public string $email;
}
```

### Static query methods

| Method | SQL |
|--------|-----|
| `User::find($id)` | `SELECT * WHERE pk = ? LIMIT 1` |
| `User::findAll()` | `SELECT *` |
| `User::findBy(['col' => $val], $order, $limit)` | `SELECT * WHERE col = ? [ORDER/LIMIT]` |
| `User::findOne(['col' => $val])` | `SELECT * WHERE col = ? LIMIT 1` |

### Instance methods

```php
$user = new User();
$user->name  = 'Alice';
$user->email = 'alice@example.com';
$user->save();    // INSERT (id is null) or UPDATE

$user->delete();  // DELETE WHERE id = ?
```

---

## Repositories

Place repository classes in `app/repositories/`.

Extend `Repository` and set `$entityClass`. Declare the class `abstract` — a concrete implementation is generated at runtime.

```php
abstract class UserRepository extends Repository {
    protected string $entityClass = User::class;

    // Custom SQL via #[Query]
    #[Query("SELECT * FROM users WHERE name = ? AND email = ?")]
    abstract public function findByNameAndEmailRaw(string $name, string $email): array;

    #[Query("SELECT COUNT(*) FROM users")]
    abstract public function countAll(): int;

    // Dynamic finder — no body needed
    abstract public function findByName(string $name): array;
}
```

### Built-in methods

`find($id)`, `findAll()`, `save($entity)`, `delete($entity)`, `deleteById($id)`

### Dynamic finders

Method names encode the query — no implementation required:

| Method | SQL |
|--------|-----|
| `findByName($n)` | `WHERE name = ?` |
| `findByNameAndEmail($n, $e)` | `WHERE name = ? AND email = ?` |
| `findByAgeGreaterThan($age)` | `WHERE age > ?` |
| `findByNameOrderByEmail($n)` | `WHERE name = ? ORDER BY email` |
| `findOneByEmail($e)` | `WHERE email = ? LIMIT 1` |
| `countByStatus($s)` | `SELECT COUNT(*) WHERE status = ?` |
| `existsByEmail($e)` | returns `bool` |
| `deleteByStatus($s)` | `DELETE WHERE status = ?` |

Supported operators (suffix on each field segment): `GreaterThan`, `LessThan`, `GreaterThanEqual`, `LessThanEqual`, `Not`, `Like`, `IsNull`, `IsNotNull`

---

## Views

Place view files in `app/views/`. They are plain `.php` files — no template engine, no build step.

```php
Renderer::render(string $view, array $data = [])
```

| Parameter | Description |
|-----------|-------------|
| `$view` | View name — maps to `app/views/<name>.php`. Use dot notation for subdirectories (e.g. `'user.list'` → `app/views/user.list.php`) |
| `$data` | Associative array of variables to pass. Each key becomes a local variable inside the view |

Every key in `$data` is extracted into the view scope before the file is rendered:

```php
// Controller
return Renderer::render('user.list', [
    'users'  => $users,   // available as $users in the view
    'title'  => 'All Users', // available as $title in the view
]);
```

```php
<!-- app/views/user.list.php -->
<h1><?= htmlspecialchars($title) ?></h1>
<?php foreach ($users as $user): ?>
    <p><?= htmlspecialchars($user->name) ?> — <?= htmlspecialchars($user->email) ?></p>
<?php endforeach; ?>
```

Rendering a view with no data (e.g. a static error page) — omit the second argument:

```php
return Renderer::render('user.notfound');
```

---

## Error Handling

```php
// In a controller — render a clean error page and stop
httpError(403, 'You do not have permission.');
return '';
```

Convenience wrappers: `return404()`, `return401()`, `return403()`, `return405()`

Unmatched routes return a 404 automatically. Uncaught exceptions return a 500 (or a full stack trace when `APP_DEBUG=true`).

---

## Configuration

| `.env` key | Default | Description |
|------------|---------|-------------|
| `APP_DEBUG` | `false` | Show stack traces on error |
| `DB_DRIVER` | `mysql` | `mysql`, `pgsql`, or `sqlite` |
| `DB_HOST` | `localhost` | Database host |
| `DB_PORT` | `3306` | Database port (`5432` for pgsql) |
| `DB_DATABASE` | `pointart` | Database name |
| `DB_USERNAME` | — | Database user |
| `DB_PASSWORD` | — | Database password |
| `DB_CHARSET` | `utf8mb4` | Charset (MySQL only) |
| `DB_PATH` | — | Path to SQLite file (SQLite only) |

---

## License

PointArt is licensed under the [Mozilla Public License 2.0](https://mozilla.org/MPL/2.0/).

You can use, modify, and distribute this software freely. If you modify any MPL-licensed source files, you must make those modifications available under the MPL 2.0. You are not required to open-source code in separate files that merely use this framework.