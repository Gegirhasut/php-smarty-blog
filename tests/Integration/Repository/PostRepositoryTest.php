<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Blog\Sort;
use App\Repository\PostRepository;
use App\Tests\DatabaseTestCase;
use PDO;

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

    public function testCountsPostsOfCategory(): void
    {
        $tools = $this->insertCategory('tools');
        $agents = $this->insertCategory('agents');
        $empty = $this->insertCategory('empty');
        $this->insertPost('a', [$tools, $agents]);
        $this->insertPost('b', [$tools]);

        self::assertSame(2, $this->posts->countInCategory($tools));
        self::assertSame(1, $this->posts->countInCategory($agents));
        self::assertSame(0, $this->posts->countInCategory($empty));
    }

    public function testSortsCategoryByDateThenId(): void
    {
        $tools = $this->insertSortingFixture();

        self::assertSame(
            ['newest', 'tie-second', 'tie-first', 'oldest'],
            array_column($this->posts->inCategory($tools, Sort::Date, 10, 0), 'slug'),
        );
    }

    public function testSortsCategoryByViewsThenId(): void
    {
        $tools = $this->insertSortingFixture();

        self::assertSame(
            ['oldest', 'tie-second', 'tie-first', 'newest'],
            array_column($this->posts->inCategory($tools, Sort::Views, 10, 0), 'slug'),
        );
    }

    public function testPaginatesCategory(): void
    {
        $tools = $this->insertSortingFixture();

        self::assertSame(['newest', 'tie-second'], array_column($this->posts->inCategory($tools, Sort::Date, 2, 0), 'slug'));
        self::assertSame(['tie-first', 'oldest'], array_column($this->posts->inCategory($tools, Sort::Date, 2, 2), 'slug'));
        self::assertSame([], $this->posts->inCategory($tools, Sort::Date, 2, 4));
    }

    public function testFindsPostBySlug(): void
    {
        $tools = $this->insertCategory('tools');
        $id = $this->insertPost('first', [$tools], '2026-10-01 10:00:00', views: 5, title: 'Первая', content: "Текст.\n\n## Раздел");

        self::assertSame([
            'id' => $id,
            'slug' => 'first',
            'title' => 'Первая',
            'description' => 'Описание статьи first.',
            'image' => '/uploads/first.webp',
            'views' => 5,
            'published_at' => '2026-10-01 10:00:00',
            'content' => "Текст.\n\n## Раздел",
        ], $this->posts->findBySlug('first'));
        self::assertNull($this->posts->findBySlug('First'));
        self::assertNull($this->posts->findBySlug('missing'));
    }

    public function testIncrementsViewsOfOnePost(): void
    {
        $tools = $this->insertCategory('tools');
        $first = $this->insertPost('first', [$tools], views: 5);
        $this->insertPost('second', [$tools], views: 5);

        $this->posts->incrementViews($first);
        $this->posts->incrementViews($first);

        self::assertSame(
            ['first' => 7, 'second' => 5],
            $this->select('SELECT slug, views FROM posts ORDER BY id')->fetchAll(PDO::FETCH_KEY_PAIR),
        );
    }

    public function testSimilarPostsShareMostCategoriesThenAreNewer(): void
    {
        $tools = $this->insertCategory('tools');
        $agents = $this->insertCategory('agents');
        $money = $this->insertCategory('money');
        $post = $this->insertPost('post', [$tools, $agents], '2026-10-01 10:00:00');
        $this->insertPost('both-old', [$tools, $agents], '2026-09-01 10:00:00');
        $this->insertPost('one-new', [$agents, $money], '2026-10-05 10:00:00');
        $this->insertPost('one-tie-first', [$tools], '2026-10-03 10:00:00');
        $this->insertPost('one-tie-second', [$tools], '2026-10-03 10:00:00');
        $this->insertPost('unrelated', [$money], '2026-10-10 10:00:00');

        self::assertSame(
            ['both-old', 'one-new', 'one-tie-second'],
            array_column($this->posts->similar($post, 3), 'slug'),
        );
        self::assertSame(
            ['both-old', 'one-new', 'one-tie-second', 'one-tie-first'],
            array_column($this->posts->similar($post, 10), 'slug'),
        );
    }

    public function testPostWithoutNeighboursHasNoSimilar(): void
    {
        $post = $this->insertPost('alone', [$this->insertCategory('tools')]);

        self::assertSame([], $this->posts->similar($post, 3));
    }

    /**
     * Четыре статьи в категории, у двух одинаковые дата и просмотры, плюс статья из другой категории.
     */
    private function insertSortingFixture(): int
    {
        $tools = $this->insertCategory('tools');
        $other = $this->insertCategory('other');
        $this->insertPost('oldest', [$tools], '2026-10-01 10:00:00', views: 900);
        $this->insertPost('tie-first', [$tools], '2026-10-02 10:00:00', views: 500);
        $this->insertPost('tie-second', [$tools], '2026-10-02 10:00:00', views: 500);
        $this->insertPost('newest', [$tools], '2026-10-03 10:00:00', views: 100);
        $this->insertPost('elsewhere', [$other], '2026-10-04 10:00:00', views: 1000);

        return $tools;
    }
}
