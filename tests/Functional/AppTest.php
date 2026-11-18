<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Core\App;
use App\Core\Config;
use App\Core\Request;
use App\Core\Response;
use App\Tests\TempDir;
use PHPUnit\Framework\TestCase;

final class AppTest extends TestCase
{
    private TempDir $root;
    private string $previousErrorLog;

    protected function setUp(): void
    {
        // Отдельный корень, чтобы тесты не делили скомпилированные шаблоны с запущенным сайтом
        $this->root = new TempDir();
        $this->root->copy(dirname(__DIR__, 2) . '/templates', 'templates');
        $this->previousErrorLog = (string) ini_set('error_log', $this->root->path . '/error.log');
    }

    protected function tearDown(): void
    {
        ini_set('error_log', $this->previousErrorLog);
        $this->root->remove();
    }

    public function testHomePage(): void
    {
        $response = $this->handle('GET', '/');

        self::assertSame(200, $response->status);
        self::assertStringContainsString('<h1>Блог</h1>', $response->body);
        self::assertStringContainsString('rel="canonical"', $response->body);
    }

    public function testHeadIsHandledLikeGet(): void
    {
        self::assertSame(200, $this->handle('HEAD', '/')->status);
    }

    public function testUnknownPageGets404WithoutCanonical(): void
    {
        $response = $this->handle('GET', '/missing');

        self::assertSame(404, $response->status);
        self::assertStringContainsString('<h1>Страница не найдена</h1>', $response->body);
        self::assertStringNotContainsString('rel="canonical"', $response->body);
    }

    public function testOtherMethodsGet405(): void
    {
        $response = $this->handle('POST', '/');

        self::assertSame(405, $response->status);
        self::assertSame('GET, HEAD', $response->headers['Allow']);
        self::assertSame('', $response->body);
    }

    public function testBrokenNotFoundPageBecomesServerError(): void
    {
        unlink($this->root->path . '/templates/errors/404.tpl');

        $response = $this->handle('GET', '/missing');

        self::assertSame(500, $response->status);
        self::assertStringContainsString('<h1>Что-то пошло не так</h1>', $response->body);
        self::assertStringContainsString('404.tpl', $this->loggedErrors());
    }

    public function testServerErrorHidesDetailsInProduction(): void
    {
        unlink($this->root->path . '/templates/home.tpl');

        $response = $this->handle('GET', '/');

        self::assertSame(500, $response->status);
        self::assertStringContainsString('<h1>Что-то пошло не так</h1>', $response->body);
        self::assertStringNotContainsString('home.tpl', $response->body);
        self::assertStringContainsString('home.tpl', $this->loggedErrors());
    }

    public function testServerErrorShowsDetailsInDevelopment(): void
    {
        unlink($this->root->path . '/templates/home.tpl');

        $response = $this->handle('GET', '/', debug: true);

        self::assertSame(500, $response->status);
        self::assertStringContainsString('home.tpl', $response->body);
    }

    private function handle(string $method, string $path, bool $debug = false): Response
    {
        $config = new Config(siteName: 'Блог', siteUrl: 'http://blog.test', debug: $debug);

        return (new App($config, $this->root->path))->handle(new Request($method, $path));
    }

    private function loggedErrors(): string
    {
        return (string) file_get_contents($this->root->path . '/error.log');
    }
}
