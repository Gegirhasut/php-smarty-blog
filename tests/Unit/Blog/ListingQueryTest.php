<?php

declare(strict_types=1);

namespace App\Tests\Unit\Blog;

use App\Blog\ListingQuery;
use App\Blog\Sort;
use App\Core\NotFoundException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ListingQueryTest extends TestCase
{
    public function testDefaultsToNewestFirstPage(): void
    {
        $query = ListingQuery::parse(['utm_source' => 'mail']);

        self::assertSame(Sort::Date, $query->sort);
        self::assertSame(1, $query->page);
    }

    public function testReadsSortAndPage(): void
    {
        $query = ListingQuery::parse(['sort' => 'views', 'page' => '3']);

        self::assertSame(Sort::Views, $query->sort);
        self::assertSame(3, $query->page);
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function invalidQueries(): array
    {
        return [
            'неизвестная сортировка' => [['sort' => 'popular']],
            'сортировка в другом регистре' => [['sort' => 'Views']],
            'пустая сортировка' => [['sort' => '']],
            'сортировка массивом' => [['sort' => ['views']]],
            'нулевая страница' => [['page' => '0']],
            'отрицательная страница' => [['page' => '-1']],
            'ведущий ноль' => [['page' => '02']],
            'плюс' => [['page' => '+2']],
            'дробь' => [['page' => '2.0']],
            'экспонента' => [['page' => '1e3']],
            'пробел' => [['page' => ' 2']],
            'перевод строки в конце' => [['page' => "2\n"]],
            'не число' => [['page' => 'abc']],
            'пустая страница' => [['page' => '']],
            'слишком большая' => [['page' => '1234567890']],
            'страница массивом' => [['page' => ['2']]],
        ];
    }

    /**
     * @param array<string, mixed> $params
     */
    #[DataProvider('invalidQueries')]
    public function testRejectsInvalidValues(array $params): void
    {
        $this->expectException(NotFoundException::class);

        ListingQuery::parse($params);
    }

    /**
     * @return array<string, array{array<string, string>, bool}>
     */
    public static function canonicalQueries(): array
    {
        return [
            'без параметров' => [[], true],
            'посторонний параметр' => [['utm_source' => 'mail'], true],
            'по просмотрам' => [['sort' => 'views'], true],
            'вторая страница по просмотрам' => [['page' => '2', 'sort' => 'views'], true],
            'сортировка по умолчанию' => [['sort' => 'date'], false],
            'первая страница' => [['page' => '1'], false],
            'первая страница по просмотрам' => [['sort' => 'views', 'page' => '1'], false],
            'вторая страница и сортировка по умолчанию' => [['sort' => 'date', 'page' => '2'], false],
        ];
    }

    /**
     * @param array<string, string> $params
     */
    #[DataProvider('canonicalQueries')]
    public function testDefaultValuesInAddressAreNotCanonical(array $params, bool $canonical): void
    {
        self::assertSame($canonical, ListingQuery::parse($params)->isCanonical($params));
    }

    public function testAddressParamsSkipDefaults(): void
    {
        self::assertSame([], (new ListingQuery())->params());
        self::assertSame(['page' => 2], (new ListingQuery(page: 2))->params());
        self::assertSame(['sort' => 'views'], (new ListingQuery(Sort::Views))->params());
        self::assertSame(['sort' => 'views', 'page' => 3], (new ListingQuery(Sort::Views, 3))->params());
    }

    public function testWithPageKeepsSort(): void
    {
        self::assertSame(['sort' => 'views'], (new ListingQuery(Sort::Views, 3))->withPage(1)->params());
    }
}
