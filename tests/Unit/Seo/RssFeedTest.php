<?php

declare(strict_types=1);

namespace App\Tests\Unit\Seo;

use App\Blog\PostCard;
use App\Blog\Urls;
use App\Core\Config;
use App\Seo\RssFeed;
use DateTimeImmutable;
use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\TestCase;

final class RssFeedTest extends TestCase
{
    private RssFeed $feed;

    protected function setUp(): void
    {
        $this->feed = new RssFeed(new Config('Агентура', 'Блог об ИИ.', 'https://blog.test', debug: false), new Urls('https://blog.test'));
    }

    public function testDescribesChannel(): void
    {
        $rss = $this->parse($this->feed->render([], []));

        self::assertSame('2.0', $this->value($rss, '/rss/@version'));
        self::assertSame('Агентура', $this->value($rss, '/rss/channel/title'));
        self::assertSame('https://blog.test/', $this->value($rss, '/rss/channel/link'));
        self::assertSame('Блог об ИИ.', $this->value($rss, '/rss/channel/description'));
        self::assertSame('https://blog.test/feed.xml', $this->value($rss, '/rss/channel/atom:link[@rel="self"]/@href'));
        self::assertSame([], $this->values($rss, '//item'));
    }

    public function testDescribesEachPost(): void
    {
        $posts = [
            $this->post(7, 'new', 'Новости & <обзоры>', '2026-10-09 16:33:00'),
            $this->post(3, 'old', 'Старая', '2026-09-01 10:00:00'),
        ];

        $rss = $this->parse($this->feed->render($posts, [7 => ['Инструменты', 'Деньги']]));

        self::assertSame(['https://blog.test/post/new', 'https://blog.test/post/old'], $this->values($rss, '//item/link'));
        self::assertSame('Новости & <обзоры>', $this->value($rss, '//item[1]/title'));
        self::assertSame('https://blog.test/post/new', $this->value($rss, '//item[1]/guid[@isPermaLink="true"]'));
        self::assertSame('Fri, 09 Oct 2026 16:33:00 +0000', $this->value($rss, '//item[1]/pubDate'));
        self::assertSame('Описание статьи new.', $this->value($rss, '//item[1]/description'));
        self::assertSame(['Инструменты', 'Деньги'], $this->values($rss, '//item[1]/category'));
        self::assertSame([], $this->values($rss, '//item[2]/category'));
    }

    private function post(int $id, string $slug, string $title, string $publishedAt): PostCard
    {
        return new PostCard($id, $slug, $title, "Описание статьи {$slug}.", "/uploads/{$slug}.webp", 0, new DateTimeImmutable($publishedAt));
    }

    private function parse(string $xml): DOMXPath
    {
        $document = new DOMDocument();
        self::assertTrue($document->loadXML($xml));
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('atom', 'http://www.w3.org/2005/Atom');

        return $xpath;
    }

    private function value(DOMXPath $xpath, string $query): string
    {
        $values = $this->values($xpath, $query);
        self::assertNotEmpty($values, "Не найдено: {$query}");

        return $values[0];
    }

    /**
     * @return list<string>
     */
    private function values(DOMXPath $xpath, string $query): array
    {
        $values = [];
        foreach ($xpath->query($query) ?: [] as $node) {
            $values[] = (string) $node->nodeValue;
        }

        return $values;
    }
}
