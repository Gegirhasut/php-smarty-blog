<?php

declare(strict_types=1);

namespace App\Seo;

use App\Blog\Urls;
use DateTimeImmutable;
use XMLWriter;

/**
 * Sitemap по протоколу sitemaps.org. В одном файле не больше 50 000 адресов, поэтому /sitemap.xml - индекс файлов:
 * главная с категориями и статьи, разложенные по диапазонам id. Старые статьи остаются в своих файлах, новые
 * дописываются в последний. XML собирает XMLWriter, он же экранирует текст.
 */
final class Sitemap
{
    // Протокол разрешает до 50 000 адресов в файле, но такой файл собирается полсекунды и весит 7 МБ.
    // Файл на 1000 статей - миллисекунды, а индекс вмещает 50 000 файлов
    public const POSTS_PER_FILE = 1000;

    private const XMLNS = 'http://www.sitemaps.org/schemas/sitemap/0.9';

    public function __construct(private readonly Urls $urls)
    {
    }

    /**
     * У файлов статей lastmod нет (по протоколу он необязателен): точная дата файла - MAX по его диапазону, то есть
     * проход по всем статьям на каждый запрос индекса. Даты самих статей - в их файлах.
     *
     * @param ?DateTimeImmutable $updatedAt дата самой свежей статьи: с ней меняются главная и категории
     * @param int $postFiles сколько файлов со статьями
     */
    public function index(?DateTimeImmutable $updatedAt, int $postFiles): string
    {
        $xml = $this->start('sitemapindex');
        $this->entry($xml, 'sitemap', $this->urls->sitemapCategories(), $updatedAt);
        for ($number = 1; $number <= $postFiles; $number++) {
            $this->entry($xml, 'sitemap', $this->urls->sitemapPosts($number), null);
        }

        return $this->finish($xml);
    }

    /**
     * @param ?DateTimeImmutable $updatedAt дата самой свежей статьи блога
     * @param array<string, DateTimeImmutable> $categories slug категории => дата её самой свежей статьи
     */
    public function categories(?DateTimeImmutable $updatedAt, array $categories): string
    {
        $xml = $this->start('urlset');
        // Главная меняется вместе с самой свежей статьёй
        $this->entry($xml, 'url', $this->urls->home(), $updatedAt);
        foreach ($categories as $slug => $categoryUpdatedAt) {
            $this->entry($xml, 'url', $this->urls->category($slug), $categoryUpdatedAt);
        }

        return $this->finish($xml);
    }

    /**
     * @param array<string, DateTimeImmutable> $posts slug статьи => дата публикации
     */
    public function posts(array $posts): string
    {
        $xml = $this->start('urlset');
        foreach ($posts as $slug => $publishedAt) {
            $this->entry($xml, 'url', $this->urls->post($slug), $publishedAt);
        }

        return $this->finish($xml);
    }

    private function start(string $root): XMLWriter
    {
        $xml = new XMLWriter();
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElement($root);
        $xml->writeAttribute('xmlns', self::XMLNS);

        return $xml;
    }

    private function finish(XMLWriter $xml): string
    {
        $xml->endElement();
        $xml->endDocument();

        return $xml->outputMemory();
    }

    /**
     * Адрес страницы (url) или файла sitemap (sitemap) с датой изменения; без даты lastmod не пишется.
     */
    private function entry(XMLWriter $xml, string $element, string $path, ?DateTimeImmutable $updatedAt): void
    {
        $xml->startElement($element);
        $xml->writeElement('loc', $this->urls->absolute($path));
        if ($updatedAt !== null) {
            $xml->writeElement('lastmod', $updatedAt->format(DATE_ATOM));
        }
        $xml->endElement();
    }
}
