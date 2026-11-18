<?php

declare(strict_types=1);

namespace App\Tests\Unit\Core;

use App\Core\Pagination;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PaginationTest extends TestCase
{
    /**
     * @return array<string, array{int, int}>
     */
    public static function totals(): array
    {
        return [
            'пусто' => [0, 1],
            'ровно страница' => [9, 1],
            'на одну больше' => [10, 2],
            'несколько страниц' => [27, 3],
        ];
    }

    #[DataProvider('totals')]
    public function testCountsPages(int $total, int $pages): void
    {
        self::assertSame($pages, (new Pagination(1, $total, 9))->pages);
    }

    public function testEmptyListHasFirstPageOnly(): void
    {
        self::assertTrue((new Pagination(1, 0, 9))->exists());
        self::assertFalse((new Pagination(2, 0, 9))->exists());
    }

    public function testPageAfterLastDoesNotExist(): void
    {
        self::assertTrue((new Pagination(2, 10, 9))->exists());
        self::assertFalse((new Pagination(3, 10, 9))->exists());
    }

    public function testOffset(): void
    {
        self::assertSame(0, (new Pagination(1, 30, 9))->offset());
        self::assertSame(18, (new Pagination(3, 30, 9))->offset());
    }

    /**
     * @return array<string, array{int, int, string}>
     */
    public static function numbers(): array
    {
        return [
            'одна страница' => [1, 1, '[1]'],
            'начало' => [1, 6, '[1] 2 3 … 6'],
            'конец' => [6, 6, '1 … 4 5 [6]'],
            'середина' => [6, 11, '1 … 4 5 [6] 7 8 … 11'],
            'рядом с первой' => [3, 10, '1 2 [3] 4 5 … 10'],
            'пропуск из одной страницы' => [5, 10, '1 2 3 4 [5] 6 7 … 10'],
        ];
    }

    #[DataProvider('numbers')]
    public function testShowsNumbersAroundCurrentPage(int $page, int $pages, string $expected): void
    {
        $links = (new Pagination($page, $pages * 9, 9))->links(fn (int $number): string => "/p/{$number}");

        $numbers = array_map(
            fn (?array $link): string => match (true) {
                $link === null => '…',
                $link['current'] => "[{$link['number']}]",
                default => (string) $link['number'],
            },
            $links['pages'],
        );

        self::assertSame($expected, implode(' ', $numbers));
    }

    public function testLinksToNeighbours(): void
    {
        $url = fn (int $number): string => "/p/{$number}";

        $first = (new Pagination(1, 27, 9))->links($url);
        $middle = (new Pagination(2, 27, 9))->links($url);
        $last = (new Pagination(3, 27, 9))->links($url);

        self::assertSame([null, '/p/2'], [$first['prev'], $first['next']]);
        self::assertSame(['/p/1', '/p/3'], [$middle['prev'], $middle['next']]);
        self::assertSame(['/p/2', null], [$last['prev'], $last['next']]);
        self::assertSame(['number' => 2, 'url' => '/p/2', 'current' => true], $middle['pages'][1]);
    }
}
