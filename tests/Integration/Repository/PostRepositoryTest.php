<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Repository\PostRepository;
use App\Tests\DatabaseTestCase;

final class PostRepositoryTest extends DatabaseTestCase
{
    private PostRepository $posts;

    protected function setUp(): void
    {
        parent::setUp();
        $this->posts = new PostRepository($this->pdo);
    }

    public function testLatestByCategoryTakesNewestPostsOfEachCategory(): void
    {
        $tools = $this->insertCategory('tools');
        $this->insertCategory('empty');
        $agents = $this->insertCategory('agents');
        $this->insertPost('old', [$tools], '2026-10-01 10:00:00');
        $this->insertPost('newest', [$tools], '2026-10-05 10:00:00');
        $this->insertPost('same-time-first', [$tools], '2026-10-03 10:00:00');
        $this->insertPost('same-time-second', [$tools, $agents], '2026-10-03 10:00:00');
        $this->insertPost('agent', [$agents], '2026-09-01 10:00:00');

        $sections = $this->posts->latestByCategory(3);

        self::assertSame(['tools', 'agents'], array_column(array_column($sections, 'category'), 'slug'));
        // При одинаковой дате выше статья, добавленная позже
        self::assertSame(['newest', 'same-time-second', 'same-time-first'], array_column($sections[0]['posts'], 'slug'));
        self::assertSame(['same-time-second', 'agent'], array_column($sections[1]['posts'], 'slug'));
    }

    public function testLatestByCategoryReturnsCategoryAndCardFields(): void
    {
        $tools = $this->insertCategory('tools', 'Инструменты', 'Обзоры сервисов.');
        $postId = $this->insertPost('first', [$tools], '2026-10-01 10:00:00', views: 42, title: 'Первая');

        self::assertSame([[
            'category' => ['id' => $tools, 'slug' => 'tools', 'name' => 'Инструменты', 'description' => 'Обзоры сервисов.'],
            'posts' => [[
                'id' => $postId,
                'slug' => 'first',
                'title' => 'Первая',
                'description' => 'Описание статьи first.',
                'image' => '/uploads/first.webp',
                'views' => 42,
                'published_at' => '2026-10-01 10:00:00',
            ]],
        ]], $this->posts->latestByCategory(3));
    }

    public function testLatestByCategoryIsEmptyWithoutPosts(): void
    {
        $this->insertCategory('empty');

        self::assertSame([], $this->posts->latestByCategory(3));
    }
}
