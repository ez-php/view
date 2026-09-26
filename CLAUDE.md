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
1. `sync_guidelines.php --check` — fails if any `CLAUDE.md` has drifted from this file
2. `check_test_classes.php` — fails on a duplicate test class name (all packages share the `Tests\` namespace, so a collision is a fatal error in the aggregated run, not a test failure)
3. `phpstan analyse` — static analysis, level 9, config: `phpstan.neon`
4. `php-cs-fixer fix` — auto-fixes style (`@PSR12` + `@PHP83Migration` + strict rules)
   *(Note: `@PHP85Migration` does not exist yet in php-cs-fixer; `@PHP83Migration` is the highest available and is used intentionally even though the project targets PHP 8.5)*
5. `phpunit` — all tests with coverage

Individual commands when needed:
```
composer analyse             # PHPStan only
composer cs                  # CS Fixer only
composer test                # PHPUnit only
composer guidelines:check    # CLAUDE.md drift only
composer test-classes:check  # duplicate test class names only
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
- Concrete classes are `final` — extend behavior through composition, not inheritance. Exception-hierarchy base classes (e.g. `EzPhpException`, `HttpException`, `CacheException`) are one carve-out, since they exist specifically to be extended. A documented template-method-style base class (e.g. `Mailable`, meant to be configured via constructor-time subclassing) is the other — the owning module's `CLAUDE.md` must record it under Design Decisions.

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

**Do not edit part 1 by hand.** It is generated from `CODING_GUIDELINES.md` by
`sync_guidelines.php` at the project root:

```
php sync_guidelines.php            # rewrite every out-of-sync CLAUDE.md
php sync_guidelines.php --check    # report drift, exit 1 if any (CI / pre-commit)
```

Edit `CODING_GUIDELINES.md`, then run the script — it replaces everything before the
`# Package:` / `# Directory:` / `# Project:` heading and preserves the hand-written
section below it byte-for-byte. Editing a single copy only creates drift; before this
script existed, all 40 copies had diverged.

### 3 — Scaffolding a new module

`make_module.php` at the project root writes the required-file set and the monorepo
wiring in one step, wrapping `docker-init` for the Docker subset:

```
composer module:make <name> -- --description="..."
php make_module.php <name> --description="..." --services=mysql,redis --extensions=gmp
```

`<name>` is the kebab-case package name; the namespace is derived as
`EzPhp\<PascalCase>` (each `-`-separated word upper-cased) unless `--namespace=`
overrides it. Existing exceptions the guess gets wrong: `bignum` → `BigNum`,
`dataloader` → `DataLoader`, `dotenv` → `Env`, `graphql` → `GraphQL`, `oauth` → `OAuth`,
`opcache` → `OPCache`, `openapi` → `OpenApi`, `swagger-ui` → `SwaggerUI`, `webauthn` → `WebAuthn` and
`websocket` → `WebSocket`; `websocket-client` → `WebsocketClient`, `websocket-tls` → `WebsocketTls`,
`webauthn-metadata` → `WebauthnMetadata` and `metrics-statsd` → `MetricsStatsd` are
intentional lower-case-word namespaces, and `testing-application` shares `EzPhp\Testing\`
with `testing`).

To bring in a module whose code already lives in its own repository instead of
generating a fresh skeleton, pass `--repo=` with a git URL:

```
php make_module.php <name> --repo=<git-url> [--namespace=Foo]
```

This runs `git submodule add <url> modules/<name>` instead of writing package
files, then applies the same monorepo wiring below. It is mutually exclusive
with `--services`/`--extensions` and `--description` — a submodule brings its own Docker
scaffold (if any) and its own `composer.json` description. A minimal `CLAUDE.md`
stub is written only if the submodule doesn't already ship one, so
`composer guidelines:sync` has a `# Package:` heading to anchor part 1 against.

It writes `modules/<name>/` and registers the module in the four places the monorepo
needs it — root `composer.json` (`autoload.psr-4` **and** the shared
`autoload-dev` `Tests\` directory list), `phpstan.neon`, `phpunit.xml` (test suite
**and** coverage source), and `packages.sh` (alphabetical position) — in both
generated and `--repo` mode.

Two things stay manual on purpose:

- **`CLAUDE.md` part 1** — only the `# Package:` section is generated. Run
  `composer guidelines:sync` afterwards; baking a guidelines copy into the generator
  would recreate the drift the sync script exists to prevent.
- **The host-port table below** (`--services` only) — claim the "next free" row by
  editing the table in `CODING_GUIDELINES.md` (never in a `CLAUDE.md` copy) and run
  `composer guidelines:sync` in the same change. Editing it drifts every `CLAUDE.md`
  until the sync runs, which is why the generator only reminds you instead of doing
  it. Skipping the edit leaves "next free" stale, so the next module collides.

### 4 — Docker scaffold

Run from the new module root (requires `"ez-php/docker": "^2.0"` in `require-dev`):

```
vendor/bin/docker-init
```

This copies `Dockerfile`, `docker-compose.yml`, `.env.example`, `start.sh`, and `docker/` into the module, replacing `{{MODULE_NAME}}` placeholders. Existing files are never overwritten.

Pass `--services` to merge MySQL/Redis/Meilisearch service definitions directly into `docker-compose.yml` and uncomment the matching sections in `.env.example`, instead of adapting them by hand afterward:

```
vendor/bin/docker-init --services=mysql
vendor/bin/docker-init --services=redis
vendor/bin/docker-init --services=meilisearch
vendor/bin/docker-init --services=mysql,redis
```

Pass `--extensions` to merge PHP extension install blocks (apt packages plus `docker-php-ext-install`/`pecl` lines) directly into `docker/app/Dockerfile`, instead of hand-editing it afterward — supported extensions: `bcmath`, `gmp`, `gd`, `imagick`:

```
vendor/bin/docker-init --extensions=gmp,bcmath
vendor/bin/docker-init --extensions=gd,imagick
```

When run from a module directory inside this monorepo, any requested extension not already present is also merged into the shared root `docker/app/Dockerfile` — the container `composer full` at the root actually runs against, distinct from the module's own standalone image.

After scaffolding:

1. Adapt `docker-compose.yml` — add or remove services (MySQL, Redis, Meilisearch) as needed
2. Adapt `.env.example` — fill in connection defaults matching the services above
3. Assign a unique host port for each exposed service (see table below)

**Allocated host ports:**

| Package | `DB_HOST_PORT` (MySQL) | Redis host port | `MEILISEARCH_PORT` |
|---|---|---|---|
| root (`ez-php-project`) | 3306 | 6379 (`REDIS_PORT`) | 7700 |
| `ez-php/framework` | 3307 | — | — |
| `ez-php/` (application template) | 3308 | 6383 (`REDIS_PORT`) | — |
| `ez-php/orm` | 3309 | — | — |
| `ez-php/cache` | — | 6380 (`REDIS_HOST_PORT`) | — |
| `ez-php/queue` | 3310 | 6381 (`REDIS_HOST_PORT`) | — |
| `ez-php/rate-limiter` | — | 6382 (`REDIS_HOST_PORT`) | — |
| `ez-php/search` | — | — | 7701 |
| `ez-php/event-store` | 3311 | — | — |
| `ez-php/broadcast` | — | 6384 (`REDIS_HOST_PORT`) | — |
| `ez-php/feature-flags` | — | 6385 (`REDIS_HOST_PORT`) | — |
| `ez-php/scheduler` | — | 6386 (`REDIS_HOST_PORT`) | — |
| `ez-php/session` | — | 6387 (`REDIS_HOST_PORT`) | — |
| **next free** | **3312** | **6388** | **7702** |

Only set a port for services the module actually uses. Modules without external services need no port config.

> The `MEILISEARCH_PORT` column is the **host** port. Inside a Compose network the service is always reachable at `http://meilisearch:7700` regardless of the host mapping — only publish-side ports need to be unique.

> The "Redis host port" column is likewise the **host**-published port. Every module row maps it through a separate `REDIS_HOST_PORT` env var in `docker-compose.yml`, keeping `REDIS_PORT` fixed at `6379` for in-container connections (the app container always reaches Redis at `redis:6379` over the Compose network, regardless of the host mapping) — the root project and the `ez-php/` application template are the two exceptions, since both have no host/container split and use `REDIS_PORT` for both (the template's other in-container Redis settings — `CACHE_REDIS_PORT`, `QUEUE_REDIS_PORT`, `RATE_LIMITER_REDIS_PORT`, `HEALTH_REDIS_PORT` — stay fixed at `6379` regardless, same as every other module).

> This table tracks only MySQL, Redis, and Meilisearch ports — the three services shared across multiple modules where a collision is otherwise easy to introduce. Mailpit is the one other service with published host ports: SMTP `1025` and web UI `8025`. `ez-php/mail` maps them through `MAILPIT_SMTP_HOST_PORT`/`MAILPIT_API_HOST_PORT` in `modules/mail/docker-compose.yml` (mirroring the `*_HOST_PORT` pattern above, documented in `modules/mail/.env.example`); the root project and the `ez-php/` template each run their own Mailpit on the same defaults (`MAIL_PORT`/`MAIL_WEB_PORT`), so **these three stacks cannot run at the same time** without overriding those variables. It isn't a table column because no module beyond those three runs Mailpit — but a new module adding its own single-use service's ports should likewise parameterize them and document the defaults in its own `.env.example` rather than adding a column here. Services reached only over the Compose network publish no host port and need no entry at all: Memcached (`memcached:11211` in the root stack and `ez-php/cache`) and the opt-in Elasticsearch/Typesense backends in `modules/search/docker-compose.ci.yml`.

### 5 — Monorepo scripts

`packages.sh` at the project root is the **central package registry**. Every multi-package script sources it — `update_all.sh`, `fullcheck.sh`, `bump_version.sh` and the `git_*_all.sh` scripts (`git_push_all.sh`, `git_pull_all.sh`, `git_tag_all.sh`, `git_delete_all_tags.sh`) — so the package list lives in exactly one place.

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


`onResolve(?Closure $listener)` registers (or clears, with `null`) an opt-in listener called with the absolute path of every template file the engine resolves — top-level templates, layouts, and partials, in resolution order. It exists for decorators such as `ez-php/view-cache` that need a render's file dependencies; rendering is identical with or without a listener.

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
