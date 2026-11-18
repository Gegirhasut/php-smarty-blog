<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Response;
use App\Core\View;
use App\Repository\CategoryRepository;
use App\Repository\PostRepository;
use App\Seo\PageMeta;

final class HomeController
{
    private const POSTS_PER_CATEGORY = 3;

    public function __construct(
        private readonly View $view,
        private readonly PageMeta $meta,
        private readonly CategoryRepository $categories,
        private readonly PostRepository $posts,
    ) {
    }

    public function index(): Response
    {
        return new Response($this->view->render('home.tpl', [
            'meta' => $this->meta->home(),
            'categories' => $this->categories->withPosts(),
            'sections' => $this->posts->latestByCategory(self::POSTS_PER_CATEGORY),
        ]));
    }
}
