# ez-php/view

PHP template engine for the ez-php framework. Renders `.php` template files with layout inheritance, named sections, reusable partials, and HTML escaping — no external library required.

---

## Installation

```bash
composer require ez-php/view
```

---

## Quick Start

Register the provider in `provider/modules.php`:

```php
use EzPhp\View\ViewServiceProvider;

$app->register(ViewServiceProvider::class);
```

Add config to `config/view.php`:

```php
return [
    'path' => env('VIEW_PATH', base_path('resources/views')),
];
```

Render a template from a controller:

```php
use EzPhp\View\View;

return new Response(View::render('home', ['name' => 'Alice']));
```

---

## Template Files

Templates are plain PHP files stored in `resources/views/` (or the configured path).

```
resources/views/
├── layouts/
│   └── app.php
├── partials/
│   └── nav.php
└── home.php
```

Template names use **dot-notation** as directory separators:
- `'home'`           → `views/home.php`
- `'layouts.app'`   → `views/layouts/app.php`
- `'users.profile'` → `views/users/profile.php`

---

## Template API

Inside every template file, `$this` refers to a `TemplateContext` that exposes:

### `$this->e(string $value): string`

HTML-escape a value. Always use for user-supplied data.

```php
<p>Hello, <?= $this->e($name) ?>!</p>
```

### `$this->extends(string $template): void`

Declare that this template extends a layout.

```php
<?php $this->extends('layouts.app') ?>
```

### `$this->section(string $name): void` / `$this->endSection(): void`

Capture named content for the layout.

```php
<?php $this->section('content') ?>
<h1>Hello!</h1>
<?php $this->endSection() ?>
```

### `$this->yield(string $name, string $default = ''): string`

In the layout, output a named section from the child template.

```php
<main><?= $this->yield('content') ?></main>
<title><?= $this->yield('title', 'My App') ?></title>
```

### `$this->partial(string $template, array $data = []): string`

Render a sub-template and return its output.

```php
<header><?= $this->partial('partials.nav', ['active' => 'home']) ?></header>
```

---

## Layout Example

**`resources/views/layouts/app.php`**
```html
<!DOCTYPE html>
<html>
<head>
    <title><?= $this->yield('title', 'My App') ?></title>
</head>
<body>
    <header><?= $this->partial('partials.nav') ?></header>
    <main><?= $this->yield('content') ?></main>
</body>
</html>
```

**`resources/views/home.php`**
```php
<?php $this->extends('layouts.app') ?>

<?php $this->section('title') ?>Home<?php $this->endSection() ?>

<?php $this->section('content') ?>
<h1>Welcome, <?= $this->e($name) ?>!</h1>
<?php $this->endSection() ?>
```

---

## Static Facade

```php
use EzPhp\View\View;
use EzPhp\View\ViewEngine;

// In tests
View::setEngine(new ViewEngine('/tmp/views'));
View::resetEngine(); // tearDown()

// In application code (after ViewServiceProvider)
View::render('home', ['name' => 'Alice']);
```

---

## Direct Engine Usage

```php
use EzPhp\View\ViewEngine;

$engine = new ViewEngine('/path/to/views');
$html   = $engine->render('home', ['name' => 'Alice']);
```
