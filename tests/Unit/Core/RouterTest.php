<?php

declare(strict_types=1);

namespace App\Tests\Unit\Core;

use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    public function testMatchesRootPath(): void
    {
        $router = new Router();
        $router->add('/', fn (): Response => new Response('home'));

        self::assertSame('home', $router->dispatch(new Request('GET', '/'))->body);
    }

    public function testPassesRequestAndParametersToHandler(): void
    {
        $request = new Request('GET', '/post/hello-world');
        $router = new Router();
        $router->add('/post/{slug}', function (Request $received, array $params) use ($request): Response {
            self::assertSame($request, $received);
            self::assertSame(['slug' => 'hello-world'], $params);

            return new Response('post');
        });

        self::assertSame('post', $router->dispatch($request)->body);
    }

    public function testExtractsSeveralParameters(): void
    {
        $router = new Router();
        $router->add('/a/{first}/b/{second}', fn (Request $request, array $params): Response => new Response(
            implode(',', $params),
        ));

        self::assertSame('1,2', $router->dispatch(new Request('GET', '/a/1/b/2'))->body);
    }

    public function testChoosesRouteBySegments(): void
    {
        $router = new Router();
        $router->add('/category/{slug}', fn (): Response => new Response('category'));
        $router->add('/post/{slug}', fn (): Response => new Response('post'));

        self::assertSame('post', $router->dispatch(new Request('GET', '/post/x'))->body);
    }

    public function testDecodesEachSegmentSeparately(): void
    {
        $router = new Router();
        $router->add('/post/{slug}', fn (Request $request, array $params): Response => new Response(
            implode('', $params),
        ));

        self::assertSame('a/b', $router->dispatch(new Request('GET', '/post/a%2Fb'))->body);
        self::assertSame('привет', $router->dispatch(new Request('GET', '/post/%D0%BF%D1%80%D0%B8%D0%B2%D0%B5%D1%82'))->body);
    }

    #[DataProvider('unknownPaths')]
    public function testThrowsNotFoundForUnknownPath(string $path): void
    {
        $router = new Router();
        $router->add('/', fn (): Response => new Response('home'));
        $router->add('/post/{slug}', fn (): Response => new Response('post'));

        $this->expectException(NotFoundException::class);
        $router->dispatch(new Request('GET', $path));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unknownPaths(): array
    {
        return [
            'unknown page' => ['/about'],
            'empty parameter' => ['/post/'],
            'trailing slash' => ['/post/hello/'],
            'extra segment' => ['/post/hello/comments'],
            'double slash' => ['//'],
        ];
    }
}
