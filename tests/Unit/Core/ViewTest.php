<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\View;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ViewTest extends TestCase
{
    private View $view;

    protected function setUp(): void
    {
        $this->view = new View(dirname(__DIR__, 2) . '/Fixtures/views');
    }

    #[Test]
    public function itRendersATemplateInsideTheLayout(): void
    {
        $html = $this->view->render('greeting', ['name' => 'Thandi', 'title' => 'Hi']);

        self::assertSame("[layout:Hi]Hello, Thandi!\n[/layout]\n", $html);
    }

    #[Test]
    public function itRendersWithoutALayoutWhenAskedTo(): void
    {
        // PHP swallows the single newline straight after a closing PHP tag.
        self::assertSame('item:Box', $this->view->render('nested/item', ['label' => 'Box'], null));
    }

    #[Test]
    public function valuesPrintedWithEAreEscaped(): void
    {
        $html = $this->view->render('greeting', ['name' => '<script>alert(1)</script>'], null);

        self::assertStringNotContainsString('<script>', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unsafeNames(): iterable
    {
        yield 'parent directory' => ['../secrets'];
        yield 'hidden traversal' => ['nested/../../secrets'];
        yield 'absolute path' => ['/etc/passwd'];
        yield 'file extension' => ['greeting.php'];
    }

    #[Test]
    #[DataProvider('unsafeNames')]
    public function itRejectsTemplateNamesThatCouldLeaveTheViewsDirectory(string $name): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->view->render($name);
    }

    #[Test]
    public function aMissingTemplateIsAnError(): void
    {
        $this->expectException(RuntimeException::class);

        $this->view->render('does-not-exist');
    }

    #[Test]
    public function aFailingTemplateLeavesNoHalfRenderedOutput(): void
    {
        $level = ob_get_level();

        try {
            $this->view->render('broken');
            self::fail('Expected the template to throw.');
        } catch (RuntimeException $e) {
            self::assertSame('Template failed part-way', $e->getMessage());
        }

        self::assertSame($level, ob_get_level());
    }
}
