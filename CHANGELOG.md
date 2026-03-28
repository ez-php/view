# Changelog

All notable changes to `ez-php/view` are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

---

## [v1.2.0] — 2026-03-28

### Changed
- Updated `ez-php/contracts` dependency constraint to `^1.2`

---

## [v1.0.1] — 2026-03-25

### Changed
- Tightened all `ez-php/*` dependency constraints from `"*"` to `"^1.0"` for predictable resolution

---

## [v1.0.0] — 2026-03-24

### Added
- `ViewEngine` — PHP template renderer; locates `.php` templates relative to a configurable views directory and renders them in an isolated scope
- `View` — value object pairing a template name with its data array; passed to `ViewEngine::render()`
- `TemplateContext` — execution context injected into each template; exposes `section()`, `yield()`, `extend()`, `partial()`, and `e()` (HTML escape)
- Layout system — templates call `extend('layout')` to wrap their output in a named layout file; sections defined with `section()`/`end()` and rendered with `yield()`
- Partials — `partial('name', $data)` includes a sub-template with an optional scoped data array
- HTML escaping — `e($value)` runs `htmlspecialchars()` with `ENT_QUOTES | ENT_SUBSTITUTE` to prevent XSS
- `ViewServiceProvider` — binds the engine, sets the views path from config, and registers a `view()` helper in the container
- `ViewException` for missing template files and render errors
