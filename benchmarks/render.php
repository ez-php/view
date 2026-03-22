<?php

declare(strict_types=1);

/**
 * Performance benchmark for EzPhp\View\ViewEngine.
 *
 * Measures the overhead of rendering a PHP template with layout inheritance,
 * named sections, partials, and HTML escaping.
 *
 * Exits with code 1 if the per-render time exceeds the defined threshold,
 * allowing CI to detect performance regressions automatically.
 *
 * Usage:
 *   php benchmarks/render.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use EzPhp\View\ViewEngine;

const ITERATIONS = 2000;
const THRESHOLD_MS = 5.0; // per-render upper bound in milliseconds

// ── Prepare a temporary template directory ────────────────────────────────────

$viewDir = sys_get_temp_dir() . '/ez-view-bench-' . getmypid();
@mkdir($viewDir . '/layouts', recursive: true);
@mkdir($viewDir . '/partials', recursive: true);

// Layout template
file_put_contents($viewDir . '/layouts/base.php', <<<'TPL'
    <?php /** @var \EzPhp\View\TemplateContext $this */ ?>
    <!DOCTYPE html>
    <html>
    <head><title><?= $this->e($title ?? 'Page') ?></title></head>
    <body>
    <header><h1><?= $this->e($siteName ?? 'ez-php') ?></h1></header>
    <main><?= $this->yield('content') ?></main>
    <footer><?= $this->yield('footer', '<p>Default footer</p>') ?></footer>
    </body>
    </html>
    TPL);

// Partial template
file_put_contents($viewDir . '/partials/card.php', <<<'TPL'
    <?php /** @var \EzPhp\View\TemplateContext $this */ ?>
    <div class="card">
        <h2><?= $this->e($item['title'] ?? '') ?></h2>
        <p><?= $this->e($item['body'] ?? '') ?></p>
    </div>
    TPL);

// Child page template
file_put_contents($viewDir . '/page.php', <<<'TPL'
    <?php /** @var \EzPhp\View\TemplateContext $this */ ?>
    <?php $this->extends('layouts.base') ?>

    <?php $this->section('content') ?>
    <ul>
    <?php foreach ($items as $item): ?>
        <li><?= $this->partial('partials.card', ['item' => $item]) ?></li>
    <?php endforeach ?>
    </ul>
    <?php $this->endSection() ?>

    <?php $this->section('footer') ?>
    <p>Custom footer for <?= $this->e($title ?? '') ?></p>
    <?php $this->endSection() ?>
    TPL);

// ── Setup engine ──────────────────────────────────────────────────────────────

$engine = new ViewEngine($viewDir);

$data = [
    'title' => 'Benchmark Page',
    'siteName' => 'ez-php Benchmarks',
    'items' => [
        ['title' => 'Item One',   'body' => 'First item description with <special> chars'],
        ['title' => 'Item Two',   'body' => 'Second item description & more content'],
        ['title' => 'Item Three', 'body' => 'Third item with "quoted" text'],
        ['title' => 'Item Four',  'body' => "Fourth item with 'single quotes'"],
        ['title' => 'Item Five',  'body' => 'Fifth item'],
    ],
];

// Warm-up
$engine->render('page', $data);

// ── Benchmark ─────────────────────────────────────────────────────────────────

$start = hrtime(true);

for ($i = 0; $i < ITERATIONS; $i++) {
    $engine->render('page', $data);
}

$end = hrtime(true);

// Cleanup
@unlink($viewDir . '/layouts/base.php');
@rmdir($viewDir . '/layouts');
@unlink($viewDir . '/partials/card.php');
@rmdir($viewDir . '/partials');
@unlink($viewDir . '/page.php');
@rmdir($viewDir);

$totalMs = ($end - $start) / 1_000_000;
$perRender = $totalMs / ITERATIONS;

echo sprintf(
    "ViewEngine Render Benchmark\n" .
    "  Template structure   : layout + page + 5 × partial\n" .
    "  Iterations           : %d\n" .
    "  Total time           : %.2f ms\n" .
    "  Per render           : %.3f ms\n" .
    "  Threshold            : %.1f ms\n",
    ITERATIONS,
    $totalMs,
    $perRender,
    THRESHOLD_MS,
);

if ($perRender > THRESHOLD_MS) {
    echo sprintf(
        "FAIL: %.3f ms exceeds threshold of %.1f ms\n",
        $perRender,
        THRESHOLD_MS,
    );
    exit(1);
}

echo "PASS\n";
exit(0);
