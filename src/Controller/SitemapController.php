<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Response;
use App\Repository\CategoryRepository;
use App\Repository\PostRepository;
use App\Seo\Sitemap;

final class SitemapController
{
    public function __construct(
        private readonly Sitemap $sitemap,
        private readonly CategoryRepository $categories,
        private readonly PostRepository $posts,
    ) {
    }

    public function index(): Response
    {
        return new Response(
            $this->sitemap->render($this->categories->lastPostDates(), $this->posts->publishedDates()),
            headers: ['Content-Type' => 'application/xml; charset=utf-8'],
        );
    }
}
