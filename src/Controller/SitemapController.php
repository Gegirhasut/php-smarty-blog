<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\NotFoundException;
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
        return $this->xml($this->sitemap->index($this->posts->newestPublishedAt(), $this->postFiles()));
    }

    public function categories(): Response
    {
        return $this->xml($this->sitemap->categories($this->posts->newestPublishedAt(), $this->categories->lastPostDates()));
    }

    public function posts(string $number): Response
    {
        // Номер только в обычной записи, как у страниц категории: «01» был бы дублем первого файла
        if (preg_match('/^[1-9][0-9]{0,8}$/D', $number) !== 1 || (int) $number > $this->postFiles()) {
            throw new NotFoundException();
        }

        $lastId = (int) $number * Sitemap::POSTS_PER_FILE;

        return $this->xml($this->sitemap->posts(
            $this->posts->publishedDatesBetweenIds($lastId - Sitemap::POSTS_PER_FILE + 1, $lastId),
        ));
    }

    /**
     * Файл статей - диапазон id: первый - с 1 по 1000, второй - с 1001 по 2000. Старые статьи не переезжают
     * между файлами, а число файлов известно по наибольшему id, без прохода по статьям.
     */
    private function postFiles(): int
    {
        return intdiv($this->posts->lastId() + Sitemap::POSTS_PER_FILE - 1, Sitemap::POSTS_PER_FILE);
    }

    private function xml(string $body): Response
    {
        return new Response($body, headers: ['Content-Type' => 'application/xml; charset=utf-8']);
    }
}
