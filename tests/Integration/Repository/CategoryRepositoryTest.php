<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Repository\CategoryRepository;
use App\Tests\DatabaseTestCase;

final class CategoryRepositoryTest extends DatabaseTestCase
{
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
}
