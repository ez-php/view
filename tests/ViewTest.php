<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\View\TemplateContext;
use EzPhp\View\View;
use EzPhp\View\ViewEngine;
use EzPhp\View\ViewException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

/**
 * Class ViewTest
 *
 * @package Tests
 */
#[CoversClass(View::class)]
#[UsesClass(ViewEngine::class)]
#[UsesClass(TemplateContext::class)]
#[UsesClass(ViewException::class)]
final class ViewTest extends TestCase
{
    private string $tmpDir = '';

    protected function setUp(): void
    {
        parent::setUp();
        View::resetEngine();
        $this->tmpDir = sys_get_temp_dir() . '/ez-php-view-facade-' . uniqid('', true);
        mkdir($this->tmpDir, 0o755, true);
    }

    protected function tearDown(): void
    {
        View::resetEngine();

        if (is_dir($this->tmpDir)) {
            array_map('unlink', glob($this->tmpDir . '/*.php') ?: []);
            rmdir($this->tmpDir);
        }

        parent::tearDown();
    }

    public function testRenderDelegatesToEngine(): void
    {
        file_put_contents($this->tmpDir . '/greet.php', 'Hi <?= $name ?>');
        View::setEngine(new ViewEngine($this->tmpDir));

        $this->assertSame('Hi Bob', View::render('greet', ['name' => 'Bob']));
    }

    public function testRenderThrowsWhenEngineNotSet(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/not initialised/');

        View::render('anything');
    }

    public function testResetEngineClearsInstance(): void
    {
        View::setEngine(new ViewEngine($this->tmpDir));
        View::resetEngine();

        $this->expectException(\RuntimeException::class);
        View::render('anything');
    }

    public function testSetEngineCanReplaceExistingEngine(): void
    {
        $dir1 = sys_get_temp_dir() . '/ez-php-view-e1-' . uniqid('', true);
        $dir2 = sys_get_temp_dir() . '/ez-php-view-e2-' . uniqid('', true);

        mkdir($dir1, 0o755, true);
        mkdir($dir2, 0o755, true);

        file_put_contents($dir1 . '/t.php', 'from-engine-1');
        file_put_contents($dir2 . '/t.php', 'from-engine-2');

        View::setEngine(new ViewEngine($dir1));
        View::setEngine(new ViewEngine($dir2));

        $output = View::render('t');

        // Cleanup
        unlink($dir1 . '/t.php');
        unlink($dir2 . '/t.php');
        rmdir($dir1);
        rmdir($dir2);

        $this->assertSame('from-engine-2', $output);
    }
}
