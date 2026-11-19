<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Response;
use App\Repository\PostRepository;
use App\Seo\LlmsTxt;

final class LlmsController
{
    // Свежие статьи каждой категории, остальные - по ссылкам на категорию и sitemap. Полный список на 100 000
    // статей весил бы десятки мегабайт, а файл должен давать короткий контекст
    private const POSTS_PER_CATEGORY = 20;

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
