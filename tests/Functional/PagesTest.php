<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Core\Response;
use App\Tests\DatabaseTestCase;
use App\Tests\Html;
use PDO;

/**
 * Страницы целиком на тестовой базе.
 */
final class PagesTest extends DatabaseTestCase
{
    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();
        $this->site = new Site(fn (): PDO => $this->pdo);
    }

    protected function tearDown(): void
    {
        $this->site->remove();
        parent::tearDown();
    }

    public function testHomePage(): void
    {
        $tools = $this->insertCategory('tools', 'Инструменты');
        $this->insertCategory('empty', 'Пустая');
        $agents = $this->insertCategory('agents', 'Агенты');
        $this->insertPost('both', [$agents, $tools], '2026-10-01 10:00:00');
        foreach (['a', 'b', 'c'] as $day => $slug) {
            $this->insertPost($slug, [$tools], '2026-10-0' . ($day + 2) . ' 12:00:00');
        }

        $page = $this->page($this->site->get('/'));

        self::assertSame(['Инструменты', 'Агенты'], $page->texts('//header//nav//a'));
        self::assertSame(['Инструменты', 'Агенты'], $page->texts('//main//h2'));
        self::assertSame(
            ['/category/tools', '/category/agents'],
            $page->attributes('//main//a[text()="Все статьи"]', 'href'),
        );
        self::assertSame(
            ['/post/c', '/post/b', '/post/a', '/post/both'],
            $page->attributes('//main//article//h3/a', 'href'),
        );
        // Сразу грузится только первая картинка, остальные - по мере прокрутки
        self::assertSame(['high', '', '', ''], $page->attributes('//main//img', 'fetchpriority'));
        self::assertSame(['', 'lazy', 'lazy', 'lazy'], $page->attributes('//main//img', 'loading'));
        self::assertSame('http://blog.test/', $page->attribute('//link[@rel="canonical"]', 'href'));
        self::assertArrayHasKey('WebSite', $page->schema());
    }

    public function testHomePageWithoutPosts(): void
    {
        $this->insertCategory('empty');

        $page = $this->page($this->site->get('/'));

        self::assertSame(0, $page->count('//header//nav'));
        self::assertSame(['Статей пока нет.'], $page->texts('//main//p[@class="empty"]'));
    }

    public function testHeadIsHandledLikeGet(): void
    {
        self::assertSame(200, $this->site->request('HEAD', '/')->status);
    }

    private function page(Response $response): Html
    {
        self::assertSame('', $this->site->errorLog());
        self::assertSame(200, $response->status);

        return new Html($response->body);
    }
}
