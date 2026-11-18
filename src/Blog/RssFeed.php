<?php

declare(strict_types=1);

namespace App\Blog;

use App\Core\Config;
use App\Repository\PostRepository;
use DateTimeImmutable;
use XMLWriter;

/**
 * RSS 2.0 со свежими статьями. XML собирает XMLWriter, он же экранирует текст.
 *
 * @phpstan-import-type PostCard from PostRepository
 */
final class RssFeed
{
    public function __construct(private readonly Config $config)
    {
    }

    /**
     * @param list<PostCard> $posts от новых к старым
     * @param array<int, list<string>> $categories названия категорий по id статьи
     */
    public function render(array $posts, array $categories): string
    {
        $siteUrl = $this->config->siteUrl;

        $xml = new XMLWriter();
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElement('rss');
        $xml->writeAttribute('version', '2.0');
        $xml->writeAttribute('xmlns:atom', 'http://www.w3.org/2005/Atom');
        $xml->startElement('channel');

        $xml->writeElement('title', $this->config->siteName);
        $xml->writeElement('link', $siteUrl . '/');
        $xml->writeElement('description', $this->config->siteDescription);
        $xml->writeElement('language', 'ru');
        // Адрес самой ленты: по нему читалки узнают, откуда она пришла
        $xml->startElement('atom:link');
        $xml->writeAttribute('href', $siteUrl . '/feed.xml');
        $xml->writeAttribute('rel', 'self');
        $xml->writeAttribute('type', 'application/rss+xml');
        $xml->endElement();

        foreach ($posts as $post) {
            $link = $siteUrl . '/post/' . $post['slug'];
            $xml->startElement('item');
            $xml->writeElement('title', $post['title']);
            $xml->writeElement('link', $link);
            $xml->startElement('guid');
            $xml->writeAttribute('isPermaLink', 'true');
            $xml->text($link);
            $xml->endElement();
            $xml->writeElement('pubDate', (new DateTimeImmutable($post['published_at']))->format(DATE_RSS));
            $xml->writeElement('description', $post['description']);
            foreach ($categories[$post['id']] ?? [] as $category) {
                $xml->writeElement('category', $category);
            }
            $xml->endElement();
        }

        $xml->endElement();
        $xml->endElement();
        $xml->endDocument();

        return $xml->outputMemory();
    }
}
