<?php

declare(strict_types=1);

namespace App\Repository;

use App\Blog\Slug;
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
        // Строка не в формате slug не может быть в базе, а запрос с ней MySQL отклонил бы с ошибкой
        if (!Slug::isValid($slug)) {
            return null;
        }

        $statement = $this->pdo->prepare('SELECT id, slug, name, description FROM categories WHERE slug = ?');
        $statement->execute([$slug]);

        /** @var Category|false $category */
        $category = $statement->fetch();

        return $category === false ? null : $category;
    }

    /**
     * Категории статьи в том же порядке, что и в меню.
     *
     * @return list<CategoryLink>
     */
    public function forPost(int $postId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT c.id, c.slug, c.name FROM categories c
             JOIN category_post cp ON cp.category_id = c.id
             WHERE cp.post_id = ?
             ORDER BY cp.category_id',
        );
        $statement->execute([$postId]);

        /** @var list<CategoryLink> */
        return $statement->fetchAll();
    }

    /**
     * Названия категорий сразу для нескольких статей одним запросом.
     *
     * @param list<int> $postIds
     * @return array<int, list<string>> id статьи => названия её категорий в порядке меню
     */
    public function namesByPost(array $postIds): array
    {
        if ($postIds === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($postIds), '?'));
        $statement = $this->pdo->prepare(
            "SELECT cp.post_id, c.name FROM category_post cp
             JOIN categories c ON c.id = cp.category_id
             WHERE cp.post_id IN ({$placeholders})
             ORDER BY cp.post_id, cp.category_id",
        );
        $statement->execute($postIds);

        // FETCH_GROUP раскладывает строки по первой колонке: [post_id => [название, ...]]
        /** @var array<int, list<string>> */
        return $statement->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_COLUMN);
    }

    /**
     * Категории со статьями и дата самой свежей из них - для lastmod в sitemap.
     * slug можно выбирать при GROUP BY c.id: он зависит от первичного ключа.
     *
     * @return list<array{slug: string, updated_at: string}>
     */
    public function forSitemap(): array
    {
        $statement = $this->pdo->prepare(
            'SELECT c.slug, MAX(p.published_at) AS updated_at FROM categories c
             JOIN category_post cp ON cp.category_id = c.id
             JOIN posts p ON p.id = cp.post_id
             GROUP BY c.id
             ORDER BY c.id',
        );
        $statement->execute();

        /** @var list<array{slug: string, updated_at: string}> */
        return $statement->fetchAll();
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
