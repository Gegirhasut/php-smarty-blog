<?php

declare(strict_types=1);

namespace App\Blog;

use App\Core\Config;
use App\Core\Meta;
use App\Repository\CategoryRepository;
use App\Repository\PostRepository;
use DateTimeImmutable;

/**
 * Заголовок, описание, canonical, хлебные крошки и JSON-LD для каждой страницы блога.
 *
 * @phpstan-import-type Category from CategoryRepository
 * @phpstan-import-type CategoryLink from CategoryRepository
 * @phpstan-import-type PostCard from PostRepository
 */
final class PageMeta
{
    // Картинка для соцсетей у страниц без своей обложки
    private const IMAGE = '/og.png';
    private const HOME = ['name' => 'Главная', 'url' => '/'];

    public function __construct(private readonly Config $config)
    {
    }

    public function home(): Meta
    {
        return new Meta(
            title: 'Блог об ИИ',
            description: $this->config->siteDescription,
            canonical: '/',
            image: self::IMAGE,
            schema: [[
                '@type' => 'WebSite',
                'name' => $this->config->siteName,
                'url' => $this->url('/'),
                'description' => $this->config->siteDescription,
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
     * @param PostCard $post
     * @param list<CategoryLink> $categories категории статьи, первая - основная: она попадает в хлебные крошки
     */
    public function post(array $post, array $categories): Meta
    {
        $path = '/post/' . $post['slug'];
        $breadcrumbs = [self::HOME];
        if ($categories !== []) {
            $breadcrumbs[] = ['name' => $categories[0]['name'], 'url' => '/category/' . $categories[0]['slug']];
        }
        $breadcrumbs[] = ['name' => $post['title'], 'url' => $path];
        // Авторов в блоге нет, статьи выпускает сам блог
        $publisher = ['@type' => 'Organization', 'name' => $this->config->siteName, 'url' => $this->url('/')];

        return new Meta(
            title: $post['title'],
            description: $post['description'],
            canonical: $path,
            ogType: 'article',
            image: $post['image'],
            breadcrumbs: $breadcrumbs,
            schema: [
                [
                    '@type' => 'BlogPosting',
                    'headline' => $post['title'],
                    'description' => $post['description'],
                    'image' => $this->url($post['image']),
                    'datePublished' => (new DateTimeImmutable($post['published_at']))->format(DATE_ATOM),
                    'url' => $this->url($path),
                    'mainEntityOfPage' => $this->url($path),
                    'articleSection' => array_column($categories, 'name'),
                    'author' => $publisher,
                    'publisher' => $publisher,
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
        return $this->config->siteUrl . $path;
    }
}
