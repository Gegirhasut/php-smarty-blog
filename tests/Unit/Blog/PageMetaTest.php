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

    public function testPost(): void
    {
        $post = [
            'id' => 7,
            'slug' => 'first',
            'title' => 'Первая статья',
            'description' => 'О чём статья.',
            'image' => '/uploads/first.webp',
            'views' => 10,
            'published_at' => '2026-10-09 16:33:00',
        ];
        $categories = [['id' => 1, 'slug' => 'tools', 'name' => 'Инструменты'], ['id' => 2, 'slug' => 'money', 'name' => 'Деньги']];

        $meta = $this->meta->post($post, $categories);

        self::assertSame('Первая статья', $meta->title);
        self::assertSame('О чём статья.', $meta->description);
        self::assertSame('/post/first', $meta->canonical);
        self::assertSame('article', $meta->ogType);
        self::assertSame('/uploads/first.webp', $meta->image);
        self::assertSame([
            ['name' => 'Главная', 'url' => '/'],
            ['name' => 'Инструменты', 'url' => '/category/tools'],
            ['name' => 'Первая статья', 'url' => '/post/first'],
        ], $meta->breadcrumbs);

        $posting = $this->node($meta, 'BlogPosting');
        self::assertSame('Первая статья', $posting['headline']);
        self::assertSame('https://blog.test/uploads/first.webp', $posting['image']);
        // Смещение зависит от date.timezone, проверяем только сами дату и время
        self::assertIsString($posting['datePublished']);
        self::assertStringStartsWith('2026-10-09T16:33:00', $posting['datePublished']);
        self::assertSame('https://blog.test/post/first', $posting['mainEntityOfPage']);
        self::assertSame(['Инструменты', 'Деньги'], $posting['articleSection']);
        self::assertSame(['@type' => 'Organization', 'name' => 'Агентура', 'url' => 'https://blog.test/'], $posting['publisher']);
        $crumbs = $this->node($meta, 'BreadcrumbList')['itemListElement'];
        self::assertIsArray($crumbs);
        self::assertCount(3, $crumbs);
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
