<?php

declare(strict_types=1);

namespace App\Repository;

use App\Blog\Sort;
use PDO;

/**
 * @phpstan-import-type Category from CategoryRepository
 * @phpstan-type PostCard array{
 *     id: int,
 *     slug: string,
 *     title: string,
 *     description: string,
 *     image: string,
 *     views: int,
 *     published_at: string
 * }
 */
final class PostRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Последние статьи каждой категории, в которой они есть, одним запросом: ROW_NUMBER() нумерует статьи
     * внутри категории от новых к старым, и из каждой берутся первые $perCategory.
     * Категории без статей отсекает JOIN, порядок категорий - по id, как в меню.
     *
     * @return list<array{category: Category, posts: list<PostCard>}>
     */
    public function latestByCategory(int $perCategory): array
    {
        $statement = $this->pdo->prepare(
            'SELECT category_id, category_slug, category_name, category_description,
                    id, slug, title, description, image, views, published_at
             FROM (
                 SELECT c.id AS category_id, c.slug AS category_slug, c.name AS category_name,
                        c.description AS category_description,
                        p.id, p.slug, p.title, p.description, p.image, p.views, p.published_at,
                        ROW_NUMBER() OVER (PARTITION BY c.id ORDER BY p.published_at DESC, p.id DESC) AS position
                 FROM categories c
                 JOIN category_post cp ON cp.category_id = c.id
                 JOIN posts p ON p.id = cp.post_id
             ) ranked
             WHERE position <= ?
             ORDER BY category_id, position',
        );
        $statement->execute([$perCategory]);

        /**
         * @var list<array{
         *     category_id: int,
         *     category_slug: string,
         *     category_name: string,
         *     category_description: string,
         *     id: int,
         *     slug: string,
         *     title: string,
         *     description: string,
         *     image: string,
         *     views: int,
         *     published_at: string
         * }> $rows
         */
        $rows = $statement->fetchAll();

        $sections = [];
        foreach ($rows as $row) {
            $sections[$row['category_id']] ??= [
                'category' => [
                    'id' => $row['category_id'],
                    'slug' => $row['category_slug'],
                    'name' => $row['category_name'],
                    'description' => $row['category_description'],
                ],
                'posts' => [],
            ];
            $sections[$row['category_id']]['posts'][] = [
                'id' => $row['id'],
                'slug' => $row['slug'],
                'title' => $row['title'],
                'description' => $row['description'],
                'image' => $row['image'],
                'views' => $row['views'],
                'published_at' => $row['published_at'],
            ];
        }

        return array_values($sections);
    }

    public function countInCategory(int $categoryId): int
    {
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM category_post WHERE category_id = ?');
        $statement->execute([$categoryId]);

        /** @var int */
        return $statement->fetchColumn();
    }

    /**
     * Страница статей категории. При равных датах или просмотрах порядок задаёт id,
     * иначе такие статьи могли бы переезжать между страницами от запроса к запросу.
     *
     * @return list<PostCard>
     */
    public function inCategory(int $categoryId, Sort $sort, int $limit, int $offset): array
    {
        // ORDER BY не принимает параметры запроса, поэтому сортировка выбирается из готовых строк
        $order = match ($sort) {
            Sort::Date => 'p.published_at DESC, p.id DESC',
            Sort::Views => 'p.views DESC, p.id DESC',
        };

        $statement = $this->pdo->prepare(
            "SELECT p.id, p.slug, p.title, p.description, p.image, p.views, p.published_at
             FROM posts p
             JOIN category_post cp ON cp.post_id = p.id
             WHERE cp.category_id = ?
             ORDER BY {$order}
             LIMIT ? OFFSET ?",
        );
        $statement->execute([$categoryId, $limit, $offset]);

        /** @var list<PostCard> */
        return $statement->fetchAll();
    }

    /**
     * @return array<string, int>
     */
    public function idsBySlug(): array
    {
        $statement = $this->pdo->prepare('SELECT slug, id FROM posts');
        $statement->execute();

        /** @var array<string, int> */
        return $statement->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    /**
     * @param list<array{
     *     title: string,
     *     slug: string,
     *     image: string,
     *     description: string,
     *     content: string,
     *     views: int,
     *     published_at: string
     * }> $posts
     */
    public function insertMany(array $posts): void
    {
        if ($posts === []) {
            return;
        }

        $params = [];
        foreach ($posts as $post) {
            array_push(
                $params,
                $post['title'],
                $post['slug'],
                $post['image'],
                $post['description'],
                $post['content'],
                $post['views'],
                $post['published_at'],
            );
        }

        $rows = implode(', ', array_fill(0, count($posts), '(?, ?, ?, ?, ?, ?, ?)'));
        $this->pdo->prepare(
            "INSERT INTO posts (title, slug, image, description, content, views, published_at) VALUES {$rows}",
        )->execute($params);
    }

    /**
     * @param list<array{int, int}> $links пары [id статьи, id категории]
     */
    public function attachCategories(array $links): void
    {
        if ($links === []) {
            return;
        }

        $rows = implode(', ', array_fill(0, count($links), '(?, ?)'));
        $this->pdo->prepare("INSERT INTO category_post (post_id, category_id) VALUES {$rows}")
            ->execute(array_merge(...$links));
    }
}
