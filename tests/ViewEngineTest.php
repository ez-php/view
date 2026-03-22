<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\View\TemplateContext;
use EzPhp\View\ViewEngine;
use EzPhp\View\ViewException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

/**
 * Class ViewEngineTest
 *
 * Integration tests that create real PHP template files in a temporary
 * directory. No external infrastructure is required.
 *
 * @package Tests
 */
#[CoversClass(ViewEngine::class)]
#[UsesClass(TemplateContext::class)]
#[UsesClass(ViewException::class)]
final class ViewEngineTest extends TestCase
{
    private string $tmpDir = '';

    private ViewEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpDir = sys_get_temp_dir() . '/ez-php-view-test-' . uniqid('', true);
        mkdir($this->tmpDir, 0o755, true);
        $this->engine = new ViewEngine($this->tmpDir);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
        parent::tearDown();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function write(string $name, string $content): void
    {
        $parts = explode('.', $name);
        $file = array_pop($parts);

        if ($parts !== []) {
            $dir = $this->tmpDir . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $parts);
            if (!is_dir($dir)) {
                mkdir($dir, 0o755, true);
            }
            file_put_contents($dir . DIRECTORY_SEPARATOR . $file . '.php', $content);
        } else {
            file_put_contents($this->tmpDir . DIRECTORY_SEPARATOR . $file . '.php', $content);
        }
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $dir . DIRECTORY_SEPARATOR . $entry;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }

        rmdir($dir);
    }

    // ── Tests ─────────────────────────────────────────────────────────────────

    public function testRendersSimpleTemplate(): void
    {
        $this->write('hello', '<p>Hello World</p>');

        $this->assertSame('<p>Hello World</p>', $this->engine->render('hello'));
    }

    public function testInjectsDataVariables(): void
    {
        $this->write('greeting', '<p>Hello <?= $name ?>!</p>');

        $this->assertSame('<p>Hello Alice!</p>', $this->engine->render('greeting', ['name' => 'Alice']));
    }

    public function testEscapesViaContextHelper(): void
    {
        $this->write('safe', '<p><?= $this->e($value) ?></p>');

        $output = $this->engine->render('safe', ['value' => '<script>xss</script>']);

        $this->assertSame('<p>&lt;script&gt;xss&lt;/script&gt;</p>', $output);
    }

    public function testRendersTemplateInSubdirectory(): void
    {
        $this->write('users.profile', '<p><?= $username ?></p>');

        $this->assertSame('<p>bob</p>', $this->engine->render('users.profile', ['username' => 'bob']));
    }

    public function testLayoutSystem(): void
    {
        $this->write('layouts.app', '<html><body><?= $this->yield("content") ?></body></html>');
        $this->write('page', '<?php $this->extends("layouts.app") ?><?php $this->section("content") ?><h1>Hi</h1><?php $this->endSection() ?>');

        $output = $this->engine->render('page');

        $this->assertSame('<html><body><h1>Hi</h1></body></html>', $output);
    }

    public function testLayoutWithMultipleSections(): void
    {
        $this->write(
            'layouts.full',
            '<title><?= $this->yield("title", "Default") ?></title><body><?= $this->yield("content") ?></body>'
        );
        $this->write(
            'full-page',
            '<?php $this->extends("layouts.full") ?>' .
            '<?php $this->section("title") ?>My Title<?php $this->endSection() ?>' .
            '<?php $this->section("content") ?><p>Body</p><?php $this->endSection() ?>'
        );

        $output = $this->engine->render('full-page');

        $this->assertSame('<title>My Title</title><body><p>Body</p></body>', $output);
    }

    public function testLayoutYieldDefaultWhenSectionMissing(): void
    {
        $this->write('layouts.simple', '<main><?= $this->yield("body", "empty") ?></main>');
        $this->write('no-section', '<?php $this->extends("layouts.simple") ?>');

        $output = $this->engine->render('no-section');

        $this->assertSame('<main>empty</main>', $output);
    }

    public function testPartialRendering(): void
    {
        $this->write('partials.nav', '<nav><?= $title ?></nav>');
        $this->write('with-partial', '<header><?= $this->partial("partials.nav", ["title" => "Menu"]) ?></header>');

        $output = $this->engine->render('with-partial');

        $this->assertSame('<header><nav>Menu</nav></header>', $output);
    }

    public function testPartialReceivesParentData(): void
    {
        $this->write('partials.item', '<li><?= $label ?></li>');
        $this->write('list', '<ul><?= $this->partial("partials.item", ["label" => $item]) ?></ul>');

        $output = $this->engine->render('list', ['item' => 'Widget']);

        $this->assertSame('<ul><li>Widget</li></ul>', $output);
    }

    public function testThrowsForMissingTemplate(): void
    {
        $this->expectException(ViewException::class);
        $this->expectExceptionMessageMatches('/Template not found/');

        $this->engine->render('does-not-exist');
    }

    public function testThrowsForMissingLayout(): void
    {
        $this->write('child', '<?php $this->extends("missing-layout") ?>');

        $this->expectException(ViewException::class);
        $this->expectExceptionMessageMatches('/Template not found/');

        $this->engine->render('child');
    }

    public function testThrowsForMissingPartial(): void
    {
        $this->write('uses-partial', '<?= $this->partial("nonexistent") ?>');

        $this->expectException(ViewException::class);
        $this->expectExceptionMessageMatches('/Template not found/');

        $this->engine->render('uses-partial');
    }

    public function testDataIsNotLeakedAcrossRenders(): void
    {
        $this->write('leak-check', '<?= isset($secret) ? "leaked" : "safe" ?>');

        $this->engine->render('leak-check', ['secret' => 'hidden']);
        $output = $this->engine->render('leak-check');

        $this->assertSame('safe', $output);
    }

    public function testLayoutPassesDataToLayout(): void
    {
        $this->write('layouts.data', '<html><?= $title ?></html>');
        $this->write('data-child', '<?php $this->extends("layouts.data") ?>');

        $output = $this->engine->render('data-child', ['title' => 'Passed Through']);

        $this->assertSame('<html>Passed Through</html>', $output);
    }
}
