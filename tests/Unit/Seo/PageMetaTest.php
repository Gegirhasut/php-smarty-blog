<?php

declare(strict_types=1);

namespace App\Tests\Unit\Seo;

use App\Blog\Category;
use App\Blog\ListingQuery;
use App\Blog\Post;
use App\Blog\Sort;
use App\Blog\Urls;
use App\Core\Config;
use App\Seo\Meta;
use App\Seo\PageMeta;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class PageMetaTest extends TestCase
{
    private PageMeta $meta;
    private Category $category;

    protected function setUp(): void
    {
        $this->meta = new PageMeta(new Config('Агентура', 'Блог об ИИ.', 'https://blog.test', '/og.jpg', debug: false), new Urls('https://blog.test'));
        $this->category = new Category(1, 'tools', 'Инструменты', 'Обзоры сервисов.');
    }

    public function testHome(): void
    {
        $meta = $this->meta->home();

        self::assertSame('/', $meta->canonical);
        self::assertSame('/og.jpg', $meta->image);
        self::assertNull($meta->robots);
        self::assertSame([], $meta->breadcrumbs);
        self::assertSame([[
            '@type' => 'WebSite',
            'name' => 'Агентура',
            'url' => 'https://blog.test/',
            'description' => 'Блог об ИИ.',
            'inLanguage' => 'ru',
        ]], $meta->schema);
    }

    public function testCategory(): void
    {
        $meta = $this->meta->category($this->category, new ListingQuery());

        self::assertSame('Инструменты', $meta->title);
        self::assertSame('Обзоры сервисов.', $meta->description);
        self::assertSame('/category/tools', $meta->canonical);
        self::assertNull($meta->robots);
        self::assertSame('/og.jpg', $meta->image);
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
        $meta = $this->meta->category($this->category, new ListingQuery(page: 2));

        self::assertSame('Инструменты, страница 2', $meta->title);
        self::assertSame('/category/tools?page=2', $meta->canonical);
        self::assertNull($meta->robots);
        self::assertSame('https://blog.test/category/tools?page=2', $this->node($meta, 'CollectionPage')['url']);
    }

    public function testCategorySortedByViewsIsNotIndexed(): void
    {
        $meta = $this->meta->category($this->category, new ListingQuery(Sort::Views, 2));

        self::assertSame('noindex, follow', $meta->robots);
        self::assertSame('/category/tools?sort=views&page=2', $meta->canonical);
    }

    public function testPost(): void
    {
        $post = new Post(
            id: 7,
            slug: 'first',
            title: 'Первая статья',
            description: 'О чём статья.',
            image: '/uploads/first.webp',
            views: 10,
            publishedAt: new DateTimeImmutable('2026-10-09 16:33:00'),
            content: 'Текст.',
        );
        $categories = [$this->category, new Category(2, 'money', 'Деньги', 'О деньгах.')];

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
        self::assertSame('2026-10-09T16:33:00+00:00', $posting['datePublished']);
        self::assertSame('https://blog.test/post/first', $posting['mainEntityOfPage']);
        self::assertSame(['Инструменты', 'Деньги'], $posting['articleSection']);
        self::assertSame(['@type' => 'Organization', 'name' => 'Агентура', 'url' => 'https://blog.test/'], $posting['publisher']);
        $crumbs = $this->node($meta, 'BreadcrumbList')['itemListElement'];
        self::assertIsArray($crumbs);
        self::assertCount(3, $crumbs);
    }

    public function testNotFoundPageIsNotShared(): void
    {
        $meta = $this->meta->notFound();

        self::assertSame('Страница не найдена', $meta->title);
        self::assertNull($meta->canonical);
        self::assertSame([], $meta->schema);
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
