# Contributing to PointArt

Thanks for taking the time to contribute.

---

## Getting Started

1. Fork the repository and clone your fork
2. Copy `.env.example` to `.env` and configure your database
3. Make your changes in a new branch: `git checkout -b my-feature`
4. Test your changes manually and run the test suite
5. Open a pull request against `master`

---

## Project Structure

All framework code lives in `framework/`. Application code (controllers, models, views) lives in `app/`. Do not mix the two — the framework must have no dependency on anything in `app/`.

---

## Code Style

- PHP 8.1+, no Composer dependencies
- Follow the existing attribute-based patterns (`#[Route]`, `#[Service]`, etc.)
- No external libraries — if something can be done with PHP's standard library or Reflection API, do it that way
- Keep framework classes focused: one responsibility per class
- Views are plain `.php` files — no template engine syntax
- **Classes must not be loaded before they are required by a request.** The ClassLoader registers an autoloader and builds a route registry, but never `require`s class files itself. A class file is only loaded when its class is first instantiated at dispatch time. Do not introduce eager loading — any change to the scan or dispatch path must preserve this behaviour.

---

## Running Tests

Tests live in `tests/` and use a minimal custom test runner (`tests/TestSuite.php`).

```bash
php tests/TestSuite.php
```

Add tests for any new framework behaviour. Tests should not depend on a live database — use `Model::$pdo` to inject an in-memory SQLite PDO instance:

```php
Model::$pdo = new PDO('sqlite::memory:');
```

---

## What to Contribute

These are the known open items. Open an issue before starting on a large one so we can align on the approach.

### Middleware system
Implement a `#[Middleware]` attribute that can be applied to a controller or individual route method. Middleware should support before/after hooks — before runs before the controller method (can short-circuit with a response), after runs after.

### RestController
Allow a controller class to declare a default `Content-Type`. Currently the framework infers JSON vs HTML from the return type. A `#[RestController]` annotation (or a parameter on `#[Router]`) should set the default to `application/json` for all routes in that class.

### Singleton vs transient services
The Container currently treats all `#[Service]`-annotated classes as singletons. Introduce a way to declare a service as transient (a new instance per injection) — either via a parameter on `#[Service]` or a separate `#[Transient]` attribute.

### Query parameter passthrough
`$_GET` parameters are not consistently injected into controller method parameters. It only reads path parameters or request bodyl on #[RequestParam]

### Model Database Migration
A module or external php file can be used for migrating the model changes to database.

### Optional Composer Support
Totally optional composer support for people who want to include external packages.

### OpenAPI / Swagger
Automatically generate an OpenAPI 3.0 spec from the existing attribute metadata — `#[Router]`, `#[Route]`, `#[RequestParam]`, method parameter types, and `#[Entity]`/`#[Column]` for schema definitions. Expose it as `GET /pointart/openapi.json` and serve a Swagger UI page at `GET /pointart/docs` (self-contained HTML, same pattern as the updater). A `#[Returns(SomeClass::class)]` attribute or similar mechanism will be needed to describe response schemas since PHP return types alone are insufficient.

### App Deployer
Extend the updater concept to application code. Allow users to connect their own GitHub repository and deploy `app/` from a release or branch — useful on shared hosting where `git pull` is not available. Should support the same secret-based auth, backup-before-overwrite, and protected paths model as the framework updater.

---


## Reporting Bugs

Open an issue and include:

- PHP version (`php -v`)
- A minimal reproduction (controller + route + what you expected vs what happened)
- Stack trace if applicable (`APP_DEBUG=true` in `.env`)

---

## License

By contributing, you agree that your contributions will be licensed under the [Mozilla Public License 2.0](https://mozilla.org/MPL/2.0/).
