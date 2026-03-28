<?php

declare(strict_types=1);

namespace EzPhp\View;

use EzPhp\Contracts\ConfigInterface;
use EzPhp\Contracts\ContainerInterface;
use EzPhp\Contracts\ServiceProvider;

/**
 * Class ViewServiceProvider
 *
 * Binds ViewEngine to the DI container using the path configured via
 * config/view.php, and wires the static View facade in boot().
 *
 * Config key: view.path
 *   The absolute path to the directory containing .php template files.
 *   Defaults to '{basePath}/resources/views' when not set.
 *
 * @package EzPhp\View
 */
final class ViewServiceProvider extends ServiceProvider
{
    /**
     * Bind ViewEngine lazily so the view path is resolved from config at
     * first resolution rather than at provider load time.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->bind(ViewEngine::class, function (ContainerInterface $app): ViewEngine {
            $config = $app->make(ConfigInterface::class);
            $path = $config->get('view.path');
            $path = is_string($path) && $path !== '' ? $path : 'resources/views';

            return new ViewEngine($path);
        });
    }

    /**
     * Wire the static View facade to the resolved ViewEngine instance.
     *
     * @return void
     */
    public function boot(): void
    {
        View::setEngine($this->app->make(ViewEngine::class));
    }
}
