<?php

declare(strict_types=1);

namespace App\Tests\Unit\Seo;

use App\Blog\Urls;
use App\Seo\Sitemap;
use DateTimeImmutable;
use DOMDocument;
use PHPUnit\Framework\TestCase;

final class SitemapTest extends TestCase
{
    private Sitemap $sitemap;

    protected function setUp(): void
    {
        $this->sitemap = new Sitemap(new Urls('https://blog.test'));
    }

    public function testIndexListsCategoriesFileAndPostFiles(): void
    {
        $xml = $this->sitemap->index(new DateTimeImmutable('2026-10-05 10:00:00'), 2);

        self::assertSame([
            'https://blog.test/sitemap-categories.xml' => '2026-10-05T10:00:00+00:00',
            'https://blog.test/sitemap-posts-1.xml' => '',
            'https://blog.test/sitemap-posts-2.xml' => '',
        ], $this->entries($xml, 'sitemapindex', 'sitemap'));
    }

    public function testCategoriesFileListsHomeAndCategories(): void
    {
        $xml = $this->sitemap->categories(
            new DateTimeImmutable('2026-10-05 10:00:00'),
            ['tools' => new DateTimeImmutable('2026-10-05 10:00:00'), 'money' => new DateTimeImmutable('2026-09-01 08:30:00')],
        );

        // Главная обновляется вместе с самой свежей статьёй
        self::assertSame([
            'https://blog.test/' => '2026-10-05T10:00:00+00:00',
            'https://blog.test/category/tools' => '2026-10-05T10:00:00+00:00',
            'https://blog.test/category/money' => '2026-09-01T08:30:00+00:00',
        ], $this->entries($xml, 'urlset', 'url'));
    }

    public function testPostsFileListsPostsInGivenOrder(): void
    {
        $posts = [
            'old' => new DateTimeImmutable('2026-09-01 08:30:00'),
            'new' => new DateTimeImmutable('2026-10-05 10:00:00'),
        ];

        self::assertSame([
            'https://blog.test/post/old' => '2026-09-01T08:30:00+00:00',
            'https://blog.test/post/new' => '2026-10-05T10:00:00+00:00',
        ], $this->entries($this->sitemap->posts($posts), 'urlset', 'url'));
    }

    public function testEmptyBlogHasHomeWithoutDate(): void
    {
        self::assertSame(
            ['https://blog.test/sitemap-categories.xml' => ''],
            $this->entries($this->sitemap->index(null, 0), 'sitemapindex', 'sitemap'),
        );
        self::assertSame(['https://blog.test/' => ''], $this->entries($this->sitemap->categories(null, []), 'urlset', 'url'));
    }

    /**
     * @return array<string, string> адрес => lastmod
     */
    private function entries(string $xml, string $root, string $element): array
    {
        $document = new DOMDocument();
        self::assertTrue($document->loadXML($xml));
        self::assertSame($root, $document->documentElement?->localName);
        self::assertSame('http://www.sitemaps.org/schemas/sitemap/0.9', $document->documentElement->namespaceURI);

        $entries = [];
        foreach ($document->getElementsByTagName($element) as $entry) {
            $loc = (string) $entry->getElementsByTagName('loc')->item(0)?->textContent;
            $entries[$loc] = (string) $entry->getElementsByTagName('lastmod')->item(0)?->textContent;
        }

        return $entries;
    }
}
