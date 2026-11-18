<?php

declare(strict_types=1);

namespace App\Tests\Unit\Core;

use App\Core\Request;
use PHPUnit\Framework\Attributes\BackupGlobals;
use PHPUnit\Framework\TestCase;

#[BackupGlobals(true)]
final class RequestTest extends TestCase
{
    public function testTakesMethodPathAndQueryFromGlobals(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'HEAD';
        $_SERVER['REQUEST_URI'] = '/category/travel?sort=views&page=2';
        $_GET = ['sort' => 'views', 'page' => '2'];

        $request = Request::fromGlobals();

        self::assertSame('HEAD', $request->method);
        self::assertSame('/category/travel', $request->path);
        self::assertSame(['sort' => 'views', 'page' => '2'], $request->query);
    }

    public function testLeavesPathEncodedForRouter(): void
    {
        $_SERVER['REQUEST_URI'] = '/post/a%2Fb?x=1';

        self::assertSame('/post/a%2Fb', Request::fromGlobals()->path);
    }

    public function testFallsBackToRootGetWithoutServerData(): void
    {
        unset($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);

        $request = Request::fromGlobals();

        self::assertSame('GET', $request->method);
        self::assertSame('/', $request->path);
    }
}
