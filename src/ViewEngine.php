<?php

declare(strict_types=1);

namespace EzPhp\View;

/**
 * Class ViewEngine
 *
 * Resolves template names to file paths and orchestrates rendering.
 *
 * Template names use dot-notation as a directory separator:
 *   'home'           → {viewPath}/home.php
 *   'layouts.app'   → {viewPath}/layouts/app.php
 *   'users.profile' → {viewPath}/users/profile.php
 *
 * Rendering flow:
 *  1. Create a fresh TemplateContext for the request.
 *  2. Include the child template through the context (so $this is available).
 *  3. If the template called $this->extends(), render the layout with the
 *     same context — sections captured in step 2 are available via yield().
 *  4. Return the final output string.
 *
 * @package EzPhp\View
 */
final class ViewEngine
{
    /**
     * @var (\Closure(string): void)|null Notified with the absolute path of every template file this
     *      engine resolves (top-level templates, layouts, and partials), in resolution order.
     */
    private ?\Closure $resolveListener = null;

    /**
     * @param string $viewPath Absolute path to the directory containing template files.
     */
    public function __construct(private readonly string $viewPath)
    {
    }

    /**
     * Register (or clear, with null) a listener notified with the absolute path of every template
     * file resolved during rendering — top-level templates, layouts, and partials alike.
     *
     * Opt-in observation hook for decorators such as `ez-php/view-cache` that need to know which
     * files a render depended on. Rendering behaviour is unchanged whether or not a listener is set.
     *
     * @param (\Closure(string): void)|null $listener
     *
     * @return void
     */
    public function onResolve(?\Closure $listener): void
    {
        $this->resolveListener = $listener;
    }

    /**
     * Render a template and return the output as a string.
     *
     * @param string               $template Template name in dot-notation.
     * @param array<string, mixed> $data     Variables made available inside the template as `$name`.
     *
     * @throws ViewException When a template file cannot be found.
     *
     * @return string
     */
    public function render(string $template, array $data = []): string
    {
        $context = $this->createContext();
        $path = $this->resolvePath($template);
        $output = $context->doInclude($path, $data);

        $layout = $context->getLayout();

        if ($layout !== null) {
            $layoutPath = $this->resolvePath($layout);
            $output = $context->doInclude($layoutPath, $data);
        }

        return $output;
    }

    /**
     * Build a TemplateContext with a partial renderer wired back to this engine.
     *
     * @return TemplateContext
     */
    private function createContext(): TemplateContext
    {
        return new TemplateContext(
            function (string $name, array $data): string {
                return $this->render($name, $data);
            }
        );
    }

    /**
     * Convert a dot-notation template name to an absolute file path and assert
     * that the file exists.
     *
     * @param string $template Template name in dot-notation.
     *
     * @throws ViewException When the resolved file does not exist.
     *
     * @return string Absolute path to the template file.
     */
    private function resolvePath(string $template): string
    {
        $relative = str_replace('.', DIRECTORY_SEPARATOR, $template);
        $path = rtrim($this->viewPath, '/\\') . DIRECTORY_SEPARATOR . $relative . '.php';

        if (!is_file($path)) {
            throw new ViewException("Template not found: {$path}");
        }

        if ($this->resolveListener !== null) {
            ($this->resolveListener)($path);
        }

        return $path;
    }
}
