<?php

declare(strict_types=1);

namespace App\Tests\Unit\Blog;

use App\Blog\ListingQuery;
use App\Blog\Sort;
use App\Blog\Urls;
use PHPUnit\Framework\TestCase;

final class UrlsTest extends TestCase
{
    private Urls $urls;

    protected function setUp(): void
    {
        $this->urls = new Urls('https://blog.test');
    }

    public function testPages(): void
    {
        self::assertSame('/', $this->urls->home());
        self::assertSame('/post/first', $this->urls->post('first'));
        self::assertSame('/feed.xml', $this->urls->feed());
        self::assertSame('/sitemap.xml', $this->urls->sitemap());
        self::assertSame('/sitemap-categories.xml', $this->urls->sitemapCategories());
        self::assertSame('/sitemap-posts-2.xml', $this->urls->sitemapPosts(2));
    }

    public function testCategoryAddressSkipsDefaults(): void
    {
        self::assertSame('/category/tools', $this->urls->category('tools'));
        self::assertSame('/category/tools', $this->urls->category('tools', new ListingQuery(Sort::Date, 1)));
        self::assertSame('/category/tools?page=2', $this->urls->category('tools', new ListingQuery(page: 2)));
        self::assertSame('/category/tools?sort=views&page=3', $this->urls->category('tools', new ListingQuery(Sort::Views, 3)));
    }

    public function testAbsoluteAddressUsesSiteUrl(): void
    {
        self::assertSame('https://blog.test/post/first', $this->urls->absolute($this->urls->post('first')));
    }
}
