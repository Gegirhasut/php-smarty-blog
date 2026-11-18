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
     * @param array<string, DateTimeImmutable> $categories slug категории => дата её самой свежей статьи
     * @param array<string, DateTimeImmutable> $posts slug статьи => дата публикации, от новых к старым
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
        $this->url($xml, '/', $posts === [] ? null : reset($posts));
        foreach ($categories as $slug => $updatedAt) {
            $this->url($xml, '/category/' . $slug, $updatedAt);
        }
        foreach ($posts as $slug => $publishedAt) {
            $this->url($xml, '/post/' . $slug, $publishedAt);
        }

        $xml->endElement();
        $xml->endDocument();

        return $xml->outputMemory();
    }

    private function url(XMLWriter $xml, string $path, ?DateTimeImmutable $updatedAt): void
    {
        $xml->startElement('url');
        $xml->writeElement('loc', $this->siteUrl . $path);
        if ($updatedAt !== null) {
            $xml->writeElement('lastmod', $updatedAt->format(DATE_ATOM));
        }
        $xml->endElement();
    }
}
