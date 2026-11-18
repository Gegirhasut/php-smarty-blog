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
    public function testListsHomeCategoriesAndPosts(): void
    {
        $xml = (new Sitemap(new Urls('https://blog.test')))->render(
            ['tools' => new DateTimeImmutable('2026-10-05 10:00:00')],
            [
                'new' => new DateTimeImmutable('2026-10-05 10:00:00'),
                'old' => new DateTimeImmutable('2026-09-01 08:30:00'),
            ],
        );

        $urls = $this->urls($xml);

        self::assertSame([
            'https://blog.test/',
            'https://blog.test/category/tools',
            'https://blog.test/post/new',
            'https://blog.test/post/old',
        ], array_keys($urls));
        // Главная обновляется вместе с самой свежей статьёй
        self::assertSame('2026-10-05T10:00:00+00:00', $urls['https://blog.test/']);
        self::assertSame('2026-09-01T08:30:00+00:00', $urls['https://blog.test/post/old']);
    }

    public function testEmptyBlogHasHomeWithoutDate(): void
    {
        self::assertSame(['https://blog.test/' => ''], $this->urls((new Sitemap(new Urls('https://blog.test')))->render([], [])));
    }

    /**
     * @return array<string, string> адрес => lastmod
     */
    private function urls(string $xml): array
    {
        $document = new DOMDocument();
        self::assertTrue($document->loadXML($xml));
        self::assertSame('http://www.sitemaps.org/schemas/sitemap/0.9', $document->documentElement?->namespaceURI);

        $urls = [];
        foreach ($document->getElementsByTagName('url') as $url) {
            $loc = (string) $url->getElementsByTagName('loc')->item(0)?->textContent;
            $urls[$loc] = (string) $url->getElementsByTagName('lastmod')->item(0)?->textContent;
        }

        return $urls;
    }
}
