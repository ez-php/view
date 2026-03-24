<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\View\TemplateContext;
use EzPhp\View\ViewException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

/**
 * Class TemplateContextTest
 *
 * @package Tests
 */
#[CoversClass(TemplateContext::class)]
#[UsesClass(ViewException::class)]
final class TemplateContextTest extends TestCase
{
    private TemplateContext $context;

    protected function setUp(): void
    {
        parent::setUp();
        $this->context = new TemplateContext(fn (string $name, array $data): string => "partial:{$name}");
    }

    public function testExtendsStoresLayout(): void
    {
        $this->context->extends('layouts.app');

        $this->assertSame('layouts.app', $this->context->getLayout());
    }

    public function testGetLayoutIsNullByDefault(): void
    {
        $this->assertNull($this->context->getLayout());
    }

    public function testYieldReturnsDefaultWhenSectionNotDefined(): void
    {
        $this->assertSame('', $this->context->yield('content'));
        $this->assertSame('fallback', $this->context->yield('content', 'fallback'));
    }

    public function testSectionAndEndSectionCaptureOutput(): void
    {
        $this->context->section('title');
        echo 'My Page Title';
        $this->context->endSection();

        $this->assertSame('My Page Title', $this->context->yield('title'));
    }

    public function testMultipleSectionsAreStoredIndependently(): void
    {
        $this->context->section('title');
        echo 'Title Content';
        $this->context->endSection();

        $this->context->section('body');
        echo 'Body Content';
        $this->context->endSection();

        $this->assertSame('Title Content', $this->context->yield('title'));
        $this->assertSame('Body Content', $this->context->yield('body'));
    }

    public function testEndSectionWithoutSectionThrows(): void
    {
        $this->expectException(ViewException::class);
        $this->expectExceptionMessageMatches('/endSection\(\) called without/');

        $this->context->endSection();
    }

    public function testSectionWhileAlreadyInSectionThrows(): void
    {
        $this->context->section('first');

        try {
            $this->context->section('second');
            $this->fail('Expected ViewException was not thrown');
        } catch (ViewException $e) {
            // Clean up the 'first' section's buffer before asserting
            ob_end_clean();
            $this->assertStringContainsString("Cannot start section 'second'", $e->getMessage());
        }
    }

    public function testEEscapesHtmlSpecialChars(): void
    {
        $this->assertSame('&lt;script&gt;alert(&#039;xss&#039;)&lt;/script&gt;', $this->context->e("<script>alert('xss')</script>"));
    }

    public function testEDoesNotModifyPlainText(): void
    {
        $this->assertSame('Hello World', $this->context->e('Hello World'));
    }

    public function testEEscapesDoubleQuotes(): void
    {
        $this->assertSame('say &quot;hello&quot;', $this->context->e('say "hello"'));
    }

    public function testPartialInvokesRenderer(): void
    {
        $this->assertSame('partial:nav', $this->context->partial('nav'));
        $this->assertSame('partial:partials.card', $this->context->partial('partials.card'));
    }

    public function testPartialPassesDataToRenderer(): void
    {
        $captured = [];

        $context = new TemplateContext(
            function (string $name, array $data) use (&$captured): string {
                $captured = $data;

                return '';
            }
        );

        $context->partial('nav', ['active' => 'home']);

        $this->assertSame(['active' => 'home'], $captured);
    }

    public function testDoIncludeRendersFileWithThisContext(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'ez-view-') . '.php';
        file_put_contents($file, '<?php echo $this->e($greeting); ?>');

        try {
            $output = $this->context->doInclude($file, ['greeting' => '<b>Hi</b>']);
            $this->assertSame('&lt;b&gt;Hi&lt;/b&gt;', $output);
        } finally {
            unlink($file);
        }
    }

    // ── HTML escaping edge cases ───────────────────────────────────────────────

    public function testEEscapesAmpersand(): void
    {
        $this->assertSame('AT&amp;T', $this->context->e('AT&T'));
    }

    public function testEReturnsEmptyString(): void
    {
        $this->assertSame('', $this->context->e(''));
    }

    public function testEPreservesUtf8Characters(): void
    {
        $this->assertSame('Üniform café', $this->context->e('Üniform café'));
    }

    public function testEDoubleEncodesAlreadyEscapedEntities(): void
    {
        // e() must not be idempotent — it encodes the raw string as-is
        $this->assertSame('&amp;lt;', $this->context->e('&lt;'));
    }

    public function testEEscapesAllFourSpecialCharsTogether(): void
    {
        $this->assertSame('&lt;&gt;&amp;&quot;', $this->context->e('<>&"'));
    }

    // ── Section edge cases ─────────────────────────────────────────────────────

    public function testSectionCapturingEmptyContent(): void
    {
        $this->context->section('empty');
        $this->context->endSection();

        $this->assertSame('', $this->context->yield('empty'));
    }

    public function testSectionCanBeYieldedMultipleTimes(): void
    {
        $this->context->section('nav');
        echo '<nav/>';
        $this->context->endSection();

        $this->assertSame('<nav/>', $this->context->yield('nav'));
        $this->assertSame('<nav/>', $this->context->yield('nav'), 'yield() must be repeatable');
    }

    public function testYieldReturnsEmptyStringForUnknownSectionWithNoDefault(): void
    {
        $this->assertSame('', $this->context->yield('nonexistent'));
    }

    public function testDoIncludeCleansBufferOnException(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'ez-view-') . '.php';
        file_put_contents($file, '<?php throw new \RuntimeException("boom"); ?>');

        $levelBefore = ob_get_level();

        try {
            $this->context->doInclude($file, []);
            $this->fail('Expected RuntimeException was not thrown');
        } catch (\RuntimeException $e) {
            $this->assertSame('boom', $e->getMessage());
            $this->assertSame($levelBefore, ob_get_level(), 'Output buffer must be cleaned on exception');
        } finally {
            unlink($file);
        }
    }
}
