<?php

declare(strict_types=1);

namespace EzPhp\View;

/**
 * Class TemplateContext
 *
 * The object bound to `$this` inside every PHP template file. Provides the
 * template API: layout inheritance, named sections, partials, and HTML escaping.
 *
 * Template authors interact exclusively through these public methods — the
 * internal state management and file inclusion are opaque from the template's
 * perspective.
 *
 * Usage inside a template file:
 *   <?php $this->extends('layouts.app') ?>
 *   <?php $this->section('content') ?>
 *     <h1>Hello <?= $this->e($name) ?>!</h1>
 *   <?php $this->endSection() ?>
 *
 * @package EzPhp\View
 */
final class TemplateContext
{
    /**
     * @var \Closure(string, array<string, mixed>): string Partial renderer injected by ViewEngine.
     */
    private readonly \Closure $partialRenderer;

    /**
     * @var string|null Layout template name; set by extends(), null when no layout is used.
     */
    private ?string $layout = null;

    /**
     * @var array<string, string> Captured section bodies keyed by section name.
     */
    private array $sections = [];

    /**
     * @var string Name of the section currently being captured; empty string when not in a section.
     */
    private string $currentSection = '';

    /**
     * @var bool Whether a section capture is active.
     */
    private bool $inSection = false;

    /**
     * @param \Closure(string, array<string, mixed>): string $partialRenderer
     *   Closure provided by ViewEngine to render partial templates by name.
     */
    public function __construct(\Closure $partialRenderer)
    {
        $this->partialRenderer = $partialRenderer;
    }

    // ── Template API ──────────────────────────────────────────────────────────

    /**
     * Declare that this template extends a layout.
     * Must be called before any output or section declarations.
     *
     * @param string $template Layout template name in dot-notation (e.g. 'layouts.app').
     *
     * @return void
     */
    public function extends(string $template): void
    {
        $this->layout = $template;
    }

    /**
     * Begin capturing a named section.
     * Everything output until the matching endSection() is stored under $name.
     *
     * @param string $name Section identifier used by yield() in the layout.
     *
     * @throws ViewException When called while another section is already open.
     *
     * @return void
     */
    public function section(string $name): void
    {
        if ($this->inSection) {
            throw new ViewException(
                "Cannot start section '{$name}': section '{$this->currentSection}' is already open."
            );
        }

        $this->currentSection = $name;
        $this->inSection = true;
        ob_start();
    }

    /**
     * End the current section and store its captured output.
     *
     * @throws ViewException When called without a prior section().
     *
     * @return void
     */
    public function endSection(): void
    {
        if (!$this->inSection) {
            throw new ViewException('endSection() called without a matching section().');
        }

        $content = ob_get_clean();

        if ($content === false) {
            throw new ViewException(
                "Failed to capture section '{$this->currentSection}': output buffer unavailable."
            );
        }

        $this->sections[$this->currentSection] = $content;
        $this->inSection = false;
        $this->currentSection = '';
    }

    /**
     * Output a named section captured by the child template.
     * Returns $default when the section was never defined.
     *
     * @param string $name    Section identifier.
     * @param string $default Fallback content when the section is absent.
     *
     * @return string
     */
    public function yield(string $name, string $default = ''): string
    {
        return $this->sections[$name] ?? $default;
    }

    /**
     * Render a partial template and return its output as a string.
     *
     * @param string               $template Template name in dot-notation.
     * @param array<string, mixed> $data     Variables passed into the partial.
     *
     * @throws ViewException When the partial template file cannot be found.
     *
     * @return string
     */
    public function partial(string $template, array $data = []): string
    {
        return ($this->partialRenderer)($template, $data);
    }

    /**
     * HTML-escape a string for safe output inside HTML attributes and text nodes.
     *
     * @param string $value Raw value.
     *
     * @return string
     */
    public function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    // ── Internal API (used by ViewEngine) ─────────────────────────────────────

    /**
     * Return the layout template name, or null when no layout was declared.
     *
     * @return string|null
     */
    public function getLayout(): ?string
    {
        return $this->layout;
    }

    /**
     * Include a template file in this context so that `$this` inside the file
     * refers to this TemplateContext instance.
     *
     * Variable names are prefixed with `__` to avoid collisions with $data keys.
     * EXTR_SKIP ensures existing variables (including $__path and $__data) are
     * never overwritten by user-supplied data.
     *
     * @param string               $__path Template file path on disk.
     * @param array<string, mixed> $__data Variables extracted into the template scope.
     *
     * @return string Captured output of the template file.
     *
     * @internal Called exclusively by ViewEngine.
     */
    public function doInclude(string $__path, array $__data): string
    {
        extract($__data, EXTR_SKIP);
        ob_start();

        try {
            include $__path;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }

        $result = ob_get_clean();

        return $result !== false ? $result : '';
    }
}
