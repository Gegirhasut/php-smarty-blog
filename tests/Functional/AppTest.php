<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Tests\TempDir;
use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Ответы, которым база не нужна: соединение с ней не должно даже открываться.
 */
final class AppTest extends TestCase
{
    private TempDir $root;
    private Site $site;

    protected function setUp(): void
    {
        $this->root = Site::root();
        $this->site = new Site($this->root, fn (): PDO => self::fail('Соединение с базой открывать не нужно'));
    }

    protected function tearDown(): void
    {
        $this->site->close();
        $this->root->remove();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unknownPaths(): array
    {
        return [
            'страница' => ['/missing'],
            'статика' => ['/uploads/missing.webp'],
            'точка входа' => ['/index.php'],
        ];
    }

    #[DataProvider('unknownPaths')]
    public function testUnknownPageGets404WithoutCanonical(string $path): void
    {
        $response = $this->site->get($path);

        self::assertSame(404, $response->status);
        self::assertStringContainsString('<h1>Страница не найдена</h1>', $response->body);
        self::assertStringNotContainsString('rel="canonical"', $response->body);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function routes(): array
    {
        return [
            'главная' => ['/'],
            'категория' => ['/category/tools'],
            'статья' => ['/post/first'],
            'sitemap' => ['/sitemap.xml'],
            'rss' => ['/feed.xml'],
            'robots' => ['/robots.txt'],
            'неизвестная страница' => ['/missing'],
        ];
    }

    #[DataProvider('routes')]
    public function testOtherMethodsGet405(string $path): void
    {
        $response = $this->site->request('POST', $path);

        self::assertSame(405, $response->status);
        self::assertSame('GET, HEAD', $response->headers['Allow']);
        self::assertSame('', $response->body);
    }

    public function testRobotsPointsToSitemap(): void
    {
        $response = $this->site->get('/robots.txt');

        self::assertSame(200, $response->status);
        self::assertSame('text/plain; charset=utf-8', $response->headers['Content-Type']);
        self::assertSame("User-agent: *\nDisallow:\n\nSitemap: http://blog.test/sitemap.xml\n", $response->body);
    }

    public function testBrokenNotFoundPageBecomesServerError(): void
    {
        unlink($this->root->path . '/templates/errors/404.tpl');

        $response = $this->site->get('/missing');

        self::assertSame(500, $response->status);
        self::assertStringContainsString('<h1>Что-то пошло не так</h1>', $response->body);
        self::assertStringContainsString('404.tpl', $this->site->errorLog());
    }

    public function testServerErrorHidesDetailsInProduction(): void
    {
        $site = new Site($this->root, fn (): PDO => throw new PDOException('Connection refused'));

        $response = $site->get('/');
        $site->close();

        self::assertSame(500, $response->status);
        self::assertStringContainsString('<h1>Что-то пошло не так</h1>', $response->body);
        self::assertStringNotContainsString('Connection refused', $response->body);
        self::assertStringContainsString('Connection refused', $site->errorLog());
    }

    public function testServerErrorShowsDetailsInDevelopment(): void
    {
        $site = new Site($this->root, fn (): PDO => throw new PDOException('Connection refused'), debug: true);

        $response = $site->get('/');
        $site->close();

        self::assertSame(500, $response->status);
        self::assertStringContainsString('Connection refused', $response->body);
    }
}
