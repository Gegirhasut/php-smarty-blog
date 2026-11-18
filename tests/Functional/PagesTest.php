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
        $this->insertPost('first', [$agents, $tools]);

        $page = $this->page($this->site->get('/'));

        self::assertSame(['Инструменты', 'Агенты'], $page->texts('//header//nav//a'));
        self::assertSame('http://blog.test/', $page->attribute('//link[@rel="canonical"]', 'href'));
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
