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

/**
 * Списки выбирают id по индексам связки category_post (там копии даты и просмотров) и читают posts только для них.
 */
final class PostRepository
{
    // Без текста статьи: в списках он не нужен. Основная категория - для заглушки вместо обложки
    private const CARD_COLUMNS = 'p.id, p.slug, p.title, p.description, p.image, p.views, p.published_at,
        (SELECT mc.slug FROM category_post mcp JOIN categories mc ON mc.id = mcp.category_id
         WHERE mcp.post_id = p.id ORDER BY mcp.position LIMIT 1) AS main_category';
    // Кандидаты в похожие - столько последних статей каждой категории статьи
    private const SIMILAR_CANDIDATES = 50;

    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Последние статьи каждой непустой категории одним запросом, порядок категорий - по id, как в меню.
     *
     * @return list<array{category: Category, posts: list<PostCard>}>
     */
    public function latestByCategory(int $perCategory): array
    {
        // cp.category_id в ORDER BY - иначе MySQL не видит, что индекс уже упорядочен, и сортирует всю категорию
        $statement = $this->pdo->prepare(
            'SELECT c.id AS category_id, c.slug AS category_slug, c.name AS category_name,
                    c.description AS category_description, ' . self::CARD_COLUMNS . '
             FROM categories c
             JOIN LATERAL (
                 SELECT cp.post_id FROM category_post cp
                 WHERE cp.category_id = c.id
                 ORDER BY cp.category_id DESC, cp.published_at DESC, cp.post_id DESC
                 LIMIT ?
             ) latest ON TRUE
             JOIN posts p ON p.id = latest.post_id
             ORDER BY c.id, p.published_at DESC, p.id DESC',
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
            $sections[$categoryId]['posts'][] = $this->card($row);
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
     * Страница категории; id в конце порядка - чтобы равные по дате или просмотрам не переезжали между страницами.
     *
     * @return list<PostCard>
     */
    public function inCategory(int $categoryId, Sort $sort, int $limit, int $offset): array
    {
        // ORDER BY не принимает параметры запроса, поэтому колонка выбирается из готовых строк
        $column = match ($sort) {
            Sort::Date => 'published_at',
            Sort::Views => 'views',
        };

        // Отложенное соединение: OFFSET пропускает записи индекса связки, а не строки статей
        $statement = $this->pdo->prepare(
            'SELECT ' . self::CARD_COLUMNS . " FROM (
                 SELECT cp.post_id, cp.{$column} AS sort_value FROM category_post cp
                 WHERE cp.category_id = ?
                 ORDER BY cp.{$column} DESC, cp.post_id DESC
                 LIMIT ? OFFSET ?
             ) page
             JOIN posts p ON p.id = page.post_id
             ORDER BY page.sort_value DESC, page.post_id DESC",
        );
        $statement->execute([$categoryId, $limit, $offset]);

        return array_map($this->card(...), Row::all($statement));
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

        return $row === null ? null : $this->post($row);
    }

    public function incrementViews(int $postId): void
    {
        // Один UPDATE: атомарен, строку меняет один раз при любом числе связей; LEFT JOIN - для статьи без категорий
        $this->pdo->prepare(
            'UPDATE posts p LEFT JOIN category_post cp ON cp.post_id = p.id
             SET p.views = p.views + 1, cp.views = cp.views + 1
             WHERE p.id = ?',
        )->execute([$postId]);
    }

    /**
     * Статьи из её категорий: больше общих - выше, затем новее; меньше $limit - только в крошечном блоге.
     *
     * @return list<PostCard>
     */
    public function similar(int $postId, int $limit): array
    {
        $similar = $this->byCommonCategories($postId, $limit);
        if (count($similar) === $limit) {
            return $similar;
        }

        // Добор отдельным запросом, а не LEFT JOIN со всеми статьями: он нужен редко и идёт по индексу даты с LIMIT
        $exclude = [$postId, ...array_map(fn (PostCard $post): int => $post->id, $similar)];

        return [...$similar, ...$this->latestExcept($exclude, $limit - count($similar))];
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

        return array_map($this->card(...), Row::all($statement));
    }

    /**
     * Дата самой свежей статьи блога; null - статей нет.
     */
    public function newestPublishedAt(): ?DateTimeImmutable
    {
        $statement = $this->pdo->prepare('SELECT published_at FROM posts ORDER BY published_at DESC LIMIT 1');
        $statement->execute();

        return Row::first($statement)?->date('published_at');
    }

    /**
     * Наибольший id статьи, 0 - статей нет: по нему считаются файлы sitemap.
     */
    public function lastId(): int
    {
        $statement = $this->pdo->prepare('SELECT id FROM posts ORDER BY id DESC LIMIT 1');
        $statement->execute();

        return Row::first($statement)?->int('id') ?? 0;
    }

    /**
     * Даты статей с id от $firstId до $lastId для файла sitemap - по покрывающему индексу, без строк с текстом.
     *
     * @return array<string, DateTimeImmutable> slug статьи => дата
     */
    public function publishedDatesBetweenIds(int $firstId, int $lastId): array
    {
        $statement = $this->pdo->prepare('SELECT slug, published_at FROM posts WHERE id BETWEEN ? AND ? ORDER BY id');
        $statement->execute([$firstId, $lastId]);

        $dates = [];
        foreach (Row::all($statement) as $row) {
            $dates[$row->string('slug')] = $row->date('published_at');
        }

        return $dates;
    }

    /**
     * @param list<string> $slugs
     * @return array<string, int>
     */
    public function idsBySlug(array $slugs): array
    {
        if ($slugs === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($slugs), '?'));
        $statement = $this->pdo->prepare("SELECT slug, id FROM posts WHERE slug IN ({$placeholders})");
        $statement->execute($slugs);

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
     *     image: ?string,
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
     * Связи статей с категориями. Дата и просмотры - копии полей статьи: по ним сортируются ленты категорий.
     *
     * @param list<array{post_id: int, category_id: int, position: int, published_at: string, views: int}> $links
     *     position - место категории у статьи, 0 - основная
     */
    public function attachCategories(array $links): void
    {
        if ($links === []) {
            return;
        }

        $params = [];
        foreach ($links as $link) {
            array_push($params, $link['post_id'], $link['category_id'], $link['position'], $link['published_at'], $link['views']);
        }

        $rows = implode(', ', array_fill(0, count($links), '(?, ?, ?, ?, ?)'));
        $this->pdo->prepare(
            "INSERT INTO category_post (post_id, category_id, position, published_at, views) VALUES {$rows}",
        )->execute($params);
    }

    /**
     * @return list<PostCard>
     */
    private function byCommonCategories(int $postId, int $limit): array
    {
        // Последние статьи категории - обратный проход по индексу связки с LIMIT, как на главной, а не вся категория:
        // запрос один и быстрый при любом объёме, цена - у старых статей огромной категории блок близок к её свежим
        $statement = $this->pdo->prepare(
            'SELECT ' . self::CARD_COLUMNS . ' FROM (
                 SELECT other.post_id, COUNT(*) AS common, MAX(other.published_at) AS published_at
                 FROM category_post own
                 JOIN LATERAL (
                     SELECT cp.post_id, cp.published_at FROM category_post cp
                     WHERE cp.category_id = own.category_id AND cp.post_id <> own.post_id
                     ORDER BY cp.category_id DESC, cp.published_at DESC, cp.post_id DESC
                     LIMIT ' . self::SIMILAR_CANDIDATES . '
                 ) other ON TRUE
                 WHERE own.post_id = ?
                 GROUP BY other.post_id
                 ORDER BY common DESC, published_at DESC, other.post_id DESC
                 LIMIT ?
             ) chosen
             JOIN posts p ON p.id = chosen.post_id
             ORDER BY chosen.common DESC, chosen.published_at DESC, chosen.post_id DESC',
        );
        $statement->execute([$postId, $limit]);

        return array_map($this->card(...), Row::all($statement));
    }

    /**
     * @param non-empty-list<int> $exclude
     * @return list<PostCard>
     */
    private function latestExcept(array $exclude, int $limit): array
    {
        $placeholders = implode(', ', array_fill(0, count($exclude), '?'));
        $statement = $this->pdo->prepare(
            'SELECT ' . self::CARD_COLUMNS . " FROM posts p
             WHERE p.id NOT IN ({$placeholders})
             ORDER BY p.published_at DESC, p.id DESC
             LIMIT ?",
        );
        $statement->execute([...$exclude, $limit]);

        return array_map($this->card(...), Row::all($statement));
    }

    private function card(Row $row): PostCard
    {
        return new PostCard(
            id: $row->int('id'),
            slug: $row->string('slug'),
            title: $row->string('title'),
            description: $row->string('description'),
            image: $row->nullableString('image'),
            views: $row->int('views'),
            publishedAt: $row->date('published_at'),
            mainCategorySlug: $row->nullableString('main_category'),
        );
    }

    private function post(Row $row): Post
    {
        return new Post(
            id: $row->int('id'),
            slug: $row->string('slug'),
            title: $row->string('title'),
            description: $row->string('description'),
            image: $row->nullableString('image'),
            views: $row->int('views'),
            publishedAt: $row->date('published_at'),
            content: $row->string('content'),
        );
    }
}
