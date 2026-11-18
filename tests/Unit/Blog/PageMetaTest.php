<?php

declare(strict_types=1);

namespace App\Tests\Unit\Blog;

use App\Blog\ListingQuery;
use App\Blog\PageMeta;
use App\Blog\Sort;
use App\Core\Meta;
use PHPUnit\Framework\TestCase;

final class PageMetaTest extends TestCase
{
    private const CATEGORY = ['id' => 1, 'slug' => 'tools', 'name' => 'Инструменты', 'description' => 'Обзоры сервисов.'];

    private PageMeta $meta;

    protected function setUp(): void
    {
        $this->meta = new PageMeta('Агентура', 'https://blog.test');
    }

    public function testHome(): void
    {
        $meta = $this->meta->home();

        self::assertSame('/', $meta->canonical);
        self::assertSame('/og.png', $meta->image);
        self::assertNull($meta->robots);
        self::assertSame([], $meta->breadcrumbs);
        self::assertSame([[
            '@type' => 'WebSite',
            'name' => 'Агентура',
            'url' => 'https://blog.test/',
            'description' => $meta->description,
            'inLanguage' => 'ru',
        ]], $meta->schema);
    }

    public function testCategory(): void
    {
        $meta = $this->meta->category(self::CATEGORY, new ListingQuery());

        self::assertSame('Инструменты', $meta->title);
        self::assertSame('Обзоры сервисов.', $meta->description);
        self::assertSame('/category/tools', $meta->canonical);
        self::assertNull($meta->robots);
        self::assertSame('/og.png', $meta->image);
        self::assertSame(
            [['name' => 'Главная', 'url' => '/'], ['name' => 'Инструменты', 'url' => '/category/tools']],
            $meta->breadcrumbs,
        );
        self::assertSame([
            '@type' => 'CollectionPage',
            'name' => 'Инструменты',
            'description' => 'Обзоры сервисов.',
            'url' => 'https://blog.test/category/tools',
        ], $this->node($meta, 'CollectionPage'));
        self::assertSame([
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => 'Главная', 'item' => 'https://blog.test/'],
                ['@type' => 'ListItem', 'position' => 2, 'name' => 'Инструменты', 'item' => 'https://blog.test/category/tools'],
            ],
        ], $this->node($meta, 'BreadcrumbList'));
    }

    public function testCategoryPageNumberGoesToTitleAndCanonical(): void
    {
        $meta = $this->meta->category(self::CATEGORY, new ListingQuery(page: 2));

        self::assertSame('Инструменты, страница 2', $meta->title);
        self::assertSame('/category/tools?page=2', $meta->canonical);
        self::assertNull($meta->robots);
        self::assertSame('https://blog.test/category/tools?page=2', $this->node($meta, 'CollectionPage')['url']);
    }

    public function testCategorySortedByViewsIsNotIndexed(): void
    {
        $meta = $this->meta->category(self::CATEGORY, new ListingQuery(Sort::Views, 2));

        self::assertSame('noindex, follow', $meta->robots);
        self::assertSame('/category/tools?sort=views&page=2', $meta->canonical);
    }

    /**
     * @return array<mixed>
     */
    private function node(Meta $meta, string $type): array
    {
        foreach ($meta->schema as $node) {
            if ($node['@type'] === $type) {
                return $node;
            }
        }

        self::fail("Нет узла {$type}");
    }
}
