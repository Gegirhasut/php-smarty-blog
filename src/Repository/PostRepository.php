<?php

declare(strict_types=1);

namespace App\Repository;

use App\Blog\Slug;
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
 * @phpstan-type Post array{
 *     id: int,
 *     slug: string,
 *     title: string,
 *     description: string,
 *     image: string,
 *     views: int,
 *     published_at: string,
 *     content: string
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
     * @return Post|null
     */
    public function findBySlug(string $slug): ?array
    {
        // Строка не в формате slug не может быть в базе, а запрос с ней MySQL отклонил бы с ошибкой
        if (!Slug::isValid($slug)) {
            return null;
        }

        $statement = $this->pdo->prepare(
            'SELECT id, slug, title, description, image, views, published_at, content FROM posts WHERE slug = ?',
        );
        $statement->execute([$slug]);

        /** @var Post|false $post */
        $post = $statement->fetch();

        return $post === false ? null : $post;
    }

    /**
     * Счётчик увеличивает сама база, поэтому одновременные просмотры не теряются.
     */
    public function incrementViews(int $postId): void
    {
        $this->pdo->prepare('UPDATE posts SET views = views + 1 WHERE id = ?')->execute([$postId]);
    }

    /**
     * Похожие статьи: сначала те, у кого больше общих категорий со статьёй, среди равных - новые.
     *
     * @return list<PostCard>
     */
    public function similar(int $postId, int $limit): array
    {
        // own - категории статьи, other - другие статьи этих категорий; COUNT(*) в группе - число общих категорий.
        // Остальные поля p можно выбирать при GROUP BY p.id: они зависят от первичного ключа
        $statement = $this->pdo->prepare(
            'SELECT p.id, p.slug, p.title, p.description, p.image, p.views, p.published_at
             FROM category_post own
             JOIN category_post other ON other.category_id = own.category_id AND other.post_id <> own.post_id
             JOIN posts p ON p.id = other.post_id
             WHERE own.post_id = ?
             GROUP BY p.id
             ORDER BY COUNT(*) DESC, p.published_at DESC, p.id DESC
             LIMIT ?',
        );
        $statement->execute([$postId, $limit]);

        /** @var list<PostCard> */
        return $statement->fetchAll();
    }

    /**
     * @return list<PostCard>
     */
    public function latest(int $limit): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, slug, title, description, image, views, published_at FROM posts
             ORDER BY published_at DESC, id DESC
             LIMIT ?',
        );
        $statement->execute([$limit]);

        /** @var list<PostCard> */
        return $statement->fetchAll();
    }

    /**
     * Все статьи для sitemap, от новых к старым. В один файл sitemap помещается 50 000 адресов:
     * блогу до этого далеко, а больше понадобился бы индекс из нескольких файлов.
     *
     * @return list<array{slug: string, published_at: string}>
     */
    public function forSitemap(): array
    {
        $statement = $this->pdo->prepare('SELECT slug, published_at FROM posts ORDER BY published_at DESC, id DESC');
        $statement->execute();

        /** @var list<array{slug: string, published_at: string}> */
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
     * @param list<array{int, int, int}> $links тройки [id статьи, id категории, место категории у статьи]
     */
    public function attachCategories(array $links): void
    {
        if ($links === []) {
            return;
        }

        $rows = implode(', ', array_fill(0, count($links), '(?, ?, ?)'));
        $this->pdo->prepare("INSERT INTO category_post (post_id, category_id, position) VALUES {$rows}")
            ->execute(array_merge(...$links));
    }
}
