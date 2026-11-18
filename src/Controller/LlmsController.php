<?php

declare(strict_types=1);

namespace App\Controller;

use App\Blog\LlmsTxt;
use App\Core\Response;
use App\Repository\PostRepository;

final class LlmsController
{
    // С запасом на все статьи нынешнего блога, но файл не разрастётся без предела:
    // остальные статьи доступны по ссылке на категорию
    private const POSTS_PER_CATEGORY = 50;

    public function __construct(
        private readonly LlmsTxt $llms,
        private readonly PostRepository $posts,
    ) {
    }

    public function index(): Response
    {
        return new Response(
            $this->llms->render($this->posts->latestByCategory(self::POSTS_PER_CATEGORY)),
            headers: ['Content-Type' => 'text/plain; charset=utf-8'],
        );
    }
}
