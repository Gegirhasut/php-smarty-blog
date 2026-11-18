<?php

declare(strict_types=1);

namespace App\Blog;

use DateTimeImmutable;
use XMLWriter;

/**
 * sitemap.xml: главная, категории и статьи с датой последнего изменения.
 * XML собирает XMLWriter, он же экранирует текст.
 */
final class Sitemap
{
    public function __construct(private readonly string $siteUrl)
    {
    }

    /**
     * @param list<array{slug: string, updated_at: string}> $categories
     * @param list<array{slug: string, published_at: string}> $posts от новых к старым
     */
    public function render(array $categories, array $posts): string
    {
        $xml = new XMLWriter();
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElement('urlset');
        $xml->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');

        // Главная меняется вместе с самой свежей статьёй
        $this->url($xml, '/', $posts[0]['published_at'] ?? null);
        foreach ($categories as $category) {
            $this->url($xml, '/category/' . $category['slug'], $category['updated_at']);
        }
        foreach ($posts as $post) {
            $this->url($xml, '/post/' . $post['slug'], $post['published_at']);
        }

        $xml->endElement();
        $xml->endDocument();

        return $xml->outputMemory();
    }

    private function url(XMLWriter $xml, string $path, ?string $updatedAt): void
    {
        $xml->startElement('url');
        $xml->writeElement('loc', $this->siteUrl . $path);
        if ($updatedAt !== null) {
            $xml->writeElement('lastmod', (new DateTimeImmutable($updatedAt))->format(DATE_ATOM));
        }
        $xml->endElement();
    }
}
