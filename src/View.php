<?php

declare(strict_types=1);

namespace EzPhp\View;

/**
 * Class View
 *
 * Static facade for the active ViewEngine singleton.
 * The ViewServiceProvider calls setEngine() in boot(), making all static
 * methods available after the application is bootstrapped.
 *
 * Usage:
 *   View::render('home', ['name' => 'Alice']);
 *
 * Testing:
 *   View::setEngine($engine);      // inject a configured engine
 *   // ... exercise code under test ...
 *   View::resetEngine();           // call in tearDown()
 *
 * @package EzPhp\View
 */
final class View
{
    /**
     * @var ViewEngine|null Active engine singleton; null before setEngine() is called.
     */
    private static ?ViewEngine $engine = null;

    /**
     * Replace (or initialise) the active engine.
     *
     * @param ViewEngine $engine
     *
     * @return void
     */
    public static function setEngine(ViewEngine $engine): void
    {
        self::$engine = $engine;
    }

    /**
     * Clear the active engine. Call in test tearDown() to prevent state leaking.
     *
     * @return void
     */
    public static function resetEngine(): void
    {
        self::$engine = null;
    }

    /**
     * Render a template and return the output as a string.
     *
     * @param string               $template Template name in dot-notation.
     * @param array<string, mixed> $data     Variables passed into the template.
     *
     * @throws \RuntimeException When called before setEngine().
     * @throws ViewException     When a template file cannot be found.
     *
     * @return string
     */
    public static function render(string $template, array $data = []): string
    {
        if (self::$engine === null) {
            throw new \RuntimeException(
                'View engine is not initialised. Add ViewServiceProvider to your application.'
            );
        }

        return self::$engine->render($template, $data);
    }
}
