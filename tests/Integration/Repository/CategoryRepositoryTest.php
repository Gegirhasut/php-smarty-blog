<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Repository\CategoryRepository;
use App\Tests\DatabaseTestCase;

final class CategoryRepositoryTest extends DatabaseTestCase
{
    protected bool $rollBack = true;

    private CategoryRepository $categories;

    protected function setUp(): void
    {
        parent::setUp();
        $this->categories = new CategoryRepository($this->pdo);
    }

    public function testListsOnlyCategoriesWithPostsInIdOrder(): void
    {
        $tools = $this->insertCategory('tools', 'Инструменты');
        $this->insertCategory('empty', 'Пустая');
        $agents = $this->insertCategory('agents', 'Агенты');
        $this->insertPost('first', [$agents]);
        $this->insertPost('second', [$agents, $tools]);

        self::assertSame([
            ['id' => $tools, 'slug' => 'tools', 'name' => 'Инструменты'],
            ['id' => $agents, 'slug' => 'agents', 'name' => 'Агенты'],
        ], $this->categories->withPosts());
    }

    public function testFindsCategoryBySlug(): void
    {
        $id = $this->insertCategory('tools', 'Инструменты', 'Обзоры сервисов.');

        self::assertSame(
            ['id' => $id, 'slug' => 'tools', 'name' => 'Инструменты', 'description' => 'Обзоры сервисов.'],
            $this->categories->findBySlug('tools'),
        );
        self::assertNull($this->categories->findBySlug('Tools'));
        self::assertNull($this->categories->findBySlug('missing'));
    }

    public function testCategoryNamesForSeveralPosts(): void
    {
        $tools = $this->insertCategory('tools', 'Инструменты');
        $agents = $this->insertCategory('agents', 'Агенты');
        $first = $this->insertPost('first', [$agents, $tools]);
        $second = $this->insertPost('second', [$agents]);
        $other = $this->insertPost('other', [$tools]);

        self::assertSame(
            [$first => ['Инструменты', 'Агенты'], $second => ['Агенты']],
            $this->categories->namesByPost([$second, $first]),
        );
        self::assertArrayNotHasKey($other, $this->categories->namesByPost([$first]));
        self::assertSame([], $this->categories->namesByPost([]));
    }

    public function testSitemapDatesAreNewestPostOfCategory(): void
    {
        $tools = $this->insertCategory('tools');
        $this->insertCategory('empty');
        $agents = $this->insertCategory('agents');
        $this->insertPost('old', [$tools, $agents], '2026-09-01 10:00:00');
        $this->insertPost('new', [$tools], '2026-10-05 10:00:00');

        self::assertSame([
            ['slug' => 'tools', 'updated_at' => '2026-10-05 10:00:00'],
            ['slug' => 'agents', 'updated_at' => '2026-09-01 10:00:00'],
        ], $this->categories->forSitemap());
    }

    public function testListsCategoriesOfPostInIdOrder(): void
    {
        $tools = $this->insertCategory('tools', 'Инструменты');
        $this->insertCategory('other', 'Другая');
        $agents = $this->insertCategory('agents', 'Агенты');
        $post = $this->insertPost('post', [$agents, $tools]);

        self::assertSame([
            ['id' => $tools, 'slug' => 'tools', 'name' => 'Инструменты'],
            ['id' => $agents, 'slug' => 'agents', 'name' => 'Агенты'],
        ], $this->categories->forPost($post));
    }
}
