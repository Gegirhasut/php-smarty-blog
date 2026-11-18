<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

final class CategoryRepository
{
    public function __construct(private readonly PDO $pdo)
    {
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
