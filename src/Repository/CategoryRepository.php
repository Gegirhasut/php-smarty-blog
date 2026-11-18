<?php

declare(strict_types=1);

namespace App\Repository;

use App\Blog\Category;
use App\Blog\Slug;
use App\Database\Row;
use DateTimeImmutable;
use PDO;

final class CategoryRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Категории, в которых есть статьи, для меню. Порядок задаёт categories.php: сидер вставляет категории
     * по порядку, поэтому сортировка по id. Пустые категории не показываем, как и на главной.
     *
     * @return list<Category>
     */
    public function withPosts(): array
    {
        $statement = $this->pdo->prepare(
            'SELECT c.id, c.slug, c.name, c.description FROM categories c
             WHERE EXISTS (SELECT 1 FROM category_post cp WHERE cp.category_id = c.id)
             ORDER BY c.id',
        );
        $statement->execute();

        return array_map($this->category(...), Row::all($statement));
    }

    public function findBySlug(string $slug): ?Category
    {
        // Строка не в формате slug не может быть в базе, а запрос с ней MySQL отклонил бы с ошибкой
        if (!Slug::isValid($slug)) {
            return null;
        }

        $statement = $this->pdo->prepare('SELECT id, slug, name, description FROM categories WHERE slug = ?');
        $statement->execute([$slug]);
        $row = Row::first($statement);

        return $row === null ? null : $this->category($row);
    }

    /**
     * Категории статьи, первой - основная.
     *
     * @return list<Category>
     */
    public function forPost(int $postId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT c.id, c.slug, c.name, c.description FROM categories c
             JOIN category_post cp ON cp.category_id = c.id
             WHERE cp.post_id = ?
             ORDER BY cp.position',
        );
        $statement->execute([$postId]);

        return array_map($this->category(...), Row::all($statement));
    }

    /**
     * Названия категорий сразу для нескольких статей одним запросом.
     *
     * @param list<int> $postIds
     * @return array<int, list<string>> id статьи => названия её категорий, первой - основная
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
             ORDER BY cp.post_id, cp.position",
        );
        $statement->execute($postIds);

        $names = [];
        foreach (Row::all($statement) as $row) {
            $names[$row->int('post_id')][] = $row->string('name');
        }

        return $names;
    }

    /**
     * Дата самой свежей статьи каждой категории, в которой есть статьи.
     * slug можно выбирать при GROUP BY c.id: он зависит от первичного ключа.
     *
     * @return array<string, DateTimeImmutable> slug категории => дата
     */
    public function lastPostDates(): array
    {
        $statement = $this->pdo->prepare(
            'SELECT c.slug, MAX(p.published_at) AS published_at FROM categories c
             JOIN category_post cp ON cp.category_id = c.id
             JOIN posts p ON p.id = cp.post_id
             GROUP BY c.id
             ORDER BY c.id',
        );
        $statement->execute();

        $dates = [];
        foreach (Row::all($statement) as $row) {
            $dates[$row->string('slug')] = $row->date('published_at');
        }

        return $dates;
    }

    public function count(): int
    {
        $statement = $this->pdo->prepare('SELECT COUNT(*) AS total FROM categories');
        $statement->execute();

        return Row::first($statement)?->int('total') ?? 0;
    }

    /**
     * @return array<string, int>
     */
    public function idsBySlug(): array
    {
        $statement = $this->pdo->prepare('SELECT slug, id FROM categories');
        $statement->execute();

        $ids = [];
        foreach (Row::all($statement) as $row) {
            $ids[$row->string('slug')] = $row->int('id');
        }

        return $ids;
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

    private function category(Row $row): Category
    {
        return new Category($row->int('id'), $row->string('slug'), $row->string('name'), $row->string('description'));
    }
}
