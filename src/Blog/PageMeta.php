<?php

declare(strict_types=1);

namespace App\Blog;

use App\Core\Meta;

/**
 * Заголовок, описание, canonical и JSON-LD для каждой страницы блога.
 */
final class PageMeta
{
    private const DESCRIPTION = 'Блог об ИИ: новые модели и инструменты, агенты, исследования, деньги и люди.';
    // Картинка для соцсетей у страниц без своей обложки
    private const IMAGE = '/og.png';

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

    private function url(string $path): string
    {
        return $this->siteUrl . $path;
    }
}
