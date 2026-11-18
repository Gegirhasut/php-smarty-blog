<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Core\Meta;
use App\Core\View;
use App\Tests\TempDir;
use DOMDocument;
use DOMElement;
use DOMXPath;
use PHPUnit\Framework\TestCase;

final class LayoutTest extends TestCase
{
    private TempDir $compiled;
    private View $view;

    protected function setUp(): void
    {
        $this->compiled = new TempDir();
        $this->view = new View(dirname(__DIR__, 2) . '/templates', $this->compiled->path, debug: true);
        $this->view->share('site', ['name' => 'Блог', 'url' => 'https://blog.test']);
    }

    protected function tearDown(): void
    {
        $this->compiled->remove();
    }

    public function testRendersSeoTags(): void
    {
        $schema = [['@type' => 'BlogPosting', 'headline' => '</script><b>']];
        $page = $this->render(new Meta(
            title: 'Статья "с кавычками" <и тегом>',
            description: 'Описание & детали',
            canonical: '/category/travel?page=2&sort=date',
            ogType: 'article',
            image: '/uploads/cover.webp',
            schema: $schema,
        ));
        $url = 'https://blog.test/category/travel?page=2&sort=date';

        self::assertSame('Статья "с кавычками" <и тегом> — Блог', $this->text($page, '//title'));
        self::assertSame('Описание & детали', $this->attribute($page, '//meta[@name="description"]', 'content'));
        self::assertSame($url, $this->attribute($page, '//link[@rel="canonical"]', 'href'));
        self::assertSame($url, $this->attribute($page, '//meta[@property="og:url"]', 'content'));
        self::assertSame('article', $this->attribute($page, '//meta[@property="og:type"]', 'content'));
        self::assertSame('Статья "с кавычками" <и тегом>', $this->attribute($page, '//meta[@property="og:title"]', 'content'));
        self::assertSame('https://blog.test/uploads/cover.webp', $this->attribute($page, '//meta[@property="og:image"]', 'content'));
        self::assertSame('summary_large_image', $this->attribute($page, '//meta[@name="twitter:card"]', 'content'));
        self::assertSame(1, $this->countNodes($page, '//script'));
        self::assertSame(
            ['@context' => 'https://schema.org', '@graph' => $schema],
            json_decode($this->text($page, '//script[@type="application/ld+json"]'), true),
        );
    }

    public function testPageWithoutCanonicalHasNoSocialTags(): void
    {
        $page = $this->render(new Meta(title: 'Страница не найдена'));

        self::assertSame('Страница не найдена — Блог', $this->text($page, '//title'));
        self::assertSame(0, $this->countNodes(
            $page,
            '//link[@rel="canonical"] | //meta[@property] | //meta[starts-with(@name, "twitter:")] | //script',
        ));
    }

    public function testUsesSiteNameAsDefaultTitle(): void
    {
        $page = $this->render(new Meta(canonical: '/'));

        self::assertSame('Блог', $this->text($page, '//title'));
        self::assertSame('Блог', $this->attribute($page, '//meta[@property="og:title"]', 'content'));
        self::assertSame('summary', $this->attribute($page, '//meta[@name="twitter:card"]', 'content'));
        self::assertSame(0, $this->countNodes($page, '//meta[@property="og:image"]'));
    }

    public function testRendersPageContentInsideMain(): void
    {
        self::assertSame('Страница', $this->text($this->render(new Meta()), '//main/h1'));
    }

    private function render(Meta $meta): DOMXPath
    {
        $html = $this->view->render(
            'string:{extends "layout.tpl"}{block "content"}<h1>Страница</h1>{/block}',
            ['meta' => $meta],
        );
        $document = new DOMDocument();
        $document->loadHTML($html, LIBXML_NOERROR);

        return new DOMXPath($document);
    }

    private function countNodes(DOMXPath $page, string $query): int
    {
        $nodes = $page->query($query);

        return $nodes === false ? 0 : $nodes->length;
    }

    private function text(DOMXPath $page, string $query): string
    {
        return $this->element($page, $query)->textContent;
    }

    private function attribute(DOMXPath $page, string $query, string $name): string
    {
        return $this->element($page, $query)->getAttribute($name);
    }

    private function element(DOMXPath $page, string $query): DOMElement
    {
        $nodes = $page->query($query);
        $node = $nodes === false ? null : $nodes->item(0);
        self::assertInstanceOf(DOMElement::class, $node, "Не найдено: {$query}");

        return $node;
    }
}
