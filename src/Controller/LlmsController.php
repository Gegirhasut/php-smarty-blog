<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Response;
use App\Repository\CategoryRepository;
use App\Repository\PostRepository;
use App\Seo\LlmsTxt;

final class LlmsController
{
    public function __construct(
        private readonly LlmsTxt $llms,
        private readonly CategoryRepository $categories,
        private readonly PostRepository $posts,
    ) {
    }

    public function index(): Response
    {
        return new Response(
            $this->llms->render($this->categories->withPosts(), $this->posts->byMainCategory()),
            headers: ['Content-Type' => 'text/plain; charset=utf-8'],
        );
    }
}
