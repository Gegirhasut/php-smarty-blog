<?php

declare(strict_types=1);

namespace App\Seo;

use App\Blog\Category;
use App\Blog\ListingQuery;
use App\Blog\Post;
use App\Blog\Sort;
use App\Core\Config;

/**
 * Заголовок, описание, canonical, хлебные крошки и JSON-LD для каждой страницы блога.
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

    public function category(Category $category, ListingQuery $query): Meta
    {
        $path = '/category/' . $category->slug;
        $canonical = $query->url($path);
        $breadcrumbs = [self::HOME, ['name' => $category->name, 'url' => $path]];

        return new Meta(
            title: $query->page > 1 ? "{$category->name}, страница {$query->page}" : $category->name,
            description: $category->description,
            canonical: $canonical,
            // Список по просмотрам - те же статьи в другом порядке: индексировать в нём нечего, а ссылки на статьи
            // пусть обходятся. canonical на список по дате не подходит: на одной и той же странице у них разные статьи
            robots: $query->sort === Sort::Views ? 'noindex, follow' : null,
            image: self::IMAGE,
            breadcrumbs: $breadcrumbs,
            schema: [
                [
                    '@type' => 'CollectionPage',
                    'name' => $category->name,
                    'description' => $category->description,
                    'url' => $this->url($canonical),
                ],
                $this->breadcrumbList($breadcrumbs),
            ],
        );
    }

    /**
     * @param list<Category> $categories категории статьи, первая - основная: она попадает в хлебные крошки
     */
    public function post(Post $post, array $categories): Meta
    {
        $path = '/post/' . $post->slug;
        $breadcrumbs = [self::HOME];
        if ($categories !== []) {
            $breadcrumbs[] = ['name' => $categories[0]->name, 'url' => '/category/' . $categories[0]->slug];
        }
        $breadcrumbs[] = ['name' => $post->title, 'url' => $path];
        // Авторов в блоге нет, статьи выпускает сам блог
        $publisher = ['@type' => 'Organization', 'name' => $this->config->siteName, 'url' => $this->url('/')];

        return new Meta(
            title: $post->title,
            description: $post->description,
            canonical: $path,
            ogType: 'article',
            image: $post->image,
            breadcrumbs: $breadcrumbs,
            schema: [
                [
                    '@type' => 'BlogPosting',
                    'headline' => $post->title,
                    'description' => $post->description,
                    'image' => $this->url($post->image),
                    'datePublished' => $post->publishedAt->format(DATE_ATOM),
                    'url' => $this->url($path),
                    'mainEntityOfPage' => $this->url($path),
                    'articleSection' => array_map(fn (Category $category): string => $category->name, $categories),
                    'author' => $publisher,
                    'publisher' => $publisher,
                ],
                $this->breadcrumbList($breadcrumbs),
            ],
        );
    }

    /**
     * Без canonical и соцсетей: страница 404 не должна попасть ни в поиск, ни в превью ссылок.
     */
    public function notFound(): Meta
    {
        return new Meta(title: 'Страница не найдена');
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
