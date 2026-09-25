<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\Application\Application;
use EzPhp\Testing\ApplicationTestCase;
use EzPhp\View\View;
use EzPhp\View\ViewEngine;
use EzPhp\View\ViewServiceProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

/**
 * Smoke test: ViewServiceProvider registers and boots its bindings in a minimal
 * application context without error.
 *
 * @package Tests
 */
#[CoversClass(ViewServiceProvider::class)]
#[UsesClass(View::class)]
#[UsesClass(ViewEngine::class)]
final class ViewServiceProviderTest extends ApplicationTestCase
{
    /**
     * @param Application $app
     *
     * @return void
     */
    protected function configureApplication(Application $app): void
    {
        $app->register(ViewServiceProvider::class);
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        View::resetEngine();
        parent::tearDown();
    }

    /**
     * @return void
     * @throws \ReflectionException
     */
    public function test_view_engine_is_bound(): void
    {
        $this->assertInstanceOf(ViewEngine::class, $this->app()->make(ViewEngine::class));
    }

    /**
     * @return void
     * @throws \ReflectionException
     */
    public function test_view_engine_binding_is_a_singleton(): void
    {
        $this->assertSame(
            $this->app()->make(ViewEngine::class),
            $this->app()->make(ViewEngine::class),
        );
    }
}
