<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Blog\Category;
use App\Repository\CategoryRepository;
use App\Tests\DatabaseTestCase;
use DateTimeImmutable;

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

        self::assertEquals([
            new Category($tools, 'tools', 'Инструменты', 'Описание категории tools.'),
            new Category($agents, 'agents', 'Агенты', 'Описание категории agents.'),
        ], $this->categories->withPosts());
    }

    public function testFindsCategoryBySlug(): void
    {
        $id = $this->insertCategory('tools', 'Инструменты', 'Обзоры сервисов.');

        self::assertEquals(new Category($id, 'tools', 'Инструменты', 'Обзоры сервисов.'), $this->categories->findBySlug('tools'));
        self::assertNull($this->categories->findBySlug('Tools'));
        self::assertNull($this->categories->findBySlug('missing'));
        // Без проверки формата MySQL ответил бы ошибкой: колонка slug в ascii
        self::assertNull($this->categories->findBySlug('привет'));
    }

    public function testCategoryNamesForSeveralPosts(): void
    {
        $tools = $this->insertCategory('tools', 'Инструменты');
        $agents = $this->insertCategory('agents', 'Агенты');
        $first = $this->insertPost('first', [$agents, $tools]);
        $second = $this->insertPost('second', [$agents]);
        $other = $this->insertPost('other', [$tools]);

        self::assertSame(
            [$first => ['Агенты', 'Инструменты'], $second => ['Агенты']],
            $this->categories->namesByPost([$second, $first]),
        );
        self::assertArrayNotHasKey($other, $this->categories->namesByPost([$first]));
        self::assertSame([], $this->categories->namesByPost([]));
    }

    public function testLastPostDateOfEachCategory(): void
    {
        $tools = $this->insertCategory('tools');
        $this->insertCategory('empty');
        $agents = $this->insertCategory('agents');
        $this->insertPost('old', [$tools, $agents], '2026-09-01 10:00:00');
        $this->insertPost('new', [$tools], '2026-10-05 10:00:00');

        self::assertSame(
            ['tools' => '2026-10-05T10:00:00+00:00', 'agents' => '2026-09-01T10:00:00+00:00'],
            array_map(fn (DateTimeImmutable $date): string => $date->format(DATE_ATOM), $this->categories->lastPostDates()),
        );
    }

    public function testListsCategoriesOfPostMainFirst(): void
    {
        $tools = $this->insertCategory('tools', 'Инструменты');
        $this->insertCategory('other', 'Другая');
        $agents = $this->insertCategory('agents', 'Агенты');
        // Основная категория создана позже другой, и порядок по id поставил бы её второй
        $post = $this->insertPost('post', [$agents, $tools]);

        self::assertSame(['agents', 'tools'], array_column($this->categories->forPost($post), 'slug'));
    }
}
