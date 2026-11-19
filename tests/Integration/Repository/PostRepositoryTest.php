<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Blog\Category;
use App\Blog\Post;
use App\Blog\PostCard;
use App\Blog\Sort;
use App\Repository\PostRepository;
use App\Tests\DatabaseTestCase;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use PDO;

final class PostRepositoryTest extends DatabaseTestCase
{
    protected bool $rollBack = true;

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

        self::assertEquals([[
            'category' => new Category($tools, 'tools', 'Инструменты', 'Обзоры сервисов.'),
            'posts' => [new PostCard(
                id: $postId,
                slug: 'first',
                title: 'Первая',
                description: 'Описание статьи first.',
                image: '/uploads/first.webp',
                views: 42,
                publishedAt: new DateTimeImmutable('2026-10-01 10:00:00', new DateTimeZone('UTC')),
                mainCategorySlug: 'tools',
            )],
        ]], $this->posts->latestByCategory(3));
    }

    public function testCardKnowsMainCategoryAndMayHaveNoCover(): void
    {
        $tools = $this->insertCategory('tools');
        $agents = $this->insertCategory('agents');
        $this->insertPost('bare', [$agents, $tools], cover: false);

        $card = $this->posts->inCategory($tools, Sort::Date, 9, 0)[0];

        self::assertNull($card->image);
        // В списке категории tools основная категория статьи всё равно agents
        self::assertSame('agents', $card->mainCategorySlug);
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

        self::assertEquals(new Post(
            id: $id,
            slug: 'first',
            title: 'Первая',
            description: 'Описание статьи first.',
            image: '/uploads/first.webp',
            views: 5,
            publishedAt: new DateTimeImmutable('2026-10-01 10:00:00', new DateTimeZone('UTC')),
            content: "Текст.\n\n## Раздел",
        ), $this->posts->findBySlug('first'));
        self::assertNull($this->posts->findBySlug('First'));
        self::assertNull($this->posts->findBySlug('missing'));
        self::assertNull($this->posts->findBySlug('привет'));
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

    public function testViewsInCategoryLinksMatchPost(): void
    {
        $tools = $this->insertCategory('tools');
        $agents = $this->insertCategory('agents');
        $money = $this->insertCategory('money');
        $post = $this->insertPost('post', [$tools, $agents, $money], views: 10);
        $other = $this->insertPost('other', [$tools], views: 10);

        $this->posts->incrementViews($post);
        $this->posts->incrementViews($post);

        // Статья в трёх категориях: и сама она, и каждая её связь получают +1 за просмотр, а не +3
        self::assertSame(12, $this->select("SELECT views FROM posts WHERE slug = 'post'")->fetchColumn());
        self::assertSame(
            [$tools => 12, $agents => 12, $money => 12],
            $this->select("SELECT category_id, views FROM category_post WHERE post_id = {$post} ORDER BY category_id")
                ->fetchAll(PDO::FETCH_KEY_PAIR),
        );
        self::assertSame(10, $this->select("SELECT views FROM category_post WHERE post_id = {$other}")->fetchColumn());
    }

    public function testCountsViewsOfPostWithoutCategories(): void
    {
        $post = $this->insertPost('alone', [], views: 3);

        $this->posts->incrementViews($post);

        self::assertSame(4, $this->select("SELECT views FROM posts WHERE id = {$post}")->fetchColumn());
    }

    public function testCategorySortedByViewsFollowsNewViews(): void
    {
        $tools = $this->insertCategory('tools');
        $this->insertPost('popular', [$tools], views: 2);
        $rising = $this->insertPost('rising', [$tools], views: 1);

        $this->posts->incrementViews($rising);
        $this->posts->incrementViews($rising);

        $cards = $this->posts->inCategory($tools, Sort::Views, 10, 0);

        self::assertSame(['rising', 'popular'], array_column($cards, 'slug'));
        self::assertSame([3, 2], array_column($cards, 'views'));
    }

    public function testSimilarPostsShareMostCategoriesThenAreClosestInTime(): void
    {
        $tools = $this->insertCategory('tools');
        $agents = $this->insertCategory('agents');
        $money = $this->insertCategory('money');
        $post = $this->insertPost('post', [$tools, $agents], '2026-10-01 10:00:00');
        $this->insertPost('both-old', [$tools, $agents], '2026-09-01 10:00:00');
        $this->insertPost('one-next-day', [$agents, $money], '2026-10-02 10:00:00');
        $this->insertPost('one-tie-first', [$tools], '2026-10-03 10:00:00');
        $this->insertPost('one-tie-second', [$tools], '2026-10-03 10:00:00');
        // Так же далеко от статьи, как пара выше, но раньше неё: из равных по расстоянию первой идёт более новая
        $this->insertPost('one-before', [$tools], '2026-09-29 10:00:00');
        $this->insertPost('unrelated', [$money], '2026-10-10 10:00:00');

        self::assertSame(
            ['both-old', 'one-next-day', 'one-tie-second'],
            array_column($this->posts->similar($post, 3), 'slug'),
        );
        // Статья без общих категорий - только добором, после всех соседей по категориям
        self::assertSame(
            ['both-old', 'one-next-day', 'one-tie-second', 'one-tie-first', 'one-before', 'unrelated'],
            array_column($this->posts->similar($post, 10), 'slug'),
        );
    }

    public function testSimilarPostsAreChosenAmongNearestPostsOfEachCategory(): void
    {
        $tools = $this->insertCategory('tools');
        $agents = $this->insertCategory('agents');
        $post = $this->insertPost('post', [$tools, $agents], '2026-10-01 10:00:00');
        // Две общие категории, но в tools между ним и статьёй 25 других: там он не кандидат, общая остаётся одна
        $this->insertPost('both-oldest', [$tools, $agents], '2026-01-01 10:00:00');
        for ($i = 1; $i <= 25; $i++) {
            $this->insertPost("tool-{$i}", [$tools], '2026-02-01 10:00:00');
        }

        self::assertSame(
            ['tool-25', 'tool-24', 'tool-23'],
            array_column($this->posts->similar($post, 3), 'slug'),
        );
    }

    public function testSimilarPostsOfBigCategoryDependOnPost(): void
    {
        $tools = $this->insertCategory('tools');
        // Больше 50 статей в одной категории: последние 50 у всех статей были бы одни и те же
        $start = new DateTimeImmutable('2026-10-01 00:00:00');
        $ids = [];
        for ($hour = 1; $hour <= 60; $hour++) {
            $publishedAt = $start->add(new DateInterval("PT{$hour}H"))->format('Y-m-d H:i:s');
            $ids[$hour] = $this->insertPost("tool-{$hour}", [$tools], $publishedAt);
        }

        // Ближайшие по дате с обеих сторон, из равных по расстоянию - более новая
        self::assertSame(['tool-11', 'tool-9', 'tool-12'], array_column($this->posts->similar($ids[10], 3), 'slug'));
        self::assertSame(['tool-51', 'tool-49', 'tool-52'], array_column($this->posts->similar($ids[50], 3), 'slug'));
        self::assertSame(['tool-59', 'tool-58', 'tool-57'], array_column($this->posts->similar($ids[60], 3), 'slug'));
    }

    public function testSimilarPostsIncludeNeighboursPublishedAtSameTime(): void
    {
        $tools = $this->insertCategory('tools');
        $this->insertPost('same-time-before', [$tools], '2026-10-01 10:00:00');
        $post = $this->insertPost('post', [$tools], '2026-10-01 10:00:00');
        $this->insertPost('same-time-after', [$tools], '2026-10-01 10:00:00');
        // Новее всех, но без общей категории: попала бы в блок, только если соседи с той же датой потерялись
        $this->insertPost('unrelated', [$this->insertCategory('money')], '2026-10-05 10:00:00');

        self::assertSame(
            ['same-time-after', 'same-time-before'],
            array_column($this->posts->similar($post, 2), 'slug'),
        );
    }

    public function testSimilarPostsAreToppedUpWithNewestOthers(): void
    {
        $tools = $this->insertCategory('tools');
        $money = $this->insertCategory('money');
        $this->insertPost('newer-neighbour', [$tools], '2026-10-09 10:00:00');
        $post = $this->insertPost('post', [$tools], '2026-10-08 10:00:00');
        $this->insertPost('newest', [$money], '2026-10-07 10:00:00');
        $this->insertPost('newer', [$money], '2026-10-06 10:00:00');
        $this->insertPost('old', [$money], '2026-09-01 10:00:00');

        // Сосед по категории новее всех, но в доборе не повторяется; сама статья в блок не попадает
        self::assertSame(
            ['newer-neighbour', 'newest', 'newer'],
            array_column($this->posts->similar($post, 3), 'slug'),
        );
    }

    public function testPostWithoutNeighboursStillGetsThreeSimilar(): void
    {
        $tools = $this->insertCategory('tools');
        $money = $this->insertCategory('money');
        $post = $this->insertPost('alone', [$tools], '2026-10-10 10:00:00');
        foreach (['a', 'b', 'c', 'd'] as $day => $slug) {
            $this->insertPost($slug, [$money], '2026-10-0' . ($day + 1) . ' 10:00:00');
        }

        self::assertSame(['d', 'c', 'b'], array_column($this->posts->similar($post, 3), 'slug'));
    }

    public function testMainCategoryIsTheOneWithLowestPosition(): void
    {
        $tools = $this->insertCategory('tools');
        $agents = $this->insertCategory('agents');
        $money = $this->insertCategory('money');
        $post = $this->insertPost('post', [$agents, $money, $tools]);
        // Основная категория удалена: позиции оставшихся начинаются не с нуля
        $this->pdo->prepare('DELETE FROM category_post WHERE post_id = ? AND position = 0')->execute([$post]);

        self::assertSame('money', $this->posts->inCategory($tools, Sort::Date, 9, 0)[0]->mainCategorySlug);
    }

    public function testLatestByCategoryFindsOldPostsOfSmallCategory(): void
    {
        $tools = $this->insertCategory('tools');
        $rare = $this->insertCategory('rare');
        $this->insertPost('rare-old', [$rare, $tools], '2026-01-01 10:00:00');
        for ($day = 1; $day <= 9; $day++) {
            $this->insertPost("tool-{$day}", [$tools], "2026-10-0{$day} 10:00:00");
        }

        $sections = $this->posts->latestByCategory(3);

        self::assertSame(['tool-9', 'tool-8', 'tool-7'], array_column($sections[0]['posts'], 'slug'));
        // У маленькой категории её статья найдена, хотя в общей ленте она старше всех
        self::assertSame(['rare-old'], array_column($sections[1]['posts'], 'slug'));
    }

    public function testLatestPostsAcrossCategories(): void
    {
        $tools = $this->insertCategory('tools');
        $agents = $this->insertCategory('agents');
        $this->insertPost('old', [$tools], '2026-09-01 10:00:00');
        $this->insertPost('new', [$agents], '2026-10-05 10:00:00');
        $this->insertPost('middle', [$tools, $agents], '2026-10-01 10:00:00');

        self::assertSame(['new', 'middle'], array_column($this->posts->latest(2), 'slug'));
    }

    public function testNoNewestPublishedAtWithoutPosts(): void
    {
        self::assertNull($this->posts->newestPublishedAt());
    }

    public function testNewestPublishedAt(): void
    {
        $tools = $this->insertCategory('tools');
        $this->insertPost('new', [$tools], '2026-10-05 10:00:00');
        $this->insertPost('old', [$tools], '2026-09-01 10:00:00');

        self::assertSame('2026-10-05T10:00:00+00:00', $this->posts->newestPublishedAt()?->format(DATE_ATOM));
    }

    public function testLastId(): void
    {
        self::assertSame(0, $this->posts->lastId());

        $tools = $this->insertCategory('tools');
        $this->insertPost('first', [$tools], '2026-10-05 10:00:00');
        $last = $this->insertPost('last', [$tools], '2026-09-01 10:00:00');

        self::assertSame($last, $this->posts->lastId());
    }

    public function testPublishedDatesBetweenIds(): void
    {
        $tools = $this->insertCategory('tools');
        $first = $this->insertPost('first', [$tools], '2026-10-05 10:00:00');
        $second = $this->insertPost('second', [$tools], '2026-09-01 10:00:00');
        $this->insertPost('third', [$tools], '2026-10-07 10:00:00');

        // По возрастанию id, а не по дате; третья вне диапазона
        self::assertSame(
            ['first' => '2026-10-05T10:00:00+00:00', 'second' => '2026-09-01T10:00:00+00:00'],
            array_map(fn (DateTimeImmutable $date): string => $date->format(DATE_ATOM), $this->posts->publishedDatesBetweenIds($first, $second)),
        );
        self::assertSame([], $this->posts->publishedDatesBetweenIds($first + 100, $first + 200));
    }

    public function testSimilarPostsAreFewerOnlyInTinyBlog(): void
    {
        $post = $this->insertPost('post', [$this->insertCategory('tools')]);
        $this->insertPost('other', [$this->insertCategory('money')]);

        self::assertSame(['other'], array_column($this->posts->similar($post, 3), 'slug'));
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
