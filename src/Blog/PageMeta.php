<?php

declare(strict_types=1);

namespace App\Blog;

use App\Core\Meta;
use App\Repository\CategoryRepository;

/**
 * Заголовок, описание, canonical, хлебные крошки и JSON-LD для каждой страницы блога.
 *
 * @phpstan-import-type Category from CategoryRepository
 */
final class PageMeta
{
    private const DESCRIPTION = 'Блог об ИИ: новые модели и инструменты, агенты, исследования, деньги и люди.';
    // Картинка для соцсетей у страниц без своей обложки
    private const IMAGE = '/og.png';
    private const HOME = ['name' => 'Главная', 'url' => '/'];

    public function __construct(
        private readonly string $siteName,
        private readonly string $siteUrl,
    ) {
    }

    public function home(): Meta
    {
        return new Meta(
            title: 'Блог об ИИ',
            description: self::DESCRIPTION,
            canonical: '/',
            image: self::IMAGE,
            schema: [[
                '@type' => 'WebSite',
                'name' => $this->siteName,
                'url' => $this->url('/'),
                'description' => self::DESCRIPTION,
                'inLanguage' => 'ru',
            ]],
        );
    }

    /**
     * @param Category $category
     */
    public function category(array $category, ListingQuery $query): Meta
    {
        $path = '/category/' . $category['slug'];
        $canonical = $query->url($path);
        $breadcrumbs = [self::HOME, ['name' => $category['name'], 'url' => $path]];

        return new Meta(
            title: $query->page > 1 ? "{$category['name']}, страница {$query->page}" : $category['name'],
            description: $category['description'],
            canonical: $canonical,
            // Список по просмотрам - те же статьи в другом порядке: индексировать в нём нечего, а ссылки на статьи
            // пусть обходятся. canonical на список по дате не подходит: на одной и той же странице у них разные статьи
            robots: $query->sort === Sort::Views ? 'noindex, follow' : null,
            image: self::IMAGE,
            breadcrumbs: $breadcrumbs,
            schema: [
                [
                    '@type' => 'CollectionPage',
                    'name' => $category['name'],
                    'description' => $category['description'],
                    'url' => $this->url($canonical),
                ],
                $this->breadcrumbList($breadcrumbs),
            ],
        );
    }

    /**
     * @param list<array{name: string, url: string}> $breadcrumbs
     * @return array<string, mixed>
     */
    private function breadcrumbList(array $breadcrumbs): array
    {
        $items = [];
        foreach ($breadcrumbs as $index => $crumb) {
            $items[] = [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $crumb['name'],
                'item' => $this->url($crumb['url']),
            ];
        }

        return ['@type' => 'BreadcrumbList', 'itemListElement' => $items];
    }

    private function url(string $path): string
    {
        return $this->siteUrl . $path;
    }
}
