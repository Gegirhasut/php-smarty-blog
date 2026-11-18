<?php

declare(strict_types=1);

namespace App\Tests\Unit\Core;

use App\Core\View;
use App\Tests\TempDir;
use PHPUnit\Framework\TestCase;

final class ViewTest extends TestCase
{
    private TempDir $dir;
    private View $view;

    protected function setUp(): void
    {
        $this->dir = new TempDir();
        $this->view = new View(templateDir: $this->dir->path, compileDir: $this->dir->path, debug: true);
    }

    protected function tearDown(): void
    {
        $this->dir->remove();
    }

    public function testEscapesOutputByDefault(): void
    {
        $html = $this->view->render('string:{$value}', ['value' => '<b class="x">\'</b>']);

        self::assertSame('&lt;b class=&quot;x&quot;&gt;&#039;&lt;/b&gt;', $html);
    }

    public function testSharedVariableIsAvailableInEveryTemplate(): void
    {
        $this->view->share('site', ['name' => 'Блог']);

        self::assertSame('Блог', $this->view->render('string:{$site.name}'));
    }

    public function testRenderDataDoesNotLeakIntoNextRender(): void
    {
        $this->view->render('string:{$value}', ['value' => 'first']);

        self::assertSame('none', $this->view->render('string:{$value|default:"none"}'));
    }

    public function testFormatsDateInRussian(): void
    {
        self::assertSame(
            '7 мая 2026, 31 дек 2025',
            $this->view->render('string:{$a|date_ru}, {$b|date_ru}', ['a' => '2026-05-07 09:00:00', 'b' => '2025-12-31 23:59:59']),
        );
    }
}
