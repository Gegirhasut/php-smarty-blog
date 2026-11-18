<?php

declare(strict_types=1);

namespace App\Seo;

use App\Blog\Category;
use App\Blog\ListingQuery;
use App\Blog\Post;
use App\Blog\Sort;
use App\Blog\Urls;
use App\Core\Config;

final class PageMeta
{
    public function __construct(
        private readonly Config $config,
        private readonly Urls $urls,
    ) {
    }

    public function home(): Meta
    {
        return new Meta(
            title: 'Блог об ИИ',
            description: $this->config->siteDescription,
            canonical: $this->urls->home(),
            image: $this->config->siteImage,
            schema: [[
                '@type' => 'WebSite',
                'name' => $this->config->siteName,
                'url' => $this->urls->absolute($this->urls->home()),
                'description' => $this->config->siteDescription,
                'inLanguage' => 'ru',
            ]],
        );
    }

    public function category(Category $category, ListingQuery $query): Meta
    {
        $canonical = $this->urls->category($category->slug, $query);
        $breadcrumbs = [$this->homeCrumb(), ['name' => $category->name, 'url' => $this->urls->category($category->slug)]];

        return new Meta(
            title: $query->page > 1 ? "{$category->name}, страница {$query->page}" : $category->name,
            description: $category->description,
            canonical: $canonical,
            // Список по просмотрам - те же статьи в другом порядке: индексировать в нём нечего, а ссылки на статьи
            // пусть обходятся. canonical на список по дате не подходит: на одной и той же странице у них разные статьи
            robots: $query->sort === Sort::Views ? 'noindex, follow' : null,
            image: $this->config->siteImage,
            breadcrumbs: $breadcrumbs,
            schema: [
                [
                    '@type' => 'CollectionPage',
                    'name' => $category->name,
                    'description' => $category->description,
                    'url' => $this->urls->absolute($canonical),
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
        $url = $this->urls->post($post->slug);
        $breadcrumbs = [$this->homeCrumb()];
        if ($categories !== []) {
            $breadcrumbs[] = ['name' => $categories[0]->name, 'url' => $this->urls->category($categories[0]->slug)];
        }
        $breadcrumbs[] = ['name' => $post->title, 'url' => $url];
        // У статьи без обложки в соцсетях и JSON-LD - картинка сайта
        $image = $post->image ?? $this->config->siteImage;
        // Авторов в блоге нет, статьи выпускает сам блог
        $publisher = [
            '@type' => 'Organization',
            'name' => $this->config->siteName,
            'url' => $this->urls->absolute($this->urls->home()),
        ];

        return new Meta(
            title: $post->title,
            description: $post->description,
            canonical: $url,
            ogType: 'article',
            image: $image,
            breadcrumbs: $breadcrumbs,
            schema: [
                [
                    '@type' => 'BlogPosting',
                    'headline' => $post->title,
                    'description' => $post->description,
                    'image' => $this->urls->absolute($image),
                    'datePublished' => $post->publishedAt->format(DATE_ATOM),
                    'url' => $this->urls->absolute($url),
                    'mainEntityOfPage' => $this->urls->absolute($url),
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
     * @return array{name: string, url: string}
     */
    private function homeCrumb(): array
    {
        return ['name' => 'Главная', 'url' => $this->urls->home()];
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
                'item' => $this->urls->absolute($crumb['url']),
            ];
        }

        return ['@type' => 'BreadcrumbList', 'itemListElement' => $items];
    }
}
