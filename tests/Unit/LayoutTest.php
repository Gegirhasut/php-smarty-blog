<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Blog\Category;
use App\Blog\Urls;
use App\Core\View;
use App\Seo\Meta;
use App\Tests\Html;
use App\Tests\TempDir;
use PHPUnit\Framework\TestCase;

final class LayoutTest extends TestCase
{
    // Шаблоны компилируются один раз на класс, а не в каждом тесте
    private static TempDir $compiled;
    private static View $view;

    public static function setUpBeforeClass(): void
    {
        self::$compiled = new TempDir();
        $root = dirname(__DIR__, 2);
        self::$view = new View($root . '/templates', self::$compiled->path, $root . '/public', debug: true);
        self::$view->share('site', ['name' => 'Блог', 'description' => 'Блог об ИИ.']);
        self::$view->share('urls', new Urls('https://blog.test'));
    }

    public static function tearDownAfterClass(): void
    {
        self::$compiled->remove();
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

        self::assertSame('Статья "с кавычками" <и тегом> — Блог', $page->text('//title'));
        self::assertSame('Описание & детали', $page->attribute('//meta[@name="description"]', 'content'));
        self::assertSame($url, $page->attribute('//link[@rel="canonical"]', 'href'));
        self::assertSame($url, $page->attribute('//meta[@property="og:url"]', 'content'));
        self::assertSame('article', $page->attribute('//meta[@property="og:type"]', 'content'));
        self::assertSame('Статья "с кавычками" <и тегом>', $page->attribute('//meta[@property="og:title"]', 'content'));
        self::assertSame('https://blog.test/uploads/cover.webp', $page->attribute('//meta[@property="og:image"]', 'content'));
        self::assertSame('summary_large_image', $page->attribute('//meta[@name="twitter:card"]', 'content'));
        self::assertSame(1, $page->count('//script'));
        self::assertSame(
            ['@context' => 'https://schema.org', '@graph' => $schema],
            json_decode($page->text('//script[@type="application/ld+json"]'), true),
        );
    }

    public function testPageWithoutCanonicalHasNoSocialTags(): void
    {
        $page = $this->render(new Meta(title: 'Страница не найдена'));

        self::assertSame('Страница не найдена — Блог', $page->text('//title'));
        self::assertSame(0, $page->count(
            '//link[@rel="canonical"] | //meta[@property] | //meta[starts-with(@name, "twitter:")] | //script',
        ));
    }

    public function testUsesSiteNameAsDefaultTitle(): void
    {
        $page = $this->render(new Meta(canonical: '/'));

        self::assertSame('Блог', $page->text('//title'));
        self::assertSame('Блог', $page->attribute('//meta[@property="og:title"]', 'content'));
        self::assertSame('summary', $page->attribute('//meta[@name="twitter:card"]', 'content'));
        self::assertSame(0, $page->count('//meta[@property="og:image"]'));
    }

    public function testRendersPageContentInsideMain(): void
    {
        self::assertSame('Страница', $this->render(new Meta())->text('//main/h1'));
    }

    public function testMarksCurrentCategoryInMenu(): void
    {
        $page = $this->render(new Meta(), [
            'categories' => [
                new Category(1, 'tools', 'Инструменты', 'Обзоры.'),
                new Category(2, 'agents', 'Агенты', 'Об агентах.'),
            ],
            'currentCategory' => 'agents',
        ]);

        self::assertSame(['/category/tools', '/category/agents'], $page->attributes('//nav//a', 'href'));
        self::assertSame(['Агенты'], $page->texts('//nav//a[@aria-current="page"]'));
    }

    public function testLinksToFeed(): void
    {
        self::assertSame(
            '/feed.xml',
            $this->render(new Meta())->attribute('//link[@rel="alternate"][@type="application/rss+xml"]', 'href'),
        );
    }

    public function testLinkedFilesExist(): void
    {
        $page = $this->render(new Meta());
        $files = $page->attributes('//link[@rel="icon" or @rel="apple-touch-icon" or @rel="manifest" or @rel="preload"]', 'href');

        self::assertCount(5, $files);
        foreach ($files as $file) {
            self::assertFileExists(dirname(__DIR__, 2) . '/public' . $file);
        }
    }

    public function testMenuIsHiddenWithoutCategories(): void
    {
        self::assertSame(0, $this->render(new Meta())->count('//nav'));
    }

    public function testRendersRobotsOnlyWhenSet(): void
    {
        self::assertSame(0, $this->render(new Meta(canonical: '/'))->count('//meta[@name="robots"]'));
        self::assertSame(
            'noindex, follow',
            $this->render(new Meta(canonical: '/', robots: 'noindex, follow'))->attribute('//meta[@name="robots"]', 'content'),
        );
    }

    public function testRendersBreadcrumbsWithCurrentPageLast(): void
    {
        $page = $this->render(new Meta(breadcrumbs: [
            ['name' => 'Главная', 'url' => '/'],
            ['name' => 'Инструменты', 'url' => '/category/tools'],
            ['name' => 'Статья <b>', 'url' => '/post/article'],
        ]));

        self::assertSame(['Главная', 'Инструменты', 'Статья <b>'], $page->texts('//nav[@aria-label="Хлебные крошки"]//li'));
        self::assertSame(['/', '/category/tools'], $page->attributes('//nav[@aria-label="Хлебные крошки"]//a', 'href'));
        self::assertSame(['Статья <b>'], $page->texts('//nav[@aria-label="Хлебные крошки"]//*[@aria-current="page"]'));
    }

    /**
     * @param array<string, mixed> $data
     */
    private function render(Meta $meta, array $data = []): Html
    {
        return new Html(self::$view->render(
            'string:{extends "layout.tpl"}{block "content"}<h1>Страница</h1>{/block}',
            ['meta' => $meta] + $data,
        ));
    }
}
