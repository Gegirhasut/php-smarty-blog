<?php

declare(strict_types=1);

namespace App\Repository;

use App\Blog\Category;
use App\Blog\Post;
use App\Blog\PostCard;
use App\Blog\Slug;
use App\Blog\Sort;
use App\Database\Row;
use DateTimeImmutable;
use PDO;

final class PostRepository
{
    // Колонки для PostCard: в списках текст статьи не нужен
    private const CARD_COLUMNS = 'p.id, p.slug, p.title, p.description, p.image, p.views, p.published_at';

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
            'SELECT * FROM (
                 SELECT c.id AS category_id, c.slug AS category_slug, c.name AS category_name,
                        c.description AS category_description, ' . self::CARD_COLUMNS . ',
                        ROW_NUMBER() OVER (PARTITION BY c.id ORDER BY p.published_at DESC, p.id DESC) AS place
                 FROM categories c
                 JOIN category_post cp ON cp.category_id = c.id
                 JOIN posts p ON p.id = cp.post_id
             ) ranked
             WHERE place <= ?
             ORDER BY category_id, place',
        );
        $statement->execute([$perCategory]);

        $sections = [];
        foreach (Row::all($statement) as $row) {
            $categoryId = $row->int('category_id');
            $sections[$categoryId] ??= [
                'category' => new Category(
                    $categoryId,
                    $row->string('category_slug'),
                    $row->string('category_name'),
                    $row->string('category_description'),
                ),
                'posts' => [],
            ];
            $sections[$categoryId]['posts'][] = PostCard::fromRow($row);
        }

        return array_values($sections);
    }

    public function countInCategory(int $categoryId): int
    {
        $statement = $this->pdo->prepare('SELECT COUNT(*) AS total FROM category_post WHERE category_id = ?');
        $statement->execute([$categoryId]);

        return Row::first($statement)?->int('total') ?? 0;
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
            'SELECT ' . self::CARD_COLUMNS . " FROM posts p
             JOIN category_post cp ON cp.post_id = p.id
             WHERE cp.category_id = ?
             ORDER BY {$order}
             LIMIT ? OFFSET ?",
        );
        $statement->execute([$categoryId, $limit, $offset]);

        return array_map(PostCard::fromRow(...), Row::all($statement));
    }

    public function findBySlug(string $slug): ?Post
    {
        // Строка не в формате slug не может быть в базе, а запрос с ней MySQL отклонил бы с ошибкой
        if (!Slug::isValid($slug)) {
            return null;
        }

        $statement = $this->pdo->prepare(
            'SELECT id, slug, title, description, image, views, published_at, content FROM posts WHERE slug = ?',
        );
        $statement->execute([$slug]);
        $row = Row::first($statement);

        return $row === null ? null : Post::fromRow($row);
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
            'SELECT ' . self::CARD_COLUMNS . ' FROM category_post own
             JOIN category_post other ON other.category_id = own.category_id AND other.post_id <> own.post_id
             JOIN posts p ON p.id = other.post_id
             WHERE own.post_id = ?
             GROUP BY p.id
             ORDER BY COUNT(*) DESC, p.published_at DESC, p.id DESC
             LIMIT ?',
        );
        $statement->execute([$postId, $limit]);

        return array_map(PostCard::fromRow(...), Row::all($statement));
    }

    /**
     * Все статьи, разложенные по основной категории, в каждой - от новых к старым.
     *
     * @return array<int, list<PostCard>> id категории => статьи
     */
    public function byMainCategory(): array
    {
        $statement = $this->pdo->prepare(
            'SELECT cp.category_id, ' . self::CARD_COLUMNS . ' FROM category_post cp
             JOIN posts p ON p.id = cp.post_id
             WHERE cp.position = 0
             ORDER BY cp.category_id, p.published_at DESC, p.id DESC',
        );
        $statement->execute();

        $posts = [];
        foreach (Row::all($statement) as $row) {
            $posts[$row->int('category_id')][] = PostCard::fromRow($row);
        }

        return $posts;
    }

    /**
     * @return list<PostCard>
     */
    public function latest(int $limit): array
    {
        $statement = $this->pdo->prepare(
            'SELECT ' . self::CARD_COLUMNS . ' FROM posts p
             ORDER BY p.published_at DESC, p.id DESC
             LIMIT ?',
        );
        $statement->execute([$limit]);

        return array_map(PostCard::fromRow(...), Row::all($statement));
    }

    /**
     * Даты публикации всех статей, от новых к старым. В один файл sitemap помещается 50 000 адресов:
     * блогу до этого далеко, а больше понадобился бы индекс из нескольких файлов.
     *
     * @return array<string, DateTimeImmutable> slug статьи => дата
     */
    public function publishedDates(): array
    {
        $statement = $this->pdo->prepare('SELECT slug, published_at FROM posts ORDER BY published_at DESC, id DESC');
        $statement->execute();

        $dates = [];
        foreach (Row::all($statement) as $row) {
            $dates[$row->string('slug')] = $row->date('published_at');
        }

        return $dates;
    }

    /**
     * @return array<string, int>
     */
    public function idsBySlug(): array
    {
        $statement = $this->pdo->prepare('SELECT slug, id FROM posts');
        $statement->execute();

        $ids = [];
        foreach (Row::all($statement) as $row) {
            $ids[$row->string('slug')] = $row->int('id');
        }

        return $ids;
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
