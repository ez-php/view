# Coding Guidelines

Applies to the entire ez-php project — framework core, all modules, and the application template.

---

## Environment

- PHP **8.5**, Composer for dependency management
- All project based commands run **inside Docker** — never directly on the host

```
docker compose exec app <command>
```

Container name: `ez-php-app`, service name: `app`.

---

## Quality Suite

Run after every change:

```
docker compose exec app composer full
```

Executes in order:
1. `phpstan analyse` — static analysis, level 9, config: `phpstan.neon`
2. `php-cs-fixer fix` — auto-fixes style (`@PSR12` + `@PHP83Migration` + strict rules)
3. `phpunit` — all tests with coverage

Individual commands when needed:
```
composer analyse   # PHPStan only
composer cs        # CS Fixer only
composer test      # PHPUnit only
```

**PHPStan:** never suppress with `@phpstan-ignore-line` — always fix the root cause.

---

## Coding Standards

- `declare(strict_types=1)` at the top of every PHP file
- Typed properties, parameters, and return values — avoid `mixed`
- PHPDoc on every class and public method
- One responsibility per class — keep classes small and focused
- Constructor injection — no service locator pattern
- No global state unless intentional and documented

**Naming:**

| Thing | Convention |
|---|---|
| Classes / Interfaces | `PascalCase` |
| Methods / variables | `camelCase` |
| Constants | `UPPER_CASE` |
| Files | Match class name exactly |

**Principles:** SOLID · KISS · DRY · YAGNI

---

## Workflow & Behavior

- Write tests **before or alongside** production code (test-first)
- Read and understand the relevant code before making any changes
- Modify the minimal number of files necessary
- Keep implementations small — if it feels big, it likely belongs in a separate module
- No hidden magic — everything must be explicit and traceable
- No large abstractions without clear necessity
- No heavy dependencies — check if PHP stdlib suffices first
- Respect module boundaries — don't reach across packages
- Keep the framework core small — what belongs in a module stays there
- Document architectural reasoning for non-obvious design decisions
- Do not change public APIs unless necessary
- Prefer composition over inheritance — no premature abstractions

---

## New Modules & CLAUDE.md Files

### 1 — Required files

Every module under `modules/<name>/` must have:

| File | Purpose |
|---|---|
| `composer.json` | package definition, deps, autoload |
| `phpstan.neon` | static analysis config, level 9 |
| `phpunit.xml` | test suite config |
| `.php-cs-fixer.php` | code style config |
| `.gitignore` | ignore `vendor/`, `.env`, cache |
| `.env.example` | environment variable defaults (copy to `.env` on first run) |
| `docker-compose.yml` | Docker Compose service definition (always `container_name: ez-php-<name>-app`) |
| `docker/app/Dockerfile` | module Docker image (`FROM au9500/php:8.5`) |
| `docker/app/container-start.sh` | container entrypoint: `composer install` → `sleep infinity` |
| `docker/app/php.ini` | PHP ini overrides (`memory_limit`, `display_errors`, `xdebug.mode`) |
| `.github/workflows/ci.yml` | standalone CI pipeline |
| `README.md` | public documentation |
| `tests/TestCase.php` | base test case for the module |
| `start.sh` | convenience script: copy `.env`, bring up Docker, wait for services, exec shell |
| `CLAUDE.md` | see section 2 below |

### 2 — CLAUDE.md structure

Every module `CLAUDE.md` must follow this exact structure:

1. **Full content of `CODING_GUIDELINES.md`, verbatim** — copy it as-is, do not summarize or shorten
2. A `---` separator
3. `# Package: ez-php/<name>` (or `# Directory: <name>` for non-package directories)
4. Module-specific section covering:
   - Source structure — file tree with one-line description per file
   - Key classes and their responsibilities
   - Design decisions and constraints
   - Testing approach and infrastructure requirements (MySQL, Redis, etc.)
   - What does **not** belong in this module

### 3 — Docker scaffold

Run from the new module root (requires `"ez-php/docker": "0.*"` in `require-dev`):

```
vendor/bin/docker-init
```

This copies `Dockerfile`, `docker-compose.yml`, `.env.example`, `start.sh`, and `docker/` into the module, replacing `{{MODULE_NAME}}` placeholders. Existing files are never overwritten.

After scaffolding:

1. Adapt `docker-compose.yml` — add or remove services (MySQL, Redis) as needed
2. Adapt `.env.example` — fill in connection defaults matching the services above
3. Assign a unique host port for each exposed service (see table below)

**Allocated host ports:**

| Package | `DB_HOST_PORT` (MySQL) | `REDIS_PORT` |
|---|---|---|
| root (`ez-php-project`) | 3306 | 6379 |
| `ez-php/framework` | 3307 | — |
| `ez-php/orm` | 3309 | — |
| `ez-php/cache` | — | 6380 |
| **next free** | **3310** | **6381** |

Only set a port for services the module actually uses. Modules without external services need no port config.

### 4 — Monorepo scripts

`packages.sh` at the project root is the **central package registry**. Both `push_all.sh` and `update_all.sh` source it — the package list lives in exactly one place.

When adding a new module, add `"$ROOT/modules/<name>"` to the `PACKAGES` array in `packages.sh` in **alphabetical order** among the other `modules/*` entries (before `framework`, `ez-php`, and the root entry at the end).

---

# Package: ez-php/view

PHP template engine for ez-php applications — layout inheritance, named sections, reusable partials, and HTML escaping. No external library required.

---

## Source Structure

```
src/
├── ViewException.php       — base exception for all view errors (missing templates, bad section calls)
├── TemplateContext.php     — $this inside templates: extends, section, endSection, yield, partial, e
├── ViewEngine.php          — resolves template paths, orchestrates rendering and layout chaining
├── View.php                — static facade: setEngine, resetEngine, render
└── ViewServiceProvider.php — binds ViewEngine (config-driven), wires View facade in boot()

tests/
├── TestCase.php            — base PHPUnit test case
├── TemplateContextTest.php — unit tests for context methods (no files, ob_start/ob_get_clean)
├── ViewEngineTest.php      — integration tests using real .php files in a temp directory
└── ViewTest.php            — facade delegation, uninitialized throw, engine replacement
```

---

## Key Classes and Responsibilities

### TemplateContext (`src/TemplateContext.php`)

The object bound to `$this` in every template file. Exposes the entire template API and is the only way templates interact with the engine.

| Method | Description |
|--------|-------------|
| `extends(string $template)` | Declare a layout; called before any output |
| `section(string $name)` | Start capturing output into a named section |
| `endSection()` | End the current section and store its output |
| `yield(string $name, string $default = '')` | Output a section from the child (used in layouts) |
| `partial(string $template, array $data = [])` | Render a sub-template and return its output |
| `e(string $value)` | HTML-escape a string (ENT_QUOTES \| ENT_SUBSTITUTE, UTF-8) |
| `getLayout()` | Return the layout name or null (used by ViewEngine) |
| `doInclude(string $path, array $data)` | Include a file with `$this` as context; `@internal` |

`doInclude` wraps the `include` in `try/catch (\Throwable)` to guarantee `ob_end_clean()` is always called when an exception propagates out of the template, preventing output buffer leaks.

---

### ViewEngine (`src/ViewEngine.php`)

Resolves dot-notation template names to file paths and orchestrates the two-phase render:

1. Include the child template through the context → child captures sections, records layout.
2. If a layout was declared, include the layout through the **same** context → layout uses `yield()` to output captured sections.

Both phases use `TemplateContext::doInclude()` so `$this` is consistently available.

---

### View (`src/View.php`)

Static facade. Holds a `ViewEngine|null` singleton. Throws `RuntimeException` when called before `setEngine()` — fail-fast makes missing provider registration immediately visible.

---

### ViewServiceProvider (`src/ViewServiceProvider.php`)

**`register()`:** Binds `ViewEngine` lazily; reads `view.path` from `Config` (defaults to `resources/views`).
**`boot()`:** Calls `View::setEngine($app->make(ViewEngine::class))`.

---

## Design Decisions and Constraints

- **`include` inside a method gives `$this` for free** — Because `doInclude()` is a method of `TemplateContext`, any file included from within it has `$this` automatically set to that `TemplateContext` instance. No `Closure::bind()`, no reflection, no magic — it is a standard PHP scoping rule.
- **Dot-notation for template names** — Dots map to directory separators (`layouts.app` → `layouts/app.php`). This convention is consistent across the PHP ecosystem and avoids OS path separator differences in application code.
- **Sections use nested `ob_start()`** — `section()` pushes a new output buffer level; `endSection()` pops it with `ob_get_clean()`. The outer buffer (from `doInclude`) captures everything outside sections, which is discarded when a layout is active.
- **Same context for child and layout** — Both the child template and its layout are rendered through the same `TemplateContext` instance. This is what makes `yield()` in the layout see sections defined in the child.
- **Partials get a fresh context** — `partial()` calls `ViewEngine::render()`, which creates a new `TemplateContext`. This ensures that `extends()` or stray `section()` calls inside a partial do not affect the parent template's state.
- **`ob_end_clean()` in exception path** — When a template throws (e.g., partial not found), the `try/catch` in `doInclude` ends the output buffer before re-throwing. Without this, PHPUnit reports "did not close its own output buffers" and the buffer stack becomes corrupt.
- **No compiled/cached templates** — Compilation caching (like Blade's `.cache` files) would add complexity disproportionate to the benefit for a lightweight module. PHP's opcode cache (OPcache) already caches the compiled bytecode of `.php` files.
- **No global template helpers** — Functions like `e()`, `old()`, `route()` are not injected globally. Template authors use `$this->e()` explicitly. This keeps the scope clean and traceable.
- **`EXTR_SKIP` in `doInclude`** — Prevents user data from overwriting the `$__path` and `$__data` parameters. Variables with double-underscore prefix are documented as reserved.
- **No abstract `extends` keyword** — The layout declaration is `$this->extends('layout')` (a method call), not a PHP `extends` class keyword. This keeps templates as plain PHP files without a custom parser.

---

## Testing Approach

- **No external infrastructure** — All tests run in-process using `sys_get_temp_dir()` for template files.
- **`ViewEngineTest` creates real `.php` files** — Templates are written to a per-test temp directory in `setUp()`, deleted recursively in `tearDown()`. This validates the full stack including file resolution, `include`, and output buffering.
- **`TemplateContextTest` tests context methods directly** — `section()` + `endSection()` are testable in isolation by echoing between them and asserting `yield()` returns the captured output.
- **`ob_end_clean()` cleanup in nested-section test** — `testSectionWhileAlreadyInSectionThrows` uses a manual `try/catch` instead of `expectException()`. This ensures the dangling output buffer from `section('first')` is cleaned up before the test ends — PHPUnit's `expectException()` would stop test execution before the cleanup line is reached.
- **`View::resetEngine()` in setUp/tearDown** — Required to prevent the static singleton from leaking between test classes.
- **`#[UsesClass]` required** — `beStrictAboutCoverageMetadata=true` is set in `phpunit.xml`. Declare all indirectly used classes. Do not add `#[UsesClass(TemplateContext::class)]` in tests that only test `ViewEngine` without explicitly instantiating `TemplateContext` — unless `ViewEngine` transitively uses it (which it does; it is listed).

---

## What Does NOT Belong Here

| Concern | Where it belongs |
|---------|-----------------|
| Template compilation / caching to `.cache` files | Application layer or a future `ez-php/view-cache` package |
| Global helper functions (`e()`, `route()`, `url()`) | Application layer bootstrap |
| Asset versioning / mix manifests | Application layer |
| Template inheritance beyond one level (grandchild → child → layout) | Not planned — YAGNI |
| Twig / Blade / Smarty syntax | Out of scope — this module is plain PHP templates |
| Form helpers, CSRF tokens | Application layer controllers / middleware |
| Mail template rendering | Use `ViewEngine::render()` directly in `ez-php/mail` |
