<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

/**
 * @phpstan-type CategoryLink array{id: int, slug: string, name: string}
 * @phpstan-type Category array{id: int, slug: string, name: string, description: string}
 */
final class CategoryRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Категории, в которых есть статьи, для меню. Порядок задаёт categories.php: сидер вставляет категории
     * по порядку, поэтому сортировка по id. Пустые категории не показываем, как и на главной.
     *
     * @return list<CategoryLink>
     */
    public function withPosts(): array
    {
        $statement = $this->pdo->prepare(
            'SELECT c.id, c.slug, c.name FROM categories c
             WHERE EXISTS (SELECT 1 FROM category_post cp WHERE cp.category_id = c.id)
             ORDER BY c.id',
        );
        $statement->execute();

        /** @var list<CategoryLink> */
        return $statement->fetchAll();
    }

    /**
     * @return Category|null
     */
    public function findBySlug(string $slug): ?array
    {
        $statement = $this->pdo->prepare('SELECT id, slug, name, description FROM categories WHERE slug = ?');
        $statement->execute([$slug]);

        /** @var Category|false $category */
        $category = $statement->fetch();

        return $category === false ? null : $category;
    }

    public function count(): int
    {
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM categories');
        $statement->execute();

        /** @var int */
        return $statement->fetchColumn();
    }

    /**
     * @return array<string, int>
     */
    public function idsBySlug(): array
    {
        $statement = $this->pdo->prepare('SELECT slug, id FROM categories');
        $statement->execute();

        /** @var array<string, int> */
        return $statement->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    /**
     * @param list<array{slug: string, name: string, description: string}> $categories
     */
    public function insertMany(array $categories): void
    {
        if ($categories === []) {
            return;
        }

        $params = [];
        foreach ($categories as $category) {
            array_push($params, $category['name'], $category['slug'], $category['description']);
        }

        $rows = implode(', ', array_fill(0, count($categories), '(?, ?, ?)'));
        $this->pdo->prepare("INSERT INTO categories (name, slug, description) VALUES {$rows}")->execute($params);
    }
}
