<?php

declare(strict_types=1);

namespace App\Controller;

use App\Blog\Sitemap;
use App\Core\Response;
use App\Repository\CategoryRepository;
use App\Repository\PostRepository;

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
            $this->sitemap->render($this->categories->forSitemap(), $this->posts->forSitemap()),
            headers: ['Content-Type' => 'application/xml; charset=utf-8'],
        );
    }
}
