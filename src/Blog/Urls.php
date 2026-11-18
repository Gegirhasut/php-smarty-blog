<?php

declare(strict_types=1);

namespace App\Blog;

/**
 * Адреса страниц блога в одном месте. Пути относительные - для ссылок на сайте этого достаточно,
 * а absolute() дописывает адрес сайта из APP_URL: он нужен в canonical, sitemap, RSS и llms.txt.
 */
final class Urls
{
    public function __construct(private readonly string $siteUrl)
    {
    }

    public function home(): string
    {
        return '/';
    }

    public function category(string $slug, ListingQuery $query = new ListingQuery()): string
    {
        $params = http_build_query($query->params());

        return '/category/' . $slug . ($params === '' ? '' : '?' . $params);
    }

    public function post(string $slug): string
    {
        return '/post/' . $slug;
    }

    public function feed(): string
    {
        return '/feed.xml';
    }

    public function sitemap(): string
    {
        return '/sitemap.xml';
    }

    public function absolute(string $path): string
    {
        return $this->siteUrl . $path;
    }
}
