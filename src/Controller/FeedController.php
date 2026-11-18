<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Response;
use App\Repository\CategoryRepository;
use App\Repository\PostRepository;
use App\Seo\RssFeed;

final class FeedController
{
    private const POSTS = 20;

    public function __construct(
        private readonly RssFeed $feed,
        private readonly CategoryRepository $categories,
        private readonly PostRepository $posts,
    ) {
    }

    public function index(): Response
    {
        $posts = $this->posts->latest(self::POSTS);

        return new Response(
            $this->feed->render($posts, $this->categories->namesByPost(array_column($posts, 'id'))),
            headers: ['Content-Type' => 'application/rss+xml; charset=utf-8'],
        );
    }
}
